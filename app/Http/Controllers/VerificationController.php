<?php

namespace App\Http\Controllers;

use App\Exceptions\Hapi\HapiException;
use App\Http\Requests\AcceptDeclarationsRequest;
use App\Portal\PortalContext;
use App\Portal\PortalRepository;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * For registrations created elsewhere (e.g. by staff) that still need the
 * indemnity statement and safety brief accepted.
 */
class VerificationController extends Controller
{
    public function __construct(private readonly PortalContext $portal) {}

    public function show(): Response|RedirectResponse
    {
        if ($this->portal->registration()->hasAcceptedDeclarations()) {
            return redirect()->route('confirmation');
        }

        $links = $this->portal->links();

        return Inertia::render('Verify', [
            'player' => $this->portal->registration()->firstName(),
            'onSite' => $this->portal->isInternal(),
            'safetyVideo' => ['embed' => $links->safetyVideoEmbedUrl(), 'url' => $links->safetyVideoUrl()],
            'consents' => [
                'accept_terms' => ['label' => __('consents.label.accept_terms'), 'link' => RegistrationController::link('rules', $links)],
                'safety_brief' => ['label' => __('consents.label.safety_brief'), 'link' => RegistrationController::link('safety_video', $links)],
            ],
        ]);
    }

    public function store(AcceptDeclarationsRequest $request, PortalRepository $repository): RedirectResponse
    {
        try {
            $repository->acceptDeclarations($this->portal->registration()->id());
        } catch (HapiException) {
            return back()->with('error', 'Sorry, something went wrong. Please try again.');
        }

        return redirect()->route('confirmation');
    }
}
