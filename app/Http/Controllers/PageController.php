<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactMessageRequest;
use App\Services\Contact\ContactMessageDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The public, no-login-required company pages: About us, Contact us and Terms
 * & conditions. The contact form records an enquiry and routes it through the
 * contact dispatcher (today an admin alert), so browsing and reaching the
 * office works for guests and signed-in users alike.
 */
final class PageController extends Controller
{
    public function about(): View
    {
        return view('pages.about');
    }

    public function terms(): View
    {
        return view('pages.terms');
    }

    public function contact(): View
    {
        return view('pages.contact');
    }

    public function submit(ContactMessageRequest $request, ContactMessageDispatcher $dispatcher): RedirectResponse
    {
        $dispatcher->dispatch($request->toMessage());

        return back()
            ->with('status', 'Thank you — your message has reached our team. We will get back to you shortly.');
    }
}
