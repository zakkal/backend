<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    /**
     * 1. Mendapatkan daftar obrolan (Inbox)
     */
    public function getConversations()
    {
        $userId = Auth::id();

        $conversations = Conversation::with(['user1', 'user2', 'messages' => function($q) {
                $q->latest()->limit(1); // Ambil pesan terakhir untuk preview
            }])
            ->where('user1_id', $userId)
            ->orWhere('user2_id', $userId)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $conversations
        ]);
    }

    /**
     * 2. Membuat percakapan baru atau mengambil yang sudah ada
     */
    public function startConversation(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id'
        ]);

        $user1 = Auth::id();
        $user2 = $request->receiver_id;

        // Mencegah chat dengan diri sendiri
        if ($user1 == $user2) {
            return response()->json(['message' => 'You cannot chat with yourself'], 400);
        }

        // Cari apakah percakapan sudah ada (cek dua arah)
        $conversation = Conversation::where(function($q) use ($user1, $user2) {
                $q->where('user1_id', $user1)->where('user2_id', $user2);
            })
            ->orWhere(function($q) use ($user1, $user2) {
                $q->where('user1_id', $user2)->where('user2_id', $user1);
            })
            ->first();

        // Jika belum ada, buat baru
        if (!$conversation) {
            $conversation = Conversation::create([
                'user1_id' => min($user1, $user2), // Agar selalu konsisten urutannya
                'user2_id' => max($user1, $user2)
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $conversation
        ]);
    }

    /**
     * 3. Mengambil semua pesan dalam satu obrolan
     */
    public function getMessages($conversationId)
    {
        // Pastikan user adalah bagian dari percakapan ini
        $conversation = Conversation::findOrFail($conversationId);
        if ($conversation->user1_id != Auth::id() && $conversation->user2_id != Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $messages = Message::where('conversation_id', $conversationId)
            ->with('sender:id,name,foto_profil')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $messages
        ]);
    }

    /**
     * 4. Mengirim pesan baru
     */
    public function sendMessage(Request $request, $conversationId)
    {
        $request->validate([
            'message' => 'required|string'
        ]);

        // Verifikasi kepemilikan percakapan
        $conversation = Conversation::findOrFail($conversationId);
        if ($conversation->user1_id != Auth::id() && $conversation->user2_id != Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $message = Message::create([
            'conversation_id' => $conversationId,
            'sender_id' => Auth::id(),
            'message' => $request->message
        ]);

        // Opsional: Load data pengirim untuk response langsung
        $message->load('sender:id,name,foto_profil');

        return response()->json([
            'status' => 'success',
            'message' => 'Message sent successfully',
            'data' => $message
        ]);
    }
}