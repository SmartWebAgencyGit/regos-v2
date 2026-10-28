<?php

namespace App\Portal\AddPlayers;

use App\Portal\Booking;

/**
 * Prices extra players from the booking's own packages in HAPI.
 *
 * Matches the legacy charge: the country's handling-fee rate applies to
 * every line when any selected package carries a handling fee, and
 * countries with a sales tax (Canada) add it on top for packages that are
 * not tax-inclusive. Unlike the legacy portal, nothing is taken from the
 * browser except the quantities.
 *
 * @phpstan-import-type QuoteLine from AddPlayersQuote
 */
class AddPlayersPricing
{
    private const CORE_PACKAGE_TYPE = 1;

    /**
     * @param  array<string, mixed>  $config  the "regos" configuration
     */
    public function __construct(private readonly array $config) {}

    /**
     * Player packages on the booking that extra players can be added to.
     *
     * @return list<array{id: int, package_id: int, name: string, unit_price: int, unit_handling_fee: int, is_inclusive: bool, booked: int, max: int}>
     */
    public function packages(Booking $booking): array
    {
        $packages = [];

        foreach ($booking->packages() as $package) {
            if ((int) ($package['package_type_id'] ?? 0) !== self::CORE_PACKAGE_TYPE) {
                continue;
            }

            $packageId = (int) ($package['package_id'] ?? 0);

            $packages[] = [
                'id' => (int) $package['id'],
                'package_id' => $packageId,
                'name' => (string) ($package['name'] ?? ''),
                'unit_price' => (int) ($package['unit_price'] ?? 0),
                'unit_handling_fee' => (int) ($package['unit_handling_fee'] ?? 0),
                'is_inclusive' => ! empty($package['is_inclusive']),
                'booked' => (int) ($package['qty_booked'] ?? 0),
                'max' => $this->maxQuantity($packageId),
            ];
        }

        return $packages;
    }

    /**
     * @param  array<int|string, int|string>  $quantities  booking package id => extra players
     */
    public function quote(Booking $booking, array $quantities): AddPlayersQuote
    {
        $country = (array) ($this->config['countries'][$booking->countryId()] ?? []);
        $feeRate = (float) ($country['handling_fee_rate'] ?? 0);
        $taxRate = (float) ($country['tax_rate'] ?? 0);

        $selected = [];

        foreach ($this->packages($booking) as $package) {
            $quantity = min((int) ($quantities[$package['id']] ?? 0), $package['max']);

            if ($quantity > 0) {
                $selected[] = [$package, $quantity];
            }
        }

        $chargesHandlingFee = collect($selected)->contains(fn (array $line) => $line[0]['unit_handling_fee'] > 0);

        $lines = array_map(function (array $line) use ($chargesHandlingFee, $feeRate, $taxRate): array {
            [$package, $quantity] = $line;

            $subtotal = $package['unit_price'] * $quantity;
            $handlingFee = $chargesHandlingFee ? (int) round($subtotal * $feeRate) : 0;
            $tax = $taxRate > 0 && ! $package['is_inclusive']
                ? (int) round(($subtotal + $handlingFee) * (1 + $taxRate)) - ($subtotal + $handlingFee)
                : 0;

            return [
                'id' => $package['id'],
                'package_id' => $package['package_id'],
                'name' => $package['name'],
                'unit_price' => $package['unit_price'],
                'unit_handling_fee' => $package['unit_handling_fee'],
                'booked' => $package['booked'],
                'quantity' => $quantity,
                'subtotal' => $subtotal,
                'handling_fee' => $handlingFee,
                'tax' => $tax,
                'total' => $subtotal + $handlingFee + $tax,
            ];
        }, $selected);

        return new AddPlayersQuote($lines, $booking->currencyCode(), $booking->currencySymbol());
    }

    public function maxQuantity(int $packageId): int
    {
        return (int) ($this->config['add_players']['package_limits'][$packageId] ?? $this->config['add_players']['default_max_quantity']);
    }
}
