<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\UserNotificationRead;
use App\Support\ApiLocale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * List notifications for the authenticated user, including per-user is_read flag.
     */
    public function index(Request $request): JsonResponse
    {
        $role = $this->getRole();

        if (! in_array($role, ['member', 'merchant'])) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $userId = Auth::id();

        $notifications = AdminNotification::where('status', 'sent')
            ->where(function ($q) use ($role) {
                $q->where('target_audience', 'all')
                    ->orWhere('target_audience', 'all_users')
                    ->orWhere('target_audience', $role);
            })
            ->orderByDesc('sent_at')
            ->paginate($request->get('per_page', 20));

        // Attach is_read for each notification for this user
        $readIds = UserNotificationRead::where('role', $role)
            ->where('user_id', $userId)
            ->whereIn('admin_notification_id', $notifications->pluck('id'))
            ->pluck('admin_notification_id')
            ->flip();

        $notifications->setCollection(
            $notifications->getCollection()->map(function (AdminNotification $n) use ($readIds) {
                return [
                    'id' => $n->id,
                    'title' => ApiLocale::resolve($n->title_i18n, (string) $n->title),
                    'message' => ApiLocale::resolve($n->message_i18n, (string) $n->message),
                    'notification_type' => $n->notification_type,
                    'target_audience' => $n->target_audience,
                    'sent_at' => $n->sent_at?->toIso8601String(),
                    'is_read' => $readIds->has($n->id),
                    'title_i18n' => $n->title_i18n,
                    'message_i18n' => $n->message_i18n,
                ];
            })
        );

        return $this->paginatedResponse($notifications, 'notifications');
    }

    /**
     * Mark a single notification as read.
     * Body: { id: <notification_id> }
     */
    public function markAsRead(Request $request): JsonResponse
    {
        $request->validate(['id' => 'required|integer|exists:admin_notifications,id']);

        UserNotificationRead::firstOrCreate([
            'role' => $this->getRole(),
            'user_id' => Auth::id(),
            'admin_notification_id' => $request->integer('id'),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Mark all sent notifications (visible to this user's role) as read.
     */
    public function markAllAsRead(): JsonResponse
    {
        $role = $this->getRole();
        $userId = Auth::id();

        $ids = AdminNotification::where('status', 'sent')
            ->where(function ($q) use ($role) {
                $q->where('target_audience', 'all')
                    ->orWhere('target_audience', 'all_users')
                    ->orWhere('target_audience', $role);
            })
            ->pluck('id');

        $rows = $ids->map(fn ($id) => [
            'role' => $role,
            'user_id' => $userId,
            'admin_notification_id' => $id,
        ])->toArray();

        // insertOrIgnore respects the unique constraint — no duplicates
        UserNotificationRead::insertOrIgnore($rows);

        return response()->json(['success' => true]);
    }
}
