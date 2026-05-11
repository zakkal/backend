<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Opportunity;
use App\Models\Like;
use App\Models\Notification;
use App\Models\Comment;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Exception;

class OpportunityController extends Controller
{
    /**
     * 1. Menampilkan semua lowongan (Status Open)
     */
    public function index()
    {
        $opportunities = Opportunity::with(['creator.organization', 'organization', 'categories'])
            ->withCount(['likes', 'comments'])
            ->where('status', 'open')
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $opportunities], 200);
    }

    /**
     * 2. Membuat lowongan baru
     */
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

        $orgId = $request->organization_id ?? $user->organization_id;

        if (!$orgId) {
            return response()->json(['success' => false, 'message' => 'User tidak terhubung ke organisasi.'], 403);
        }

        try {
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

            return response()->json(['success' => true, 'message' => 'Lowongan berhasil dibuat', 'data' => $opportunity], 201);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal membuat lowongan', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 3. Detail lowongan
     */
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

    /**
     * 4. Update lowongan
     */
    public function update(Request $request, $id)
    {
        $opportunity = Opportunity::findOrFail($id);
        
        if ($opportunity->created_by != Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($request->hasFile('foto')) {
            if ($opportunity->foto) {
                Storage::disk('public')->delete($opportunity->foto);
            }
            $opportunity->foto = $request->file('foto')->store('opportunities', 'public');
        }

        $opportunity->update($request->except(['foto', 'categories']));

        if ($request->has('categories')) {
            $opportunity->categories()->sync($request->categories);
        }

        return response()->json(['success' => true, 'message' => 'Lowongan berhasil diupdate']);
    }

    /**
     * 5. Hapus lowongan
     */
    public function destroy($id)
    {
        $opportunity = Opportunity::findOrFail($id);
        if ($opportunity->created_by != Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($opportunity->foto) {
            Storage::disk('public')->delete($opportunity->foto);
        }

        $opportunity->delete();
        return response()->json(['success' => true, 'message' => 'Lowongan berhasil dihapus']);
    }

    /**
     * 6. Fitur Like/Unlike + Notifikasi
     */
    public function toggleLike($id)
    {
        try {
            $userId = Auth::id();
            $opportunity = Opportunity::find($id);
            
            if (!$opportunity) {
                return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
            }

            $like = Like::where('user_id', $userId)->where('opportunity_id', $id)->first();

            if ($like) {
                $like->delete();
                return response()->json(['success' => true, 'message' => 'Unliked', 'is_liked' => false]);
            }

            Like::create(['user_id' => $userId, 'opportunity_id' => $id]);

            // Kirim Notifikasi ke Pemilik
            if ($opportunity->created_by != $userId) {
                Notification::create([
                    'user_id' => $opportunity->created_by,
                    'from_user_id' => $userId,
                    'type' => 'like',
                    'message' => Auth::user()->name . ' menyukai lowongan Anda.',
                    'is_read' => false
                ]);
            }

            return response()->json(['success' => true, 'message' => 'Liked', 'is_liked' => true], 201);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Server Error', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 7. Simpan Komentar + Notifikasi
     */
    public function storeComment(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'comment' => 'required|string',
            'parent_id' => 'nullable|exists:comments,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $opportunity = Opportunity::findOrFail($id);

            $comment = Comment::create([
                'user_id'        => Auth::id(),
                'opportunity_id' => $id, 
                'comment'        => $request->comment,
                'parent_id'      => $request->parent_id
            ]);

            // Kirim Notifikasi ke Pemilik
            if ($opportunity->created_by != Auth::id()) {
                Notification::create([
                    'user_id' => $opportunity->created_by,
                    'from_user_id' => Auth::id(),
                    'type' => 'comment',
                    'message' => Auth::user()->name . ' berkomentar di lowongan Anda.',
                    'is_read' => false
                ]);
            }

            return response()->json(['success' => true, 'data' => $comment->load('user:id,name,foto_profil')], 201);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal kirim komentar', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 8. Ambil Komentar (Fix Error 500 temanmu)
     */
    public function getComments($id)
    {
        $comments = Comment::with(['user:id,name,foto_profil', 'replies.user:id,name,foto_profil'])
            ->where('opportunity_id', $id)
            ->whereNull('parent_id')
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $comments]);
    }

    /**
     * 9. Lain-lain
     */
    public function getLikeStatus($id)
    {
        $isLiked = Like::where('user_id', Auth::id())->where('opportunity_id', $id)->exists();
        return response()->json(['success' => true, 'is_liked' => $isLiked]);
    }

    public function getCategories()
    {
        return response()->json(['success' => true, 'data' => Category::all()]);
    }
}