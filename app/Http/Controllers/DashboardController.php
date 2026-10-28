<?php

namespace App\Http\Controllers;

use App\Portal\BookingPresenter;
use App\Portal\PortalContext;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Booking overview with the countdown to game day.
     */
    public function __invoke(PortalContext $portal): Response
    {
        return Inertia::render('Dashboard', [
            'booking' => BookingPresenter::booking($portal->booking()),
            'registration' => BookingPresenter::registration($portal->registration()),
        ]);
    }
}
