<?php

namespace App\Notifications;

use App\Models\RequestApproval;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/** Une validation vous attend (manager nominatif, ou tous les membres du rôle DSI). */
class ApprovalRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public RequestApproval $approval)
    {
        $this->afterCommit();
    }

    public static function dispatchTo(RequestApproval $approval): void
    {
        $recipients = $approval->assigned_to_id
            ? User::query()->whereKey($approval->assigned_to_id)->active()->get()
            : User::role($approval->assigned_role)->active()->get();

        NotificationFacade::send($recipients, new self($approval));
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->approval->serviceRequest->loadMissing(['beneficiary', 'requester', 'phoneDetail', 'simDetail']);

        return (new MailMessage)
            ->subject(__('[À valider] :reference — :subject pour :name', [
                'reference' => $request->displayReference(),
                'subject' => $request->subjectLabel(),
                'name' => $request->beneficiary->name,
            ]))
            ->greeting(__('Bonjour :name,', ['name' => $notifiable->first_name]))
            ->line(__(':requester a soumis une demande « :subject » pour :beneficiary.', [
                'requester' => $request->requester->name,
                'subject' => $request->subjectLabel(),
                'beneficiary' => $request->beneficiary->name,
            ]))
            ->line(__('Étape attendue : :step.', ['step' => $this->approval->step->getLabel()]))
            ->action(__('Examiner la demande'), route('requests.show', $request))
            ->salutation(__('Portail des demandes IT — AGL Guinée'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $request = $this->approval->serviceRequest;

        return [
            'kind' => 'approval_requested',
            'request_uuid' => $request->uuid,
            'reference' => $request->displayReference(),
            'title' => __('Validation demandée'),
            'body' => __(':reference — :step', ['reference' => $request->displayReference(), 'step' => $this->approval->step->getLabel()]),
        ];
    }
}
