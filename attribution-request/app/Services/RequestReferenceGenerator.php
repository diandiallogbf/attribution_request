<?php

namespace App\Services;

use App\ValueObjects\RequestReference;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Attribue le prochain N° de fiche du mois (2611001, 2611002…), toutes fiches confondues.
 * La ligne de compteur est verrouillée (SELECT … FOR UPDATE) : deux soumissions
 * simultanées ne peuvent pas obtenir le même numéro.
 */
final class RequestReferenceGenerator
{
    public function next(?CarbonInterface $at = null): RequestReference
    {
        $at ??= now();
        $period = RequestReference::periodOf($at->year, $at->month);

        return DB::transaction(function () use ($at, $period): RequestReference {
            $counter = DB::table('request_sequences')->where('period', $period)->lockForUpdate()->first();

            if ($counter === null) {
                try {
                    DB::table('request_sequences')->insert(['period' => $period, 'last_number' => 1]);

                    return RequestReference::forPeriod($at, 1);
                } catch (UniqueConstraintViolationException) {
                    // Un autre processus vient de créer le compteur : on retombe sur l'incrément.
                }
            }

            DB::table('request_sequences')->where('period', $period)->increment('last_number');

            return RequestReference::forPeriod(
                $at,
                (int) DB::table('request_sequences')->where('period', $period)->value('last_number'),
            );
        });
    }
}
