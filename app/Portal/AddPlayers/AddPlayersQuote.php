<?php

namespace App\Portal\AddPlayers;

/**
 * Server-calculated price for extra players. All amounts are minor units.
 *
 * @phpstan-type QuoteLine array{id: int, package_id: int, name: string, unit_price: int, unit_handling_fee: int, booked: int, quantity: int, subtotal: int, handling_fee: int, tax: int, total: int}
 */
final class AddPlayersQuote
{
    /**
     * @param  list<QuoteLine>  $lines
     */
    public function __construct(
        public readonly array $lines,
        public readonly ?string $currency,
        public readonly string $currencySymbol,
    ) {}

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    public function playerCount(): int
    {
        return array_sum(array_column($this->lines, 'quantity'));
    }

    public function subtotal(): int
    {
        return array_sum(array_column($this->lines, 'subtotal'));
    }

    public function handlingFee(): int
    {
        return array_sum(array_column($this->lines, 'handling_fee'));
    }

    public function tax(): int
    {
        return array_sum(array_column($this->lines, 'tax'));
    }

    public function total(): int
    {
        return array_sum(array_column($this->lines, 'total'));
    }

    public function format(int $amount): string
    {
        return $this->currencySymbol.number_format($amount / 100, 2);
    }
}
