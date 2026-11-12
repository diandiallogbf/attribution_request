<?php

namespace App\Exceptions\Domain;

final class DeviceUnavailableException extends DomainException
{
    public static function alreadyAssigned(string $label): self
    {
        return new self(
            __(':label est déjà attribué à un autre collaborateur.', ['label' => $label]),
            ['device' => $label],
        );
    }

    public function title(): string
    {
        return __('Équipement indisponible');
    }
}
