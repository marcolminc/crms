<?php

namespace App\Contracts;

use App\Services\Contact\ContactMessage;

/**
 * A route an enquiry from the public contact page can travel down. Today the
 * only channel is an in-app alert to the administrators; a future e-mail or
 * SMS channel just implements this interface and registers itself with the
 * dispatcher, so the controller never has to change.
 */
interface ContactMessageChannel
{
    public function deliver(ContactMessage $message): void;
}
