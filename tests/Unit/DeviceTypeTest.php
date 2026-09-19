<?php

namespace Tests\Unit;

use App\Support\Analytics\DeviceType;
use Tests\TestCase;

class DeviceTypeTest extends TestCase
{
    public function test_it_classifies_viewport_widths_into_device_buckets()
    {
        $this->assertSame(DeviceType::MOBILE, DeviceType::fromViewportWidth(390));
        $this->assertSame(DeviceType::TABLET, DeviceType::fromViewportWidth(834));
        $this->assertSame(DeviceType::DESKTOP, DeviceType::fromViewportWidth(1440));
    }

    public function test_bucket_boundaries_are_inclusive_of_their_ceiling()
    {
        // The thresholds are CSS breakpoints, so the width that still gets the
        // mobile layout must still count as mobile.
        $this->assertSame(DeviceType::MOBILE, DeviceType::fromViewportWidth(768));
        $this->assertSame(DeviceType::TABLET, DeviceType::fromViewportWidth(769));
        $this->assertSame(DeviceType::TABLET, DeviceType::fromViewportWidth(1024));
        $this->assertSame(DeviceType::DESKTOP, DeviceType::fromViewportWidth(1025));
    }

    public function test_an_unreported_width_falls_back_to_desktop()
    {
        // Rather than inventing a fourth "unknown" segment nobody can act on.
        $this->assertSame(DeviceType::DESKTOP, DeviceType::fromViewportWidth(null));
        $this->assertSame(DeviceType::DESKTOP, DeviceType::fromViewportWidth(0));
        $this->assertSame(DeviceType::DESKTOP, DeviceType::fromViewportWidth(-1));
    }

    public function test_it_validates_device_keys_against_the_configured_devices()
    {
        $this->assertTrue(DeviceType::isValid('mobile'));
        $this->assertFalse(DeviceType::isValid('watch'));
        $this->assertFalse(DeviceType::isValid(null));
    }

    public function test_every_bucket_has_a_capture_configuration()
    {
        // A device that classifies but has no emulated viewport would produce
        // points with no screenshot to draw them on.
        foreach (DeviceType::all() as $device) {
            $this->assertIsArray(config("screenshots.devices.{$device}"));
            $this->assertNotEmpty(config("screenshots.devices.{$device}.width"));
        }
    }
}
