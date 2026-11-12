<?php

namespace App\Notifications;

use App\Models\ServiceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Étape franchie : matériel prêt, fiche à transmettre, demande clôturée… */
class RequestMilestone extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ServiceRequest $request,
        public string $title,
        public string $body,
        public string $actionLabel,
        public ?string $url = null,
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
        return (new MailMessage)
            ->subject(__(':reference — :title', ['reference' => $this->request->displayReference(), 'title' => $this->title]))
            ->greeting(__('Bonjour :name,', ['name' => $notifiable->first_name]))
            ->line($this->body)
            ->action($this->actionLabel, $this->url ?? route('requests.show', $this->request))
            ->salutation(__('Portail des demandes IT — AGL Guinée'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'milestone',
            'request_uuid' => $this->request->uuid,
            'reference' => $this->request->displayReference(),
            'title' => $this->title,
            'body' => $this->body,
        ];
    }
}
