<?php

namespace App\Portal;

use Illuminate\Support\Str;

/**
 * Shapes bookings and registrations into the props the pages receive.
 * Dates are formatted here, in the site's timezone, so a visitor's own
 * device timezone never shifts their game time.
 */
class BookingPresenter
{
    /**
     * @return array{id: int, party: string, organiser: string, group: ?string, site: string, venue: string, paxBooked: int, registeredCount: int, gameDate: array{iso: string, day: string, date: string, short: string, time: string}}
     */
    public static function booking(Booking $booking): array
    {
        $gameDate = $booking->gameDate();

        return [
            'id' => $booking->id(),
            'party' => $booking->partyName(),
            'organiser' => $booking->organiserName(),
            'group' => $booking->group(),
            'site' => $booking->siteName(),
            'venue' => config('regos.brand.name').' '.$booking->siteName(),
            'paxBooked' => $booking->paxBooked(),
            'registeredCount' => count($booking->registrations()),
            'gameDate' => [
                'iso' => $gameDate->toIso8601String(),
                'day' => $gameDate->format('l'),
                'date' => $gameDate->format('jS F Y'),
                'short' => $gameDate->format('D j M Y'),
                'time' => $gameDate->format('g:i a'),
            ],
        ];
    }

    /**
     * @return array{id: int, name: string, firstName: string, initials: string, confirmed: bool, declarationsAccepted: bool, complete: bool}|null
     */
    public static function registration(?Registration $registration): ?array
    {
        if ($registration === null) {
            return null;
        }

        return [
            'id' => $registration->id(),
            'name' => $registration->fullName(),
            'firstName' => $registration->firstName(),
            'initials' => self::initials($registration->firstName(), $registration->lastName()),
            'confirmed' => $registration->isConfirmed(),
            'declarationsAccepted' => $registration->hasAcceptedDeclarations(),
            'complete' => $registration->isComplete(),
        ];
    }

    public static function initials(string $first, string $last): string
    {
        return Str::upper(Str::substr(trim($first), 0, 1).Str::substr(trim($last), 0, 1)) ?: '?';
    }
}
