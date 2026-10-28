<?php

namespace App\Hapi;

use App\Exceptions\Hapi\HapiException;

/**
 * Client for HAPI, the Xena booking API that owns all portal data.
 *
 * URIs are relative to the API root and include their query string, e.g.
 * "/bookings/123?includes=site.address&columns=id,site_id".
 */
interface Hapi
{
    /**
     * Perform an authenticated GET and return the decoded JSON body.
     *
     * @return array<string, mixed>
     *
     * @throws HapiException
     */
    public function get(string $uri): array;

    /**
     * Perform an authenticated JSON POST and return the decoded JSON body.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws HapiException
     */
    public function post(string $uri, array $payload): array;

    /**
     * Perform an authenticated GET and return the raw body (QR SVGs, links).
     *
     * @throws HapiException
     */
    public function raw(string $uri): string;
}
