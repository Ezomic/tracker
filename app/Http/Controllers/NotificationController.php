<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function read(Request $request, string $notification): RedirectResponse
    {
        $this->currentUser($request)
            ->notifications()
            ->whereKey($notification)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $this->currentUser($request)->unreadNotifications->markAsRead();

        return back();
    }
}
