<?php

namespace App\Support\Screenshots;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Owns a headless Chrome started for one capture: an isolated profile, a
 * private debugging port, and a guaranteed teardown.
 */
class ChromeProcess
{
    protected Process $process;

    protected string $profileDirectory;

    protected int $port;

    protected ?string $webSocketUrl = null;

    public function __construct(protected string $binary, protected int $timeout) {}

    public function start(): void
    {
        $this->port = $this->reservePort();
        $this->profileDirectory = sys_get_temp_dir().'/abify-chrome-'.bin2hex(random_bytes(8));

        $this->process = new Process([
            $this->binary,
            '--headless=new',
            '--remote-debugging-port='.$this->port,
            '--user-data-dir='.$this->profileDirectory,
            '--no-first-run',
            '--no-default-browser-check',
            '--disable-gpu',
            '--disable-dev-shm-usage',
            '--disable-extensions',
            '--disable-background-networking',
            '--disable-sync',
            '--disable-translate',
            '--mute-audio',
            '--hide-scrollbars',
            // Nothing here should ever reach a real user's browser profile,
            // and captures target arbitrary customer URLs — keep it sealed.
            '--no-sandbox',
            '--disable-setuid-sandbox',
            'about:blank',
        ]);

        $this->process->setTimeout($this->timeout);
        $this->process->start();

        $this->webSocketUrl = $this->awaitDebuggerUrl();
    }

    public function webSocketUrl(): string
    {
        if ($this->webSocketUrl === null) {
            throw new RuntimeException('Chrome has not been started.');
        }

        return $this->webSocketUrl;
    }

    public function stop(): void
    {
        if (isset($this->process) && $this->process->isRunning()) {
            $this->process->stop(5, SIGKILL);
        }

        if (isset($this->profileDirectory) && is_dir($this->profileDirectory)) {
            // Best effort. Chrome's renderer and GPU children can still be
            // flushing profile files for a moment after the browser process
            // dies, so a leftover temp directory is a tidiness problem, never
            // a reason to fail a capture that already succeeded.
            try {
                $this->deleteDirectory($this->profileDirectory);
            } catch (Throwable $exception) {
                Log::debug("Could not clean up the Chrome profile at {$this->profileDirectory}: ".$exception->getMessage());
            }
        }
    }

    /**
     * Chrome writes its debugger URL to the port file (and to stderr) only
     * once it is actually listening, so poll /json/version rather than
     * guessing at a fixed sleep.
     */
    protected function awaitDebuggerUrl(): string
    {
        $deadline = microtime(true) + min($this->timeout, 30);

        while (microtime(true) < $deadline) {
            if (! $this->process->isRunning()) {
                throw new RuntimeException(
                    'Chrome exited before the DevTools endpoint came up: '
                    .trim($this->process->getErrorOutput() ?: $this->process->getOutput())
                );
            }

            $context = stream_context_create(['http' => ['timeout' => 1]]);
            $body = @file_get_contents("http://127.0.0.1:{$this->port}/json/version", false, $context);

            if ($body !== false) {
                $url = json_decode($body, true)['webSocketDebuggerUrl'] ?? null;

                if (is_string($url)) {
                    return $url;
                }
            }

            usleep(150_000);
        }

        throw new RuntimeException('Timed out waiting for Chrome to open its DevTools endpoint.');
    }

    /**
     * Bind port 0 and let the OS pick, so parallel captures (queue workers)
     * never collide on a hard-coded debugging port.
     */
    protected function reservePort(): int
    {
        $socket = @stream_socket_server('tcp://127.0.0.1:0', $errno, $error);

        if ($socket === false) {
            throw new RuntimeException("Cannot reserve a debugging port: {$error} ({$errno})");
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr((string) $name, strrpos((string) $name, ':') + 1);
    }

    protected function deleteDirectory(string $directory): void
    {
        foreach (@scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.'/'.$entry;

            if (is_dir($path) && ! is_link($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        // One retry: a child process that outlived the browser may have
        // written into this directory between the scan and the rmdir.
        if (! @rmdir($directory) && is_dir($directory)) {
            usleep(200_000);

            foreach (@scandir($directory) ?: [] as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    $path = $directory.'/'.$entry;

                    is_dir($path) && ! is_link($path)
                        ? $this->deleteDirectory($path)
                        : @unlink($path);
                }
            }

            @rmdir($directory);
        }
    }
}
