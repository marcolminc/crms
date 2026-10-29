<?php

namespace App\Services\Contact;

/**
 * A validated enquiry from the public contact page, carried as an immutable
 * value object so every delivery channel sees exactly the same data.
 */
final readonly class ContactMessage
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone = null,
        public ?string $subject = null,
        public string $body = '',
    ) {}

    /**
     * A short headline for alerts / e-mail subjects.
     */
    public function headline(): string
    {
        return trim((string) $this->subject) !== '' ? $this->subject : 'Website enquiry';
    }

    /**
     * The message rendered as plain text, used for the in-app alert body.
     */
    public function toPlainText(): string
    {
        $lines = [
            "From: {$this->name} <{$this->email}>",
        ];

        if ($this->phone) {
            $lines[] = "Phone: {$this->phone}";
        }

        $lines[] = '';
        $lines[] = $this->body;

        return implode("\n", $lines);
    }
}
