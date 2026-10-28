<?php

namespace Tests\Unit;

use App\Portal\NetworkLocator;
use PHPUnit\Framework\TestCase;

class NetworkLocatorTest extends TestCase
{
    public function test_it_maps_site_network_addresses_to_their_site(): void
    {
        $locator = new NetworkLocator(['10.1.3.0/24' => 87, '10.3.2.0/24' => 3]);

        $this->assertSame(87, $locator->siteIdFor('10.1.3.200'));
        $this->assertSame(3, $locator->siteIdFor('10.3.2.1'));
        $this->assertNull($locator->siteIdFor('10.1.4.1'));
        $this->assertNull($locator->siteIdFor('203.0.113.9'));
        $this->assertNull($locator->siteIdFor(null));
    }
}
