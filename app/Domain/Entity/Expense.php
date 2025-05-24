<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use DateTimeImmutable;

final class Expense
{
    public function __construct(
        public ?int $id,
        public int $userId,
        public DateTimeImmutable $date,
        public string $category,
        public int $amountCents,
        public string $description,
    ) {}

    public function getAmount(): float
    {
        return $this->amountCents / 100;
    }

    public function setAmount(float $amount): void
    {
        $this->amountCents = (int) round($amount * 100);
    }

    public function getFormattedAmount(string $currency = '€'): string
    {
        return number_format($this->getAmount(), 2) . ' ' . $currency;
    }
}