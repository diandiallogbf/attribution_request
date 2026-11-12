<?php

namespace App\Data;

use App\ValueObjects\MoneyGnf;
use Carbon\CarbonInterface;

/** Saisie comptable d'une commande par la Finance. */
final readonly class OrderAccountingData
{
    public function __construct(
        public ?string $invoiceReference = null,
        public ?CarbonInterface $invoicedAt = null,
        public ?MoneyGnf $invoiceAmount = null,
        public ?string $costCenter = null,
        public ?CarbonInterface $paidAt = null,
        public ?string $notes = null,
    ) {}
}
