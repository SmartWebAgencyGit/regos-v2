<?php

namespace App\Http\Controllers;

use App\Exceptions\Hapi\HapiException;
use App\Portal\AddPlayers\AddPlayersCheckout;
use App\Portal\BookingPresenter;
use App\Portal\PortalContext;
use App\Portal\PortalRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * The player's boarding pass: event details and their check-in QR code.
 */
class BoardingPassController extends Controller
{
    public function __construct(private readonly PortalContext $portal) {}

    public function show(AddPlayersCheckout $checkout): InertiaResponse|RedirectResponse
    {
        $registration = $this->portal->registration();

        if (! $registration->hasAcceptedDeclarations()) {
            return redirect()->route('verify');
        }

        $booking = $this->portal->booking();
        $links = $this->portal->links();

        return Inertia::render('BoardingPass', [
            'booking' => BookingPresenter::booking($booking),
            'registration' => BookingPresenter::registration($registration),
            'qrUrl' => $registration->isComplete() ? route('qr', ['r' => $registration->id()]) : null,
            'links' => [
                'directions' => $links->directions(),
                'map' => $links->map(),
                'infoPack' => $links->infoPack(),
                'itinerary' => $links->itinerary(),
            ],
            'canAddPlayers' => $checkout->isAvailableFor($booking),
        ]);
    }

    /**
     * Proxy the check-in QR code from HAPI.
     */
    public function qr(PortalRepository $repository): Response
    {
        try {
            $svg = $repository->registrationQr($this->portal->registration()->id());
        } catch (HapiException) {
            abort(404);
        }

        return self::svg($svg);
    }

    public static function svg(string $svg): Response
    {
        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, max-age=300',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
