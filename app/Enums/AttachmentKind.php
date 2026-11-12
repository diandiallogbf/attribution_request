<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasLabel;

enum AttachmentKind: string implements HasLabel
{
    use EnumHelpers;

    case PurchaseOrder = 'purchase_order';
    case RequestForm = 'request_form';
    case SignedReceipt = 'signed_receipt';
    case ExecutionProof = 'execution_proof';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::PurchaseOrder => __('Bon de commande'),
            self::RequestForm => __('Fiche officielle (PDF)'),
            self::SignedReceipt => __('Accusé de réception signé'),
            self::ExecutionProof => __('Capture / ticket d\'exécution'),
            self::Other => __('Autre document'),
        };
    }
}
