<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasLabel;

/** Entrées du journal immuable d'une demande (chronologie + audit). */
enum RequestEventType: string implements HasLabel
{
    use EnumHelpers;

    case Created = 'created';
    case Submitted = 'submitted';
    case Updated = 'updated';
    case Approved = 'approved';
    case Returned = 'returned';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Ordered = 'ordered';
    case Received = 'received';
    case Assigned = 'assigned';
    case ReceiptConfirmed = 'receipt_confirmed';
    case Closed = 'closed';
    case Commented = 'commented';

    public function getLabel(): string
    {
        return match ($this) {
            self::Created => __('Demande créée'),
            self::Submitted => __('Demande soumise'),
            self::Updated => __('Demande modifiée'),
            self::Approved => __('Validée'),
            self::Returned => __('Renvoyée pour correction'),
            self::Rejected => __('Rejetée'),
            self::Cancelled => __('Annulée'),
            self::Ordered => __('Commandée'),
            self::Received => __('Matériel réceptionné'),
            self::Assigned => __('Attribuée'),
            self::ReceiptConfirmed => __('Réception confirmée'),
            self::Closed => __('Clôturée'),
            self::Commented => __('Commentaire'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Created => 'document-plus',
            self::Submitted => 'paper-airplane',
            self::Updated => 'pencil-square',
            self::Approved => 'check',
            self::Returned => 'arrow-uturn-left',
            self::Rejected => 'x-mark',
            self::Cancelled => 'no-symbol',
            self::Ordered => 'shopping-cart',
            self::Received => 'inbox-arrow-down',
            self::Assigned => 'device-phone-mobile',
            self::ReceiptConfirmed => 'hand-thumb-up',
            self::Closed => 'archive-box',
            self::Commented => 'chat-bubble-left-ellipsis',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Approved, self::ReceiptConfirmed, self::Closed => 'success',
            self::Returned => 'orange',
            self::Rejected, self::Cancelled => 'danger',
            self::Ordered, self::Received, self::Assigned => 'violet',
            default => 'gray',
        };
    }
}
