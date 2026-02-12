<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // 🔹 Toutes les notifications
    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'notifications' => $request->user()->notifications
        ]);
    }

    // 🔹 Notifications non lues
    public function unread(Request $request)
    {
        return response()->json([
            'success' => true,
            'notifications' => $request->user()->unreadNotifications
        ]);
    }

    // 🔹 Marquer TOUT comme lu
    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read'
        ]);
    }

    // 🔹 Marquer UNE notification comme lue
    public function markOneAsRead($id, Request $request)
    {
        $notification = $request->user()
            ->notifications()
            ->where('id', $id)
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    }
}

