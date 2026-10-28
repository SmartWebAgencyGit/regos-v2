<?php

namespace App\Http\Controllers;

use App\Portal\PortalContext;
use Inertia\Inertia;
use Inertia\Response;

class RulesController extends Controller
{
    /**
     * The indemnity statement / terms for the booking's country.
     */
    public function __invoke(PortalContext $portal): Response
    {
        $variant = $portal->links()->rulesVariant();

        $sections = $variant === 'au'
            ? [
                ['title' => 'Playing Rules', 'items' => __('rules.playing_rules')],
                ['title' => 'Acknowledgement and Assumption of Risk', 'paragraphs' => [__('rules.risk_warning'), 'I ACKNOWLEDGE AND UNDERSTAND THAT:'], 'items' => __('rules.risks')],
                ['title' => 'Release', 'paragraphs' => [__('rules.release_intro'), __('rules.release')]],
            ]
            : [
                ['title' => 'Terms & Conditions', 'items' => __('rules.uk_terms')],
            ];

        return Inertia::render('Rules', ['variant' => $variant, 'sections' => $sections]);
    }
}
