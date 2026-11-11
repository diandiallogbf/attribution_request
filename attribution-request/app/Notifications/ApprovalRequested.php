<?php

namespace App\Notifications;

use App\Models\RequestApproval;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/** Une validation vous attend (manager / directeur nominatif, ou tous les membres du rôle DSI). */
class ApprovalRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public RequestApproval $approval, public bool $reminder = false)
    {
        $this->afterCommit();
    }

    public static function dispatchTo(RequestApproval $approval, bool $reminder = false): void
    {
        $recipients = $approval->assigned_to_id
            ? User::query()->whereKey($approval->assigned_to_id)->active()->get()
            : User::role($approval->assigned_role)->active()->get();

        NotificationFacade::send($recipients, new self($approval, $reminder));
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approval = $this->approval->loadMissing('direction');
        $request = $approval->serviceRequest->loadMissing(['beneficiary', 'requester', 'phoneDetail', 'simDetail', 'profileDetail']);

        return (new MailMessage)
            ->subject(__(':prefix :reference -:subject pour :name', [
                'prefix' => $this->reminder ? __('[Relance]') : __('[À valider]'),
                'reference' => $request->displayReference(),
                'subject' => $request->subjectLabel(),
                'name' => $request->beneficiaryName(),
            ]))
            ->greeting(__('Bonjour :name,', ['name' => $notifiable->first_name]))
            ->line($this->reminder
                ? __('Cette demande attend toujours votre décision.')
                : __(':requester a soumis une demande « :subject » pour :beneficiary.', [
                    'requester' => $request->requester->name,
                    'subject' => $request->subjectLabel(),
                    'beneficiary' => $request->beneficiaryName(),
                ]))
            ->line(__('Étape attendue : :step.', ['step' => $approval->label()]))
            ->action(__('Examiner la demande'), route('requests.show', $request))
            ->salutation(__('Portail des demandes IT -AGL Guinée'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $request = $this->approval->serviceRequest;

        return [
            'kind' => 'approval_requested',
            'request_uuid' => $request->uuid,
            'reference' => $request->displayReference(),
            'title' => $this->reminder ? __('Relance : validation attendue') : __('Validation demandée'),
            'body' => __(':reference -:step', ['reference' => $request->displayReference(), 'step' => $this->approval->label()]),
        ];
    }
}
