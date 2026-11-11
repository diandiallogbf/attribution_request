<?php

namespace App\Services;

use App\Enums\RequestType;
use App\ValueObjects\RequestReference;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Attribue la prochaine référence annuelle (TEL-2026-00001, TEL-2026-00002…).
 * La ligne de compteur est verrouillée (SELECT … FOR UPDATE) : deux soumissions
 * simultanées ne peuvent pas obtenir le même numéro.
 */
final class RequestReferenceGenerator
{
    public function next(RequestType $type, ?CarbonInterface $at = null): RequestReference
    {
        $year = ($at ?? now())->year;
        $prefix = $type->referencePrefix();

        return DB::transaction(function () use ($type, $year, $prefix): RequestReference {
            $sequence = DB::table('request_sequences')
                ->where('prefix', $prefix)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                try {
                    DB::table('request_sequences')->insert(['prefix' => $prefix, 'year' => $year, 'last_number' => 1]);

                    return RequestReference::make($type, $year, 1);
                } catch (UniqueConstraintViolationException) {
                    // Un autre processus vient de créer le compteur : on retombe sur l'incrément.
                }
            }

            DB::table('request_sequences')
                ->where('prefix', $prefix)
                ->where('year', $year)
                ->increment('last_number');

            $number = (int) DB::table('request_sequences')
                ->where('prefix', $prefix)
                ->where('year', $year)
                ->value('last_number');

            return RequestReference::make($type, $year, $number);
        });
    }
}
