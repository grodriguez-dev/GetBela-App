<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Notification;

class NotificationController extends Controller
{
    public function index($businessId)
    {
        $notifications = Notification::where('business_id', $businessId)
            ->orderBy('created_at', 'desc')
            ->get();

        // Contador de cuantas no han sido leídas
        $unreadCount = Notification::where('business_id', $businessId)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount
        ], 200);
    }

    public function markAsRead($id)
    {
        $notification = Notification::findOrFail($id);
        
        $notification->update([
            'read_at' => now(),
        ]);

        return response()->json([
            'message' => 'Notificación marcada como leída.'
        ], 200);
    }
}
