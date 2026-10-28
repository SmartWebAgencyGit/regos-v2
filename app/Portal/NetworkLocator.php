<?php

namespace App\Portal;

use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Maps a visitor IP to the paintball site whose network it belongs to.
 */
class NetworkLocator
{
    /**
     * @param  array<string, int>  $networks  CIDR range => HAPI site id
     */
    public function __construct(private readonly array $networks) {}

    public function siteIdFor(?string $ip): ?int
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        foreach ($this->networks as $range => $siteId) {
            if (IpUtils::checkIp($ip, $range)) {
                return (int) $siteId;
            }
        }

        return null;
    }
}
