<?php

namespace App\Notifications;

use App\Enums\ApprovalDecision;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Informe le demandeur d'une décision (validée, renvoyée pour correction, rejetée). */
class RequestDecided extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ServiceRequest $request,
        public ApprovalDecision $decision,
        public User $decidedBy,
        public ?string $comment = null,
    ) {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__(':reference — :decision', ['reference' => $this->request->displayReference(), 'decision' => $this->decision->getLabel()]))
            ->greeting(__('Bonjour :name,', ['name' => $notifiable->first_name]))
            ->line(__('Votre demande :reference a été :decision par :by.', [
                'reference' => $this->request->displayReference(),
                'decision' => mb_strtolower($this->decision->getLabel()),
                'by' => $this->decidedBy->name,
            ]));

        if ($this->comment) {
            $mail->line(__('Commentaire : « :comment »', ['comment' => $this->comment]));
        }

        if ($this->decision === ApprovalDecision::Returned) {
            $mail->line(__('Corrigez-la puis soumettez-la à nouveau.'));
        }

        return $mail
            ->action(__('Voir la demande'), route('requests.show', $this->request))
            ->salutation(__('Portail des demandes IT — AGL Guinée'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'request_decided',
            'request_uuid' => $this->request->uuid,
            'reference' => $this->request->displayReference(),
            'decision' => $this->decision->value,
            'title' => $this->decision->getLabel(),
            'body' => __(':reference par :by', ['reference' => $this->request->displayReference(), 'by' => $this->decidedBy->name]),
        ];
    }
}
