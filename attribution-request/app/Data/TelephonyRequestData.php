<?php

namespace App\Data;

use App\Enums\LineOption;
use App\Enums\PhoneReason;
use App\Enums\PhoneType;
use App\Enums\PlanType;
use App\Enums\SimAction;
use App\ValueObjects\MoneyGnf;
use App\ValueObjects\PhoneNumber;

/**
 * Contenu d'une demande Téléphonie, déjà validé et typé (enums + value objects).
 * Construit par l'assistant Livewire, consommé par les Actions : le domaine ne voit jamais de chaînes brutes.
 */
final readonly class TelephonyRequestData
{
    /**
     * @param  list<LineOption>  $lineOptions
     */
    public function __construct(
        public bool $wantsPhone,
        public bool $wantsSim,
        // Téléphone
        public ?PhoneReason $phoneReason = null,
        public ?PhoneType $phoneType = null,
        public ?string $otherTypeLabel = null,
        public ?string $detailedReason = null,
        public ?int $previousDeviceId = null,
        public ?string $previousDeviceLabel = null,
        public ?string $previousDeviceSerial = null,
        // Ligne SIM
        public ?SimAction $simAction = null,
        public ?PlanType $planType = null,
        public array $lineOptions = [],
        public ?int $existingSimLineId = null,
        public ?PhoneNumber $existingMsisdn = null,
        public ?string $entity = null,
        public ?MoneyGnf $monthlyRecharge = null,
    ) {}

    /** @return array<string, mixed> */
    public function phoneAttributes(): array
    {
        $needsNewDevice = $this->phoneReason?->requiresNewDevice() ?? false;
        $needsPrevious = $this->phoneReason?->requiresPreviousDevice() ?? false;

        return [
            'reason' => $this->phoneReason,
            'requested_phone_type' => $needsNewDevice ? $this->phoneType : null,
            'other_type_label' => $needsNewDevice && $this->phoneType === PhoneType::Other ? $this->otherTypeLabel : null,
            'detailed_reason' => $this->detailedReason,
            'previous_device_id' => $needsPrevious ? $this->previousDeviceId : null,
            'previous_device_label' => $needsPrevious && ! $this->previousDeviceId ? $this->previousDeviceLabel : null,
            'previous_device_serial' => $needsPrevious && ! $this->previousDeviceId ? $this->previousDeviceSerial : null,
        ];
    }

    /** @return array<string, mixed> */
    public function simAttributes(): array
    {
        $needsLine = $this->simAction?->requiresExistingLine() ?? false;
        $needsPlan = $this->simAction?->requiresPlan() ?? false;

        return [
            'action' => $this->simAction,
            'plan_type' => $needsPlan ? $this->planType : null,
            'options' => $needsPlan ? $this->lineOptions : null,
            'existing_sim_line_id' => $needsLine ? $this->existingSimLineId : null,
            'existing_msisdn' => $needsLine && ! $this->existingSimLineId ? $this->existingMsisdn : null,
            'entity' => $this->entity ?: null,
            'requested_monthly_recharge_gnf' => $needsPlan ? $this->monthlyRecharge : null,
        ];
    }
}
