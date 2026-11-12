<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Filament\Support\Contracts\HasLabel;

enum PhoneType: string implements HasLabel
{
    use EnumHelpers;

    case SamsungBasic = 'samsung_basic';
    case SamsungAndroid = 'samsung_android';
    case Iphone = 'iphone';
    case Tablet = 'tablet';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::SamsungBasic => __('Samsung simple'),
            self::SamsungAndroid => __('Samsung Android'),
            self::Iphone => __('iPhone'),
            self::Tablet => __('Tablette'),
            self::Other => __('Autre'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SamsungBasic => __('Téléphone à touches : appels et SMS.'),
            self::SamsungAndroid => __('Smartphone Android : e-mail, Teams, applications.'),
            self::Iphone => __('Smartphone Apple.'),
            self::Tablet => __('Tablette avec carte SIM.'),
            self::Other => __('Précisez le besoin.'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::SamsungBasic => 'phone',
            self::SamsungAndroid, self::Iphone => 'device-phone-mobile',
            self::Tablet => 'device-tablet',
            self::Other => 'ellipsis-horizontal-circle',
        };
    }
}
