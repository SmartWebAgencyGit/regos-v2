<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class ThanksController extends Controller
{
    /**
     * Shown after a successful extra-players payment.
     */
    public function __invoke(): Response
    {
        return Inertia::render('Thanks');
    }
}
