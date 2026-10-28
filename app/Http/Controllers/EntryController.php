<?php

namespace App\Http\Controllers;

use App\Exceptions\Hapi\HapiException;
use App\Exceptions\Portal\PortalAccessDenied;
use App\Portal\PortalContext;
use App\Portal\PortalRepository;
use App\Portal\UserKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The portal's front door. SMS / email links land here with "?k={key}";
 * returning visitors are sent to the right page for their progress.
 */
class EntryController extends Controller
{
    public function __construct(
        private readonly PortalContext $portal,
        private readonly PortalRepository $repository,
    ) {}

    public function show(Request $request): RedirectResponse|Response
    {
        if ($request->filled('k')) {
            return $this->signInWithLink((string) $request->query('k'));
        }

        $error = session('portal_error');

        if ($this->portal->hasKey()) {
            try {
                return $this->redirectToProgress();
            } catch (PortalAccessDenied $exception) {
                $this->portal->signOut();
                $error = $exception->getMessage();
            }
        }

        if ($this->portal->isInternal()) {
            return redirect()->route('groups.index')->with('error', $error);
        }

        return Inertia::render('Message', [
            'tone' => 'denied',
            'heading' => $error ? 'We couldn’t open your booking' : 'Use your booking link',
            'message' => $error ?? 'Please use the link in the confirmation SMS or Email to access this portal',
            'note' => 'Access to the Portal is closed after your game.',
        ]);
    }

    /**
     * Leave the current group on a shared on-site device.
     */
    public function logout(): RedirectResponse
    {
        $this->portal->signOut();

        return redirect()->route('groups.index');
    }

    private function signInWithLink(string $value): RedirectResponse
    {
        try {
            $key = UserKey::fromString($value);
            $this->repository->authorize($key);
        } catch (PortalAccessDenied $exception) {
            $this->portal->signOut();

            return redirect()->route('portal.entry')->with('portal_error', $exception->getMessage());
        }

        // Opening the link sent after registering verifies the registration.
        if ($key->hasRegistration()) {
            try {
                $this->repository->confirmRegistration($key->registrationId);
            } catch (HapiException) {
                // The registration may already be confirmed; carry on.
            }
        }

        $this->portal->signIn($key);

        return redirect()->route('portal.entry');
    }

    private function redirectToProgress(): RedirectResponse
    {
        $key = $this->portal->key();
        $registration = $this->portal->registration();

        if ($registration === null && $key?->hasRegistration()) {
            // The registration was removed from the booking; fall back to the group key.
            $this->portal->signIn($key->withRegistration(null));
        }

        if ($registration === null) {
            return redirect()->route('dashboard');
        }

        return redirect()->route($registration->isComplete() ? 'confirmation' : 'verify');
    }
}
