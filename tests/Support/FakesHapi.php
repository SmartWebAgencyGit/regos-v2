<?php

namespace Tests\Support;

use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Fakes HAPI (and Worldpay) over HTTP so feature tests exercise the real
 * client, URIs and payloads.
 */
trait FakesHapi
{
    /**
     * @var array<string, mixed>
     */
    protected array $hapiBooking = [];

    /**
     * @var list<array<string, mixed>>
     */
    protected array $hapiCustomers = [];

    /**
     * Per-test responses keyed by "METHOD /path" (regex allowed after the method).
     *
     * @var array<string, PromiseInterface|Closure>
     */
    protected array $hapiResponses = [];

    protected ?string $worldpayReply = null;

    /**
     * @param  array<string, mixed>  $booking
     */
    protected function fakeHapi(array $booking): void
    {
        $this->hapiBooking = $booking;

        Http::fake(function (Request $request) {
            if (Str::startsWith($request->url(), 'https://worldpay.test')) {
                return Http::response($this->worldpayReply ?? self::worldpayReply('AUTHORISED'));
            }

            $path = Str::after((string) parse_url($request->url(), PHP_URL_PATH), '/api');
            $route = $request->method().' '.$path;

            foreach ($this->hapiResponses as $pattern => $response) {
                if (preg_match('#^'.$pattern.'$#', $route)) {
                    return $response instanceof Closure ? $response($request) : $response;
                }
            }

            return match (true) {
                $route === 'POST /auth' => Http::response(['token' => 'test-token']),
                (bool) preg_match('#^GET /bookings/\d+$#', $route) => Http::response(['data' => $this->hapiBooking]),
                (bool) preg_match('#^GET /sites/\d+$#', $route) => Http::response(['data' => ['id' => 87, 'address' => ['timezone' => 'Europe/London']]]),
                $route === 'GET /customers' => Http::response(['data' => $this->hapiCustomers]),
                $route === 'POST /registrations' => Http::response(['data' => ['id' => 7200]]),
                (bool) preg_match('#^GET /registrations/\d+/qr$#', $route) => Http::response('<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>'),
                default => Http::response(['data' => []]),
            };
        });
    }

    /**
     * HAPI requests (excluding auth) sent so far, as "METHOD /path" => payload.
     *
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    protected function hapiRequests(string $method, string $pathPattern): array
    {
        return Http::recorded(function (Request $request) use ($method, $pathPattern) {
            $path = Str::after((string) parse_url($request->url(), PHP_URL_PATH), '/api');

            return Str::startsWith($request->url(), 'https://hapi.test')
                && $request->method() === $method
                && preg_match('#^'.$pathPattern.'$#', $path);
        })->map(fn (array $pair) => [$pair[0]->url(), $pair[0]->data()])->values()->all();
    }

    protected static function worldpayReply(string $lastEvent): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><paymentService version="1.4" merchantCode="TEST"><reply>'
            .'<orderStatus orderCode="X"><payment><lastEvent>'.$lastEvent.'</lastEvent></payment></orderStatus>'
            .'</reply></paymentService>';
    }

    protected function signInAs(?int $registrationId = null): static
    {
        return $this->withSession(['regos.user_key' => HapiFixtures::key($registrationId)]);
    }
}
