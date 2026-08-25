<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

final class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(20)
            ->through(fn (DatabaseNotification $notification): array => $this->payload($notification));

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'read_all_url' => route('notifications.read-all'),
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        /** @var DatabaseNotification $record */
        $record = $request->user()->notifications()->findOrFail($notification);
        $record->markAsRead();

        $url = is_string($record->data['url'] ?? null)
            ? $record->data['url']
            : route('notifications.index');

        return redirect()->to($url);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'Todas as notificações foram marcadas como lidas.');
    }

    /** @return array<string, mixed> */
    private function payload(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'title' => $notification->data['title'] ?? 'Notificação',
            'message' => $notification->data['message'] ?? '',
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at?->diffForHumans(),
            'read_url' => route('notifications.read', $notification->id),
        ];
    }
}
