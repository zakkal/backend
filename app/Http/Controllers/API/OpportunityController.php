<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Opportunity;
use App\Models\Like;
use App\Models\Comment;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class OpportunityController extends Controller
{
    public function index()
    {
        $opportunities = Opportunity::with(['creator.organization', 'organization', 'categories'])
            ->withCount(['likes', 'comments'])
            ->where('status', 'open')
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $opportunities], 200);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'judul'           => 'required|string|max:255',
            'deskripsi'       => 'required|string',
            'lokasi'          => 'required|string',
            'maps_url'        => 'required|url',
            'tipe'            => 'required|in:online,offline',
            'tanggal_mulai'   => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'kuota'           => 'required|integer',
            'foto'            => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'categories'      => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Logic: Jika organization_id tidak dikirim, ambil dari profile user login
        $orgId = $request->organization_id ?? $user->organization_id;

        if (!$orgId) {
            return response()->json(['success' => false, 'message' => 'User tidak terhubung ke organisasi manapun.'], 403);
        }

        $path = null;
        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('opportunities', 'public');
        }

        $opportunity = Opportunity::create([
            'organization_id' => $orgId,
            'user_id'         => $user->id, 
            'created_by'      => $user->id, 
            'judul'           => $request->judul,
            'deskripsi'       => $request->deskripsi,
            'lokasi'          => $request->lokasi,
            'maps_url'        => $request->maps_url,
            'foto'            => $path,
            'tipe'            => $request->tipe,
            'tanggal_mulai'   => $request->tanggal_mulai,
            'tanggal_selesai' => $request->tanggal_selesai,
            'kuota'           => $request->kuota,
            'status'          => 'open',
        ]);

        if ($request->has('categories')) {
            $opportunity->categories()->sync($request->categories);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lowongan berhasil dibuat',
            'data'    => $opportunity->load(['creator.organization', 'categories'])
        ], 201);
    }

    public function show($id)
    {
        $opportunity = Opportunity::with([
                'creator.organization', 
                'organization',
                'categories',
                'comments.user:id,name,foto_profil',
                'comments.replies.user:id,name,foto_profil'
            ])
            ->withCount(['likes', 'comments'])
            ->find($id);

        if (!$opportunity) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        return response()->json(['success' => true, 'data' => $opportunity], 200);
    }

    public function toggleLike($id)
    {
        $userId = Auth::id();
        $like = Like::where('user_id', $userId)->where('opportunity_id', $id)->first();

        if ($like) {
            $like->delete();
            return response()->json(['success' => true, 'message' => 'Unliked', 'is_liked' => false]);
        }

        Like::create(['user_id' => $userId, 'opportunity_id' => $id]);
        return response()->json(['success' => true, 'message' => 'Liked', 'is_liked' => true], 201);
    }

    public function getLikeStatus($id)
    {
        $isLiked = Like::where('user_id', Auth::id())->where('opportunity_id', $id)->exists();
        return response()->json(['success' => true, 'is_liked' => $isLiked]);
    }

    public function storeComment(Request $request, $id)
    {
        $request->validate(['comment' => 'required|string']);

        $comment = Comment::create([
            'user_id'        => Auth::id(),
            'opportunity_id' => $id, 
            'comment'        => $request->comment,
            'parent_id'      => $request->parent_id
        ]);

        return response()->json(['success' => true, 'data' => $comment->load('user:id,name,foto_profil')], 201);
    }
    
    // ... method lainnya (update, destroy, getComments, getCategories) tetap sama
}