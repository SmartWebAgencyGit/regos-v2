<?php

namespace App\Http\Controllers;

use App\Exceptions\Hapi\HapiException;
use App\Http\Requests\StoreRegistrationRequest;
use App\Portal\BookingLinks;
use App\Portal\BookingPresenter;
use App\Portal\ConsentRules;
use App\Portal\PortalContext;
use App\Portal\PortalRepository;
use App\Portal\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Player registration (and, with ?edit=1, correcting a player's details).
 */
class RegistrationController extends Controller
{
    public function __construct(private readonly PortalContext $portal) {}

    public function create(Request $request, ConsentRules $consentRules, PortalRepository $repository): Response
    {
        $booking = $this->portal->booking();
        $links = $this->portal->links();
        $registration = $request->boolean('edit') ? $this->portal->registration() : null;
        $today = Date::now($booking->timezone());

        if ($registration !== null) {
            try {
                $registration = $repository->registration($registration->id());
            } catch (HapiException) {
                // The booking still has their name and contact details.
            }
        }

        return Inertia::render('Register', [
            'booking' => BookingPresenter::booking($booking),
            'editing' => $registration !== null,
            'player' => [
                'name_first' => $registration?->firstName() ?? '',
                'name_last' => $registration?->lastName() ?? '',
                'date_of_birth' => $registration?->dateOfBirth() ?? '',
                'mobile' => $registration?->mobile() ?? '',
                'email' => $registration?->email() ?? '',
                'postcode' => $registration?->postcode() ?? '',
            ],
            'consents' => array_map(fn (array $consent) => [
                'input' => $consent['input'],
                'label' => __('consents.label.'.$consent['label']),
                'link' => self::link($consent['link'], $links),
                'minAge' => $consent['min_age'],
                'maxAge' => $consent['max_age'],
                'required' => $consent['required'],
            ], $consentRules->forBooking($booking)),
            'guardianConsent' => __('consents.label.guardian_permission'),
            'guardianAge' => (int) config('regos.registration.guardian_age'),
            'safetyVideo' => ['embed' => $links->safetyVideoEmbedUrl(), 'url' => $links->safetyVideoUrl()],
            'privacyPolicy' => $links->privacyPolicy(),
            'onSite' => $this->portal->isInternal(),
            'today' => $today->format('Y-m-d'),
            'latestDateOfBirth' => $today
                ->subYears($booking->minimumAge() !== null ? (int) config('regos.registration.youngest_player_age') : 0)
                ->format('Y-m-d'),
        ]);
    }

    public function store(StoreRegistrationRequest $request, RegistrationService $registrations, PortalRepository $repository): RedirectResponse
    {
        $booking = $this->portal->booking();
        $key = $this->portal->key();
        // HAPI won't let the portal change a player's customer record, so a
        // correction is a new registration (as in the legacy portal), which
        // also sends the verification link to the corrected details.
        $correcting = $request->boolean('editing') && $this->portal->registration() !== null;

        try {
            $registrationId = $registrations->save(
                $booking,
                $request->safe()->only(['name_first', 'name_last', 'date_of_birth', 'mobile', 'email', 'postcode']),
                $request->declarations(),
                $request->ip(),
                $request->userAgent(),
            );
        } catch (HapiException) {
            return back()->with('error', 'Sorry, we could not save your registration. Please try again.');
        }

        $this->portal->signIn($key->withRegistration($registrationId));

        try {
            $repository->acceptDeclarations($registrationId);
        } catch (HapiException $exception) {
            Log::warning('Registration saved but accepting declarations failed', [
                'registration_id' => $registrationId,
                'error' => $exception->getMessage(),
            ]);

            return redirect()->route('verify')->with('warning', 'Please confirm the declarations below to finish registering.');
        }

        return redirect()->route('confirmation')->with('status', $correcting
            ? 'Your details have been updated. We’ve sent a new verification link.'
            : 'You’re registered! Check your phone for your verification link.');
    }

    /**
     * @return array{text: string, href: ?string}|null
     */
    public static function link(?string $link, BookingLinks $links): ?array
    {
        return $link === null ? null : ['text' => __('consents.links.'.$link), 'href' => $links->forConsent($link)];
    }
}
