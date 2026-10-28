<?php

namespace App\Portal;

/**
 * Country- and site-specific resources for a booking (safety brief video,
 * information pack, itinerary, map, ...), resolved from config/regos.php.
 */
class BookingLinks
{
    /**
     * @param  array<string, mixed>  $config  the "regos" configuration
     */
    public function __construct(
        private readonly Booking $booking,
        private readonly array $config,
    ) {}

    public function safetyVideoUrl(): string
    {
        return 'https://youtu.be/'.$this->safetyVideoId();
    }

    public function safetyVideoEmbedUrl(): string
    {
        return 'https://www.youtube.com/embed/'.$this->safetyVideoId().'?rel=0&controls=1';
    }

    public function parentInfo(): ?string
    {
        return $this->country('parent_info');
    }

    public function privacyPolicy(): ?string
    {
        return $this->country('privacy_policy');
    }

    public function infoPack(): ?string
    {
        return $this->eventLink($this->site('info_pack') ?? $this->country('info_pack'));
    }

    public function itinerary(): ?string
    {
        $itineraries = (array) ($this->countryConfig()['itinerary'] ?? []);

        return $this->eventLink($itineraries[$this->booking->gametypeId()] ?? $itineraries['default'] ?? null);
    }

    /**
     * URL of the site map PDF, when there is one.
     */
    public function map(): ?string
    {
        $path = $this->site('map');

        return $path === null ? null : asset(implode('/', array_map('rawurlencode', explode('/', ltrim($path, '/')))));
    }

    public function directions(): ?string
    {
        return $this->eventLink('https://www.google.com/maps/dir/?api=1&destination='
            .rawurlencode($this->config['brand']['name'].' '.$this->booking->siteName()));
    }

    /**
     * "au" for the playing rules / risk / release wording, otherwise "uk".
     */
    public function rulesVariant(): string
    {
        return $this->country('rules') ?? 'uk';
    }

    public function isRestrictedSite(): bool
    {
        return in_array($this->booking->siteName(), (array) $this->config['restricted_sites'], true);
    }

    /**
     * Resolve the URL for a consent "link" key from config/regos.php.
     */
    public function forConsent(?string $link): ?string
    {
        return match ($link) {
            'safety_video' => $this->safetyVideoUrl(),
            'rules' => route('rules'),
            'parent_info' => $this->parentInfo(),
            default => null,
        };
    }

    private function safetyVideoId(): string
    {
        return $this->country('safety_video') ?? $this->config['brand']['default_safety_video'];
    }

    private function eventLink(?string $url): ?string
    {
        return $this->isRestrictedSite() ? null : $url;
    }

    /**
     * @return array<string, mixed>
     */
    private function countryConfig(): array
    {
        return (array) ($this->config['countries'][$this->booking->countryId()] ?? []);
    }

    private function country(string $key): ?string
    {
        $value = $this->countryConfig()[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function site(string $key): ?string
    {
        $value = $this->config['sites'][$this->booking->countryId()][$this->booking->siteName()][$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
