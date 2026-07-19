<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StaffMessage;
use App\Models\User;
use App\Events\StaffMessageSent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffChatController extends Controller
{
    /**
     * Get list of staff users with their last message (for sidebar).
     */
    public function getConversations(Request $request)
    {
        $userId = $request->user()->id;

        // Get all users who have exchanged messages with current user
        $contactIds = StaffMessage::where('sender_id', $userId)
            ->orWhere('receiver_id', $userId)
            ->pluck('sender_id')
            ->merge(StaffMessage::where('sender_id', $userId)
                ->orWhere('receiver_id', $userId)
                ->pluck('receiver_id'))
            ->unique()
            ->filter(fn($id) => $id != $userId)
            ->values();

        // If no contacts yet, return all staff users
        if ($contactIds->isEmpty()) {
            $users = User::where('id', '!=', $userId)
                ->where('role', '!=', 'client')
                ->select('id', 'name', 'role')
                ->get();
        } else {
            $users = User::whereIn('id', $contactIds)
                ->select('id', 'name', 'role')
                ->get();
        }

        // Add last message and unread count to each user
        $conversations = $users->map(function ($user) use ($userId) {
            $lastMessage = StaffMessage::where(function ($q) use ($userId, $user) {
                $q->where('sender_id', $userId)->where('receiver_id', $user->id);
            })->orWhere(function ($q) use ($userId, $user) {
                $q->where('sender_id', $user->id)->where('receiver_id', $userId);
            })->latest()->first();

            $unreadCount = StaffMessage::where('sender_id', $user->id)
                ->where('receiver_id', $userId)
                ->where('is_read', false)
                ->count();

            return [
                'user'          => $user,
                'last_message'  => $lastMessage?->message ?? '',
                'last_time'     => $lastMessage?->created_at?->diffForHumans() ?? '',
                'unread_count'  => $unreadCount,
            ];
        })->sortByDesc('last_time')->values();

        return response()->json([
            'status' => 'success',
            'data'   => $conversations,
        ]);
    }

    /**
     * Get messages between current user and a specific user.
     */
    public function getMessages(Request $request, $otherUserId)
    {
        $userId = $request->user()->id;

        $messages = StaffMessage::where(function ($q) use ($userId, $otherUserId) {
            $q->where('sender_id', $userId)->where('receiver_id', $otherUserId);
        })->orWhere(function ($q) use ($userId, $otherUserId) {
            $q->where('sender_id', $otherUserId)->where('receiver_id', $userId);
        })->orderBy('created_at', 'asc')->get();

        // Mark messages as read
        StaffMessage::where('sender_id', $otherUserId)
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'status' => 'success',
            'data'   => $messages,
        ]);
    }

    /**
     * Send a new message.
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'message'     => 'required|string|max:5000',
        ]);

        $userId = $request->user()->id;

        $message = StaffMessage::create([
            'sender_id'   => $userId,
            'receiver_id' => $request->receiver_id,
            'message'     => $request->message,
            'is_read'     => false,
        ]);

        // Broadcast the event via Reverb
        broadcast(new StaffMessageSent($message))->toOthers();

        return response()->json([
            'status' => 'success',
            'data'   => $message,
        ]);
    }

    /**
     * Mark a message as read.
     */
    public function markAsRead(Request $request, $messageId)
    {
        $message = StaffMessage::findOrFail($messageId);
        $message->update(['is_read' => true]);

        return response()->json([
            'status' => 'success',
            'message' => 'Message marked as read',
        ]);
    }
}