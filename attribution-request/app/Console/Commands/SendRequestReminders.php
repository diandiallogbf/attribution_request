<?php

namespace App\Console\Commands;

use App\Enums\ApprovalDecision;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Models\RequestApproval;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\ApprovalRequested;
use App\Notifications\RequestMilestone;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Relance quotidienne : validations en attente depuis plus de 48 h, et réceptions non confirmées.
 * Une même validation n'est relancée qu'une fois par 24 h.
 */
#[Signature('requests:send-reminders {--hours=48 : Délai avant relance}')]
#[Description('Relance les valideurs et bénéficiaires en retard')]
class SendRequestReminders extends Command
{
    public function handle(): int
    {
        $threshold = now()->subHours((int) $this->option('hours'));

        $approvals = RequestApproval::query()
            ->where('decision', ApprovalDecision::Pending)
            ->where('created_at', '<', $threshold)
            ->where(fn ($query) => $query->whereNull('reminded_at')->orWhere('reminded_at', '<', now()->subDay()))
            ->with('serviceRequest')
            ->get();

        foreach ($approvals as $approval) {
            ApprovalRequested::dispatchTo($approval, reminder: true);
            $approval->update(['reminded_at' => now()]);
            $approval->serviceRequest->recordEvent(RequestEventType::Reminded, null, __('Relance : :step', ['step' => $approval->step->getLabel()]));
        }

        $receipts = ServiceRequest::query()
            ->where('status', RequestStatus::PendingReceipt)
            ->where('updated_at', '<', $threshold)
            ->with('beneficiary')
            ->get();

        foreach ($receipts as $request) {
            if ($request->beneficiary instanceof User) {
                Notification::send($request->beneficiary, new RequestMilestone(
                    $request,
                    __('Rappel : confirmez la réception'),
                    __('Votre matériel (:subject) vous attend ou vous a été remis : confirmez la réception pour clôturer la demande.', ['subject' => $request->subjectLabel()]),
                    __('Confirmer la réception'),
                ));
            }
        }

        $this->info(sprintf('%d validation(s) et %d réception(s) relancée(s).', $approvals->count(), $receipts->count()));

        return self::SUCCESS;
    }
}
