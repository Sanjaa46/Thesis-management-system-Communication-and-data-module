<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NotificationEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The notification title.
     *
     * @var string
     */
    public $title;

    /**
     * The notification content.
     *
     * @var string
     */
    public $content;

    /**
     * Additional data to include in the email.
     *
     * @var array
     */
    public $additionalData;

    /**
     * Create a new message instance.
     *
     * @param string $title
     * @param string $content
     * @param array $additionalData
     * @return void
     */
    public function __construct($title, $content, $additionalData = [])
    {
        $this->title = $title;
        $this->content = $content;
        $this->additionalData = $additionalData;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject($this->title)
            ->view('emails.notification')
            ->with([
                'title' => $this->title,
                'content' => $this->content,
                'additionalData' => $this->additionalData,
                'url' => $this->additionalData['url'] ?? null,
                'systemName' => 'Дипломын ажлын удирдах систем',
            ]);
    }
}