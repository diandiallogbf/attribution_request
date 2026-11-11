<?php

namespace App\Actions\Fulfilment;

use App\Actions\Fulfilment\Concerns\EnsuresRole;
use App\Data\ProfileExecutionData;
use App\Enums\AttachmentKind;
use App\Enums\ProfileAction;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Enums\Role;
use App\Exceptions\Domain\FulfilmentException;
use App\Exceptions\Domain\InvalidTransitionException;
use App\Models\Application;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\RequestMilestone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Fiche profil -exécution par l'IT : comptes et accès ouverts (ou fermés), preuve d'exécution
 * (ticket / capture). Le compte portail d'un nouvel arrivant peut être créé dans la foulée.
 * La fiche part ensuite chez l'assistante pour transmission et archivage.
 */
final class ExecuteProfileRequest
{
    use EnsuresRole;

    public function handle(ServiceRequest $request, User $actor, ProfileExecutionData $data): ServiceRequest
    {
        $this->ensureRole($actor, Role::fulfilment(), __('exécuter une fiche profil'));

        $request = DB::transaction(function () use ($request, $actor, $data): ServiceRequest {
            $request = ServiceRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            $request->load(['beneficiary', 'profileDetail.applications']);
            $detail = $request->profileDetail;

            if ($request->type !== RequestType::UserProfile || $detail === null || $request->status !== RequestStatus::Approved) {
                throw InvalidTransitionException::between($request->displayReference(), $request->status, RequestStatus::Executed);
            }

            $missing = $detail->applications->pluck('id')->diff($data->executedApplicationIds);
            if ($missing->isNotEmpty()) {
                throw FulfilmentException::missing(__('accès non traités : :apps', [
                    'apps' => Application::query()->whereKey($missing)->pluck('name')->join(', '),
                ]));
            }

            foreach ($detail->applications as $application) {
                $detail->applications()->updateExistingPivot($application->id, ['executed_at' => now()]);
            }

            if ($detail->action === ProfileAction::Creation && $data->createPortalAccount) {
                $user = $this->createPortalAccount($request, $data->accountEmail);
                $request->beneficiary_id = $user->id;
                $detail->created_user_id = $user->id;
            }

            if ($detail->action === ProfileAction::Deletion && $data->deactivatePortalAccount && $request->beneficiary) {
                $request->beneficiary->update(['is_active' => false]);
            }

            $detail->execution_ticket = $data->ticket;
            $detail->save();

            if ($data->proofPath !== null) {
                $disk = Storage::disk('local');
                $request->attachments()->create([
                    'kind' => AttachmentKind::ExecutionProof,
                    'disk' => 'local',
                    'path' => $data->proofPath,
                    'original_name' => $data->proofOriginalName ?? basename($data->proofPath),
                    'mime_type' => $disk->mimeType($data->proofPath) ?: 'application/octet-stream',
                    'size' => $disk->size($data->proofPath),
                    'uploaded_by_id' => $actor->id,
                ]);
            }

            $request->fill([
                'executed_at' => now(),
                'executed_by_id' => $actor->id,
                'execution_notes' => $data->notes,
            ])->save();

            $request->transitionTo(RequestStatus::Executed, $actor, RequestEventType::Executed, $data->ticket ? __('Ticket :ticket', ['ticket' => $data->ticket]) : null);

            return $request;
        });

        foreach (User::role(Role::Assistant->value)->active()->get() as $assistant) {
            $assistant->notify(new RequestMilestone(
                $request,
                __('Fiche à transmettre'),
                __('La fiche :reference (:subject pour :name) a été exécutée par l\'IT : transmettez-la et archivez-la.', [
                    'reference' => $request->displayReference(),
                    'subject' => $request->subjectLabel(),
                    'name' => $request->beneficiaryName(),
                ]),
                __('Ouvrir la fiche'),
                url('/admin/demandes/'.$request->uuid),
            ));
        }

        return $request;
    }

    private function createPortalAccount(ServiceRequest $request, ?string $email): User
    {
        $detail = $request->profileDetail;
        $email = Str::lower(trim((string) $email));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['account_email' => __('Saisissez l\'adresse e-mail créée pour le collaborateur.')]);
        }

        if (User::query()->withTrashed()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['account_email' => __('Un compte existe déjà avec cette adresse e-mail.')]);
        }

        return User::create([
            'matricule' => $detail->matricule,
            'first_name' => $detail->first_name,
            'last_name' => $detail->last_name,
            'email' => $email,
            'password' => null, // il choisira son mot de passe via « Mot de passe oublié » (puis SSO Microsoft)
            'job_title' => $detail->job_title,
            'category' => $detail->category,
            'department_id' => $detail->department_id,
            'site_id' => $detail->site_id,
            'manager_id' => $detail->manager_id,
            'is_active' => true,
        ]);
    }
}
