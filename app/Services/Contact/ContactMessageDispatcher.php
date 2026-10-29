<?php

namespace App\Services\Contact;

use App\Contracts\ContactMessageChannel;

/**
 * The single entry point the contact controller talks to. It fans an enquiry
 * out to every registered channel, so adding e-mail (or SMS) later is a matter
 * of binding another ContactMessageChannel — no controller changes required.
 */
final class ContactMessageDispatcher
{
    /**
     * @param  array<int, ContactMessageChannel>  $channels
     */
    public function __construct(private readonly array $channels = []) {}

    public function dispatch(ContactMessage $message): void
    {
        foreach ($this->channels as $channel) {
            $channel->deliver($message);
        }
    }
}
