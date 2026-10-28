<?php

namespace App\Http\Middleware;

use App\Portal\PortalContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(private readonly PortalContext $portal) {}

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * The "portal" summary only uses a booking the request has already
     * loaded, so pages shown before sign-in never call HAPI for it.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'brand' => [
                'name' => config('regos.brand.name'),
                'phone' => config('regos.brand.phone'),
                'website' => config('regos.brand.website'),
            ],
            'devMode' => [
                'demoBookings' => (bool) config('regos.hapi.fake'),
                'simulatedPayments' => config('regos.payments.driver') === 'fake',
            ],
            'onSite' => fn () => $this->portal->isInternal(),
            'portal' => function () {
                $booking = $this->portal->loadedBooking();

                if ($booking === null) {
                    return null;
                }

                $registration = $booking->registration($this->portal->key()?->registrationId);

                return [
                    'party' => $booking->partyName(),
                    'hasRegistration' => $registration !== null,
                    'registrationComplete' => (bool) $registration?->isComplete(),
                ];
            },
            'flash' => fn () => array_filter([
                'success' => $request->session()->get('status'),
                'warning' => $request->session()->get('warning'),
                'error' => $request->session()->get('error'),
            ]),
        ];
    }
}
