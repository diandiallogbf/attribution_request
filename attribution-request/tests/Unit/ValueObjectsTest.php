<?php

use App\Enums\RequestType;
use App\Exceptions\Domain\InvalidValueException;
use App\Support\Luhn;
use App\ValueObjects\Iccid;
use App\ValueObjects\Imei;
use App\ValueObjects\Matricule;
use App\ValueObjects\MoneyGnf;
use App\ValueObjects\PhoneNumber;
use App\ValueObjects\RequestReference;

describe('Matricule', function () {
    it('accepte exactement 5 chiffres et conserve les zéros de tête', function () {
        expect(Matricule::from('04217')->toStorage())->toBe('04217')
            ->and(Matricule::from(' 12345 ')->toStorage())->toBe('12345');
    });

    it('refuse tout autre format', function (string $value) {
        Matricule::from($value);
    })->throws(InvalidValueException::class)->with(['1234', '123456', 'A1234', '']);

    it('compare par valeur', function () {
        expect(Matricule::from('00001')->equals(Matricule::from('00001')))->toBeTrue();
    });
});

describe('PhoneNumber', function () {
    it('normalise les écritures courantes au format E.164', function (string $input) {
        expect(PhoneNumber::from($input)->toStorage())->toBe('+224622123456');
    })->with(['622123456', '622 12 34 56', '+224 622-12-34-56', '00224622123456', '224622123456']);

    it('formate pour l\'affichage', function () {
        $number = PhoneNumber::from('622123456');

        expect($number->national())->toBe('622 12 34 56')
            ->and($number->international())->toBe('+224 622 12 34 56');
    });

    it('refuse un numéro non mobile ou de mauvaise longueur', function (string $value) {
        PhoneNumber::from($value);
    })->throws(InvalidValueException::class)->with(['30412345', '722123456', '62212345', '+33612345678']);
});

describe('Imei', function () {
    it('valide la clé de Luhn', function () {
        expect(Imei::from('490154203237518')->formatted())->toBe('49-015420-323751-8');
    });

    it('refuse une clé de contrôle fausse', function () {
        Imei::from('490154203237519');
    })->throws(InvalidValueException::class);
});

describe('Iccid', function () {
    it('accepte un ICCID valide', function () {
        $iccid = Luhn::complete('892240112345678901');

        expect(Iccid::from($iccid)->toStorage())->toBe($iccid);
    });

    it('refuse un ICCID qui ne commence pas par 89', function () {
        Iccid::from(Luhn::complete('123456789012345678'));
    })->throws(InvalidValueException::class);
});

describe('MoneyGnf', function () {
    it('lit les montants saisis à la française', function (string $input, int $expected) {
        expect(MoneyGnf::from($input)->amount)->toBe($expected);
    })->with([
        ['300000', 300_000],
        ['300 000', 300_000],
        ['1.000.000 GNF', 1_000_000],
    ]);

    it('formate avec espaces insécables', function () {
        expect(MoneyGnf::from(1_500_000)->format())->toBe("1\u{202F}500\u{202F}000\u{00A0}GNF");
    });

    it('refuse les décimales et les négatifs', function (string|int $value) {
        MoneyGnf::from($value);
    })->throws(InvalidValueException::class)->with(['12,50', -5]);

    it('compare les montants', function () {
        expect(MoneyGnf::from(600_000)->isGreaterThan(MoneyGnf::from(250_000)))->toBeTrue();
    });
});

describe('RequestReference', function () {
    it('fait l\'aller-retour chaîne ↔ objet', function () {
        $reference = RequestReference::make(RequestType::Telephony, 2026, 42);

        expect($reference->toStorage())->toBe('TEL-2026-00042')
            ->and(RequestReference::from('TEL-2026-00042')->equals($reference))->toBeTrue();
    });

    it('refuse un préfixe inconnu', function () {
        RequestReference::from('XYZ-2026-00001');
    })->throws(InvalidValueException::class);
});
