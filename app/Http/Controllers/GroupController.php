<?php

namespace App\Http\Controllers;

use App\Exceptions\Hapi\HapiException;
use App\Exceptions\Portal\PortalAccessDenied;
use App\Portal\Booking;
use App\Portal\BookingPresenter;
use App\Portal\PortalContext;
use App\Portal\PortalRepository;
use App\Portal\UserKey;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * On-site group picker for shared devices at a paintball centre.
 */
class GroupController extends Controller
{
    public function __construct(
        private readonly PortalContext $portal,
        private readonly PortalRepository $repository,
    ) {}

    public function index(): Response
    {
        try {
            $groups = array_map(fn (array $group) => [
                'bookingId' => $group['booking_id'],
                'name' => Booking::formatName($group['name_first'], $group['name_last']),
                'initials' => BookingPresenter::initials($group['name_first'], $group['name_last']),
                'players' => $group['pax_booked'],
            ], $this->repository->groups($this->portal->siteId()));
        } catch (HapiException) {
            $groups = null;
        }

        return Inertia::render('Groups', ['groups' => $groups]);
    }

    public function join(int $bookingId): RedirectResponse
    {
        try {
            $group = collect($this->repository->groups($this->portal->siteId()))->firstWhere('booking_id', $bookingId);

            if ($group === null) {
                return back()->with('error', 'Error Joining Group');
            }

            $key = UserKey::fromString($group['registration_key']);
            $this->repository->authorize($key);
        } catch (HapiException|PortalAccessDenied) {
            return back()->with('error', 'Error Joining Group');
        }

        $this->portal->signIn($key);

        return redirect()->route('dashboard');
    }
}
