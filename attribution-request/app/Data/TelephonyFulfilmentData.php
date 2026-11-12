<?php

namespace App\Data;

use App\Enums\DeviceStatus;

/** Saisie de l'IT à l'étape 05 « Attribution » (configuration, remise, retour de l'ancien appareil). */
final readonly class TelephonyFulfilmentData
{
    /** Points de configuration vérifiés avant la remise (« Config (MS) du téléphone + puce »). */
    public const CHECKLIST = [
        'microsoft_account' => 'Compte Microsoft 365 configuré',
        'intune' => 'Appareil enrôlé dans Intune',
        'sim_inserted' => 'Puce insérée et testée',
        'business_apps' => 'Applications métier installées',
    ];

    /**
     * @param  array<string, bool>  $setupChecklist
     */
    public function __construct(
        public ?int $deviceId = null,
        public array $setupChecklist = [],
        public ?DeviceStatus $previousDeviceStatus = null,
        public ?string $returnCondition = null,
        public ?int $simLineId = null,
        public ?string $notes = null,
    ) {}
}
