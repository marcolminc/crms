<?php

namespace App\Http\Requests;

use App\Rules\FullName;
use App\Rules\PhoneNumber;
use App\Services\Contact\ContactMessage;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The public contact-page enquiry. Validated to the same standard as the rest
 * of the app: a real name (no digits/symbols), a well-formed e-mail, an
 * optional Tanzanian phone, and a bounded message.
 */
final class ContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120', new FullName],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', new PhoneNumber],
            'subject' => ['nullable', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'your name',
            'email' => 'your e-mail address',
            'phone' => 'your phone number',
            'subject' => 'subject',
            'message' => 'message',
        ];
    }

    public function toMessage(): ContactMessage
    {
        return new ContactMessage(
            name: (string) $this->validated('name'),
            email: (string) $this->validated('email'),
            phone: $this->filled('phone') ? (string) $this->validated('phone') : null,
            subject: $this->filled('subject') ? (string) $this->validated('subject') : null,
            body: (string) $this->validated('message'),
        );
    }
}
