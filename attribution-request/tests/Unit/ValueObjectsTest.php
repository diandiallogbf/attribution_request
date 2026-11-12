<?php

use App\Exceptions\Domain\InvalidValueException;
use App\Support\Luhn;
use App\ValueObjects\Iccid;
use App\ValueObjects\Imei;
use App\ValueObjects\Matricule;
use App\ValueObjects\MoneyGnf;
use App\ValueObjects\PhoneNumber;
use App\ValueObjects\RequestReference;

describe('Matricule', function () {
    it('accepte exactement 4 chiffres et conserve les zéros de tête', function () {
        expect(Matricule::from('0421')->toStorage())->toBe('0421')
            ->and(Matricule::from(' 1234 ')->toStorage())->toBe('1234');
    });

    it('refuse tout autre format', function (string $value) {
        Matricule::from($value);
    })->throws(InvalidValueException::class)->with(['123', '12345', 'A123', '']);

    it('compare par valeur', function () {
        expect(Matricule::from('0001')->equals(Matricule::from('0001')))->toBeTrue();
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
    it('suit le format AA MM XXX : 2611541 = novembre 2026, n° 541', function () {
        $reference = RequestReference::make(2026, 11, 541);

        expect($reference->toStorage())->toBe('2611541')
            ->and($reference->spaced())->toBe('26 11 541')
            ->and(RequestReference::from('26 11 541')->equals($reference))->toBeTrue()
            ->and(RequestReference::make(2027, 1, 7)->toStorage())->toBe('2701007');
    });

    it('s\'allonge au-delà de 999 fiches dans le mois au lieu de bloquer', function () {
        expect(RequestReference::make(2026, 11, 1000)->toStorage())->toBe('26111000')
            ->and(RequestReference::from('26111000')->sequence)->toBe(1000);
    });

    it('refuse un format ou un mois invalide', function (string $value) {
        RequestReference::from($value);
    })->throws(InvalidValueException::class)->with(['261354', '2613001', '2600001', '2611000', 'TEL-2026-00001']);
});
