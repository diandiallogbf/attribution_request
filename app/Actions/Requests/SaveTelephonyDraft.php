<?php

namespace App\Actions\Requests;

use App\Data\TelephonyRequestData;
use App\Exceptions\Domain\InvalidTransitionException;
use App\Exceptions\Domain\UnauthorizedActionException;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Enregistre le contenu du brouillon à chaque étape de l'assistant (rien n'est perdu si l'utilisateur s'interrompt). */
final class SaveTelephonyDraft
{
    public function handle(ServiceRequest $request, User $actor, TelephonyRequestData $data): ServiceRequest
    {
        if ($request->requester_id !== $actor->id) {
            throw UnauthorizedActionException::notTheApprover($request->displayReference());
        }

        if (! $request->status->isEditableByRequester()) {
            throw new InvalidTransitionException(
                __('La demande :reference est « :status » : elle ne peut plus être modifiée.', [
                    'reference' => $request->displayReference(),
                    'status' => $request->status->getLabel(),
                ]),
                ['reference' => $request->displayReference(), 'status' => $request->status->value],
            );
        }

        return DB::transaction(function () use ($request, $data): ServiceRequest {
            if ($data->wantsPhone) {
                $request->phoneDetail()->updateOrCreate([], $data->phoneAttributes());
            } else {
                $request->phoneDetail()->delete();
            }

            if ($data->wantsSim) {
                $request->simDetail()->updateOrCreate([], $data->simAttributes());
            } else {
                $request->simDetail()->delete();
            }

            $request->touch();

            return $request->refresh();
        });
    }
}
