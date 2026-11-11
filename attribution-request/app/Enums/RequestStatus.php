<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Cycle de vie d'une demande. Les transitions autorisées sont portées par l'enum :
 * toute tentative hors de ce graphe lève une InvalidTransitionException (cf. ServiceRequest::transitionTo).
 */
enum RequestStatus: string implements HasColor, HasIcon, HasLabel
{
    use EnumHelpers;

    case Draft = 'draft';
    case PendingManager = 'pending_manager';
    case Returned = 'returned';
    case PendingDsi = 'pending_dsi';
    case Approved = 'approved';
    case Ordering = 'ordering';
    case ReadyForAssignment = 'ready_for_assignment';
    case PendingReceipt = 'pending_receipt';
    case Closed = 'closed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => __('Brouillon'),
            self::PendingManager => __('En attente du manager'),
            self::Returned => __('À corriger'),
            self::PendingDsi => __('En attente DSI'),
            self::Approved => __('Approuvée'),
            self::Ordering => __('En commande'),
            self::ReadyForAssignment => __('Prête à attribuer'),
            self::PendingReceipt => __('En attente de réception'),
            self::Closed => __('Clôturée'),
            self::Rejected => __('Rejetée'),
            self::Cancelled => __('Annulée'),
        };
    }

    /** Couleur sémantique, traduite par <x-status-badge> côté portail et par Filament côté back-office. */
    public function getColor(): string
    {
        return match ($this) {
            self::Draft, self::Cancelled => 'gray',
            self::PendingManager, self::PendingDsi => 'warning',
            self::Returned => 'orange',
            self::Approved, self::PendingReceipt => 'info',
            self::Ordering => 'violet',
            self::ReadyForAssignment => 'primary',
            self::Closed => 'success',
            self::Rejected => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'pencil-square',
            self::PendingManager, self::PendingDsi => 'clock',
            self::Returned => 'arrow-uturn-left',
            self::Approved => 'check-badge',
            self::Ordering => 'shopping-cart',
            self::ReadyForAssignment => 'cube',
            self::PendingReceipt => 'hand-raised',
            self::Closed => 'check-circle',
            self::Rejected => 'x-circle',
            self::Cancelled => 'no-symbol',
        };
    }

    public function getIcon(): string
    {
        return 'heroicon-m-'.$this->icon();
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::PendingManager, self::PendingDsi, self::Cancelled],
            self::PendingManager => [self::PendingDsi, self::Returned, self::Rejected, self::Cancelled],
            self::Returned => [self::PendingManager, self::PendingDsi, self::Cancelled],
            self::PendingDsi => [self::Approved, self::Returned, self::Rejected],
            self::Approved => [self::Ordering, self::ReadyForAssignment, self::Closed],
            self::Ordering => [self::ReadyForAssignment],
            self::ReadyForAssignment => [self::PendingReceipt],
            self::PendingReceipt => [self::Closed],
            self::Closed, self::Rejected, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /** Le demandeur peut encore modifier le contenu de sa demande. */
    public function isEditableByRequester(): bool
    {
        return in_array($this, [self::Draft, self::Returned], true);
    }

    public function isAwaitingApproval(): bool
    {
        return in_array($this, [self::PendingManager, self::PendingDsi], true);
    }

    /** Étape (1 à 6) de la fiche officielle à laquelle correspond ce statut, pour la frise de suivi. */
    public function stage(): int
    {
        return match ($this) {
            self::Draft, self::Returned, self::Cancelled => 1,
            self::PendingManager => 2,
            self::PendingDsi, self::Rejected => 3,
            self::Approved, self::Ordering => 4,
            self::ReadyForAssignment, self::PendingReceipt => 5,
            self::Closed => 6,
        };
    }

    /** @return list<self> */
    public static function open(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status) => ! $status->isFinal()));
    }
}
