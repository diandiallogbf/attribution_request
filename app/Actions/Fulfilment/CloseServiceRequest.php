<?php

namespace App\Actions\Fulfilment;

use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\RequestMilestone;
use App\Services\RequestFormPdf;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Étape 06 — Clôture : la demande est close et la fiche officielle (PDF, avec l'historique des
 * validations horodatées) est archivée avec son empreinte SHA-256. Pas de contrôle de rôle ici :
 * les points d'entrée (ConfirmReceipt, TransmitAndArchive, FulfilTelephonyRequest) s'en chargent.
 */
final readonly class CloseServiceRequest
{
    public function __construct(private RequestFormPdf $pdf) {}

    public function handle(ServiceRequest $request, ?User $actor, ?string $comment = null): ServiceRequest
    {
        $request = DB::transaction(function () use ($request, $actor, $comment): ServiceRequest {
            $request = ServiceRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            $request->transitionTo(RequestStatus::Closed, $actor, RequestEventType::Closed, $comment);

            return $request;
        });

        try {
            $this->pdf->archive($request, $actor);
        } catch (Throwable $exception) {
            // La clôture reste acquise ; la fiche pourra être régénérée depuis le back-office.
            report($exception);
        }

        $request->loadMissing(['requester', 'beneficiary']);
        $recipients = collect([$request->requester, $request->beneficiary])->filter()->unique('id');

        foreach ($recipients as $recipient) {
            $recipient->notify(new RequestMilestone(
                $request,
                __('Demande clôturée'),
                __('La demande :reference (:subject) est terminée. La fiche officielle a été archivée.', [
                    'reference' => $request->displayReference(),
                    'subject' => $request->subjectLabel(),
                ]),
                __('Voir la demande'),
            ));
        }

        return $request;
    }
}
