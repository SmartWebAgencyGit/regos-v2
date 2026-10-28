<?php

namespace App\Http\Controllers;

use App\Portal\BookingPresenter;
use App\Portal\PortalContext;
use App\Portal\Registration;
use Inertia\Inertia;
use Inertia\Response;

class PlayerController extends Controller
{
    /**
     * Players registered against the booking.
     */
    public function __invoke(PortalContext $portal): Response
    {
        $booking = $portal->booking();
        $mine = $portal->registration()?->id();

        return Inertia::render('Players', [
            'booking' => BookingPresenter::booking($booking),
            'players' => array_map(fn (Registration $player) => [
                'id' => $player->id(),
                'name' => $player->fullName(),
                'initials' => BookingPresenter::initials($player->firstName(), $player->lastName()),
                'isYou' => $player->id() === $mine,
            ], $booking->registrations()),
        ]);
    }
}
