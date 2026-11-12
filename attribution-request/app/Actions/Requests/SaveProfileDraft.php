<?php

namespace App\Actions\Requests;

use App\Data\ProfileRequestData;
use App\Exceptions\Domain\InvalidTransitionException;
use App\Exceptions\Domain\UnauthorizedActionException;
use App\Models\Application;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Enregistre le contenu du brouillon de fiche profil à chaque étape de l'assistant. */
final class SaveProfileDraft
{
    public function handle(ServiceRequest $request, User $actor, ProfileRequestData $data): ServiceRequest
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
            $detail = $request->profileDetail()->updateOrCreate([], $data->attributes());

            // Seules les applications actives du référentiel sont acceptées.
            $applicationIds = $data->action->requiresAccessDetails()
                ? Application::query()->whereKey($data->applicationIds)->where('is_active', true)->pluck('id')->all()
                : [];
            $detail->applications()->sync($applicationIds);

            if ($data->action->concernsNewcomer()) {
                $request->site_id = $data->siteId;
            }

            $request->touch();

            return $request->refresh();
        });
    }
}
