<?php

namespace App\Actions\Requests;

use App\Enums\ProfileAction;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Enums\Role;
use App\Exceptions\Domain\UnauthorizedActionException;
use App\Models\Department;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\ApproverResolver;
use Illuminate\Support\Facades\DB;

/** Étape 01 — Initiation d'une fiche profil utilisateur. */
final readonly class StartProfileDraft
{
    public function __construct(private ApproverResolver $approvers) {}

    public function handle(User $requester, ProfileAction $action, ?User $beneficiary = null): ServiceRequest
    {
        if ($action->concernsNewcomer()) {
            if (! self::canRequestForNewcomers($requester)) {
                throw UnauthorizedActionException::cannotRequestForNewcomer();
            }
            $beneficiary = null;
        } elseif ($beneficiary === null || ! $this->approvers->canRequestFor($requester, $beneficiary)) {
            throw UnauthorizedActionException::notAllowedToRequestFor($beneficiary?->name ?? '—');
        }

        return DB::transaction(function () use ($requester, $action, $beneficiary): ServiceRequest {
            $request = ServiceRequest::create([
                'type' => RequestType::UserProfile,
                'status' => RequestStatus::Draft,
                'requester_id' => $requester->id,
                'beneficiary_id' => $beneficiary?->id,
                'site_id' => $beneficiary?->site_id ?? $requester->site_id,
            ]);

            $request->profileDetail()->create([
                'action' => $action,
                'manager_id' => $action->concernsNewcomer() ? $requester->id : null,
                'department_id' => $action->concernsNewcomer() ? $requester->department_id : null,
                'site_id' => $action->concernsNewcomer() ? $requester->site_id : null,
            ]);

            $request->recordEvent(RequestEventType::Created, $requester);

            return $request;
        });
    }

    /** Managers, chefs de service, directeurs et équipes support peuvent demander l'ouverture d'un compte. */
    public static function canRequestForNewcomers(User $user): bool
    {
        return $user->hasAnyRole([...Role::backOffice(), Role::Director])
            || $user->isManager()
            || Department::query()->where('head_id', $user->id)->exists();
    }
}
