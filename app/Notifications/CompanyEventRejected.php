<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompanyEventRejected extends Notification
{
    use Queueable;

    public $request;

    /**
     * Create a new notification instance.
     */
    public function __construct(\App\Models\CompanyEventRequest $request)
    {
        $this->request = $request;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $eventData = $this->request->event_data;
        $title = $eventData['title'] ?? 'タイトルなし';

        return [
            'request_id' => $this->request->id,
            'title' => $title,
            'message' => "全社カレンダーへの追加申請が却下されました: {$title}",
            'review_comment' => $this->request->review_comment,
            'reviewed_by' => $this->request->reviewed_by,
            'type' => 'company_event_rejected'
        ];
    }
}
