<?php

namespace App\Support\Screenshots;

use RuntimeException;

/**
 * A minimal Chrome DevTools Protocol client: a hand-rolled WebSocket
 * (RFC 6455) client plus request/response correlation.
 *
 * Chrome's CLI can only screenshot the viewport, which is useless for a
 * heatmap — the whole point is to see clicks that happened four screens down.
 * CDP's Page.captureScreenshot with captureBeyondViewport, paired with
 * Emulation.setDeviceMetricsOverride, gives a true full-page image at a real
 * emulated device size. That needs a WebSocket, and pulling in a browser
 * automation package (and a second Chrome download) for ~150 lines of framing
 * isn't a trade worth making — so the framing lives here.
 *
 * Only what CDP actually uses is implemented: text frames, client-side
 * masking, continuation frames, and ping/pong. No extensions, no compression.
 */
class DevToolsConnection
{
    protected const OP_CONTINUATION = 0x0;

    protected const OP_TEXT = 0x1;

    protected const OP_CLOSE = 0x8;

    protected const OP_PING = 0x9;

    protected const OP_PONG = 0xA;

    /** The fixed RFC 6455 handshake GUID. */
    protected const HANDSHAKE_GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    /** @var resource */
    protected $socket;

    protected int $nextId = 0;

    /**
     * Events that arrived while we were waiting for a command response.
     * CDP multiplexes both over one socket, so anything that isn't the reply
     * we asked for is parked here rather than dropped.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $events = [];

    public function __construct(string $webSocketUrl, protected int $timeout = 60)
    {
        $parts = parse_url($webSocketUrl);

        if (! isset($parts['host'], $parts['port'])) {
            throw new RuntimeException("Malformed DevTools WebSocket URL: {$webSocketUrl}");
        }

        $socket = @stream_socket_client(
            "tcp://{$parts['host']}:{$parts['port']}",
            $errno,
            $error,
            $this->timeout,
        );

        if ($socket === false) {
            throw new RuntimeException("Cannot reach the DevTools endpoint: {$error} ({$errno})");
        }

        $this->socket = $socket;
        stream_set_timeout($this->socket, $this->timeout);

        $this->handshake(
            $parts['host'],
            $parts['port'],
            ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : ''),
        );
    }

    /**
     * Send a CDP command and block until its reply comes back.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function call(string $method, array $params = [], ?string $sessionId = null): array
    {
        $id = ++$this->nextId;

        $message = ['id' => $id, 'method' => $method, 'params' => (object) $params];

        if ($sessionId !== null) {
            $message['sessionId'] = $sessionId;
        }

        $this->writeFrame((string) json_encode($message));

        $deadline = microtime(true) + $this->timeout;

        while (microtime(true) < $deadline) {
            $frame = $this->readMessage();

            if ($frame === null) {
                throw new RuntimeException("DevTools closed the connection during {$method}.");
            }

            if (($frame['id'] ?? null) === $id) {
                if (isset($frame['error'])) {
                    throw new RuntimeException($method.' failed: '.json_encode($frame['error']));
                }

                return $frame['result'] ?? [];
            }

            if (isset($frame['method'])) {
                $this->events[] = $frame;
            }
        }

        throw new RuntimeException("Timed out waiting for a reply to {$method}.");
    }

    /**
     * Block until a given CDP event arrives, or the timeout expires.
     * Returns false on timeout rather than throwing: for events like
     * Page.loadEventFired a slow page is a reason to carry on and capture
     * whatever painted, not to fail the whole job.
     */
    public function waitForEvent(string $method, int $timeoutSeconds): bool
    {
        foreach ($this->events as $index => $event) {
            if ($event['method'] === $method) {
                unset($this->events[$index]);

                return true;
            }
        }

        $deadline = microtime(true) + $timeoutSeconds;

        while (microtime(true) < $deadline) {
            $frame = $this->readMessage();

            if ($frame === null) {
                return false;
            }

            if (($frame['method'] ?? null) === $method) {
                return true;
            }

            if (isset($frame['method'])) {
                $this->events[] = $frame;
            }
        }

        return false;
    }

    public function close(): void
    {
        if (is_resource($this->socket)) {
            @fclose($this->socket);
        }
    }

    protected function handshake(string $host, int $port, string $path): void
    {
        $key = base64_encode(random_bytes(16));

        $request = "GET {$path} HTTP/1.1\r\n"
            ."Host: {$host}:{$port}\r\n"
            ."Upgrade: websocket\r\n"
            ."Connection: Upgrade\r\n"
            ."Sec-WebSocket-Key: {$key}\r\n"
            ."Sec-WebSocket-Version: 13\r\n\r\n";

        fwrite($this->socket, $request);

        $response = '';
        $deadline = microtime(true) + $this->timeout;

        // Byte at a time: anything past the blank line is already WebSocket
        // frame data, and a buffered read would swallow the start of the
        // first frame.
        while (! str_ends_with($response, "\r\n\r\n")) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Timed out on the DevTools WebSocket handshake.');
            }

            $byte = fread($this->socket, 1);

            if ($byte === false || $byte === '') {
                if (feof($this->socket)) {
                    throw new RuntimeException('DevTools closed the connection during the handshake.');
                }

                usleep(1000);

                continue;
            }

            $response .= $byte;
        }

        if (! str_contains($response, ' 101 ')) {
            throw new RuntimeException('DevTools refused the WebSocket upgrade: '.strtok($response, "\r\n"));
        }

        $expected = base64_encode(sha1($key.self::HANDSHAKE_GUID, true));

        if (! preg_match('/^sec-websocket-accept:\s*(\S+)/mi', $response, $matches)
            || ! hash_equals($expected, $matches[1])) {
            throw new RuntimeException('DevTools returned an invalid WebSocket accept key.');
        }
    }

    /**
     * Read one complete (possibly fragmented) text message.
     *
     * @return array<string, mixed>|null Null once the peer closes.
     */
    protected function readMessage(): ?array
    {
        $payload = '';

        while (true) {
            $header = $this->readBytes(2);

            $first = ord($header[0]);
            $second = ord($header[1]);

            $final = ($first & 0x80) !== 0;
            $opcode = $first & 0x0F;
            $length = $second & 0x7F;

            if ($length === 126) {
                $length = $this->readLength('n', 2);
            } elseif ($length === 127) {
                $length = $this->readLength('J', 8);
            }

            // Server-to-client frames are never masked, per RFC 6455.
            $chunk = $length > 0 ? $this->readBytes($length) : '';

            if ($opcode === self::OP_CLOSE) {
                return null;
            }

            if ($opcode === self::OP_PING) {
                $this->writeFrame($chunk, self::OP_PONG);

                continue;
            }

            if ($opcode === self::OP_PONG) {
                continue;
            }

            if ($opcode !== self::OP_TEXT && $opcode !== self::OP_CONTINUATION) {
                continue;
            }

            $payload .= $chunk;

            if ($final) {
                /** @var array<string, mixed>|null $decoded */
                $decoded = json_decode($payload, true);

                return is_array($decoded) ? $decoded : [];
            }
        }
    }

    /**
     * Read an extended payload length: the 16- or 64-bit big-endian field
     * that follows the 7-bit length when a frame is longer than 125 bytes.
     */
    protected function readLength(string $format, int $bytes): int
    {
        $unpacked = unpack($format, $this->readBytes($bytes));

        if ($unpacked === false || ! isset($unpacked[1]) || ! is_int($unpacked[1])) {
            throw new RuntimeException('Malformed frame length from the DevTools connection.');
        }

        return $unpacked[1];
    }

    protected function readBytes(int $length): string
    {
        $buffer = '';
        $deadline = microtime(true) + $this->timeout;

        while (($remaining = $length - strlen($buffer)) > 0) {
            $chunk = fread($this->socket, $remaining);

            if ($chunk === false || $chunk === '') {
                if (feof($this->socket) || microtime(true) > $deadline) {
                    throw new RuntimeException('The DevTools connection went away mid-frame.');
                }

                usleep(500);

                continue;
            }

            $buffer .= $chunk;
        }

        return $buffer;
    }

    protected function writeFrame(string $payload, int $opcode = self::OP_TEXT): void
    {
        $length = strlen($payload);
        $mask = random_bytes(4);

        $frame = chr(0x80 | $opcode);

        if ($length < 126) {
            $frame .= chr(0x80 | $length);
        } elseif ($length < 65536) {
            $frame .= chr(0x80 | 126).pack('n', $length);
        } else {
            $frame .= chr(0x80 | 127).pack('J', $length);
        }

        $frame .= $mask.($payload ^ str_pad('', $length, $mask));

        if (@fwrite($this->socket, $frame) === false) {
            throw new RuntimeException('Failed to write to the DevTools connection.');
        }
    }
}
