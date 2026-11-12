<?php

namespace App\Actions\Fulfilment;

use App\Actions\Fulfilment\Concerns\EnsuresRole;
use App\Enums\RequestEventType;
use App\Enums\Role;
use App\Exceptions\Domain\FulfilmentException;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\RequestMilestone;
use Illuminate\Support\Facades\DB;

/**
 * Phase commande / exécution : la DSI confie la demande à un membre de son équipe (responsable IT
 * ou technicien). Le délégataire est notifié ; lui seul (avec la DSI et l'admin) peut ensuite
 * commander, réceptionner, attribuer ou exécuter. Une nouvelle délégation réattribue la demande.
 */
final class DelegateFulfilment
{
    use EnsuresRole;

    public function handle(ServiceRequest $request, User $actor, User $delegate, ?string $instructions = null): ServiceRequest
    {
        $this->ensureRole($actor, Role::delegators(), __('déléguer le traitement d\'une demande'));

        if (! $delegate->is_active || ! $delegate->hasAnyRole(Role::delegates())) {
            throw FulfilmentException::invalidDelegate($delegate->name);
        }

        $instructions = filled($instructions) ? trim($instructions) : null;

        $request = DB::transaction(function () use ($request, $actor, $delegate, $instructions): ServiceRequest {
            $request = ServiceRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            $request->load(['handler', 'beneficiary', 'phoneDetail', 'simDetail', 'profileDetail']);

            if (! $request->status->isDelegable()) {
                throw FulfilmentException::notDelegable($request->displayReference(), $request->status->getLabel());
            }

            if ($request->handler_id === $delegate->id) {
                throw FulfilmentException::alreadyHandledBy($request->displayReference(), $delegate->name);
            }

            $previous = $request->handler;

            $request->fill([
                'handler_id' => $delegate->id,
                'delegated_by_id' => $actor->id,
                'delegated_at' => now(),
                'delegation_note' => $instructions,
            ])->save();

            $request->setRelation('handler', $delegate);

            // Le journal (visible du collaborateur) dit qui s'en occupe ; les consignes restent internes.
            $request->recordEvent(
                RequestEventType::Delegated,
                $actor,
                __('Confiée à :name', ['name' => $delegate->name]),
                ['handler' => ['from' => $previous?->name, 'to' => $delegate->name]],
            );

            return $request;
        });

        $delegate->notify(new RequestMilestone(
            $request,
            __('Demande à traiter'),
            __(':by vous confie la commande / l\'exécution de la fiche :reference (:subject pour :name).:instructions', [
                'by' => $actor->name,
                'reference' => $request->displayReference(),
                'subject' => $request->subjectLabel(),
                'name' => $request->beneficiaryName(),
                'instructions' => $instructions ? ' '.__('Consignes : :text', ['text' => $instructions]) : '',
            ]),
            __('Ouvrir la fiche'),
            url('/admin/demandes/'.$request->uuid),
        ));

        return $request;
    }
}
