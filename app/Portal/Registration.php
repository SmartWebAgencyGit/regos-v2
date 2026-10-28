<?php

namespace App\Portal;

use Illuminate\Support\Arr;

/**
 * A player's registration against a booking.
 */
final class Registration
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(private readonly array $data) {}

    public function id(): int
    {
        return (int) Arr::get($this->data, 'id');
    }

    public function firstName(): string
    {
        return (string) Arr::get($this->data, 'customer.name_first', '');
    }

    public function lastName(): string
    {
        return (string) Arr::get($this->data, 'customer.name_last', '');
    }

    public function fullName(): string
    {
        return Booking::formatName($this->firstName(), $this->lastName());
    }

    public function email(): ?string
    {
        return Arr::get($this->data, 'customer.emails.0.value');
    }

    public function mobile(): ?string
    {
        return Arr::get($this->data, 'customer.phones.0.value');
    }

    /**
     * As Y-m-d (HAPI stores a datetime).
     */
    public function dateOfBirth(): ?string
    {
        $date = (string) Arr::get($this->data, 'customer.date_of_birth', '');

        return preg_match('/^\d{4}-\d{2}-\d{2}/', $date, $match) && $match[0] !== '0000-00-00' ? $match[0] : null;
    }

    public function postcode(): ?string
    {
        return Arr::get($this->data, 'customer.address.postcode');
    }

    /**
     * The player clicked the verification link sent by SMS / email.
     */
    public function isConfirmed(): bool
    {
        return (bool) Arr::get($this->data, 'confirmed');
    }

    public function hasAcceptedDeclarations(): bool
    {
        return (bool) Arr::get($this->data, 'accept_terms') && (bool) Arr::get($this->data, 'safety_brief');
    }

    /**
     * Ready to play: verified and declarations accepted.
     */
    public function isComplete(): bool
    {
        return $this->isConfirmed() && $this->hasAcceptedDeclarations();
    }
}
