<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Opportunity;
use App\Models\Like;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class OpportunityController extends Controller
{
    // 1. Ambil semua lowongan yang statusnya 'open'
    public function index()
    {
        $opportunities = Opportunity::with(['creator.organization', 'organization'])
            ->withCount(['likes', 'comments'])
            ->where('status', 'open')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $opportunities
        ], 200);
    }

    // 2. Simpan lowongan baru ke Database
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'organization_id' => 'required|exists:organizations,id',
            'judul'           => 'required|string|max:255',
            'deskripsi'       => 'required|string',
            'lokasi'          => 'required|string',
            'maps_url'        => 'required|url',
            'tipe'            => 'required|in:online,offline',
            'tanggal_mulai'   => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'kuota'           => 'required|integer',
            'foto'            => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $path = null;
        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('opportunities', 'public');
        }

        // Simpan ke DB dengan mengisi user_id dan created_by otomatis
        $opportunity = Opportunity::create([
            'organization_id' => $request->organization_id,
            'user_id'         => Auth::id(), 
            'created_by'      => Auth::id(), 
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

        return response()->json([
            'success' => true,
            'message' => 'Lowongan berhasil dibuat',
            'data'    => $opportunity->load(['creator.organization'])
        ], 201);

            // ... kode validator (tambahkan 'categories' => 'required|array') ...

        $opportunity = Opportunity::create([
        // ... field yang sudah ada ...
        ]);

        // Sinkronkan kategori yang dipilih (isinya array ID kategori, misal [1, 2])
        if ($request->has('categories')) {
            $opportunity->categories()->sync($request->categories);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lowongan dan kategori berhasil disimpan',
            'data' => $opportunity->load('categories')
        ], 201);
    }

    // 3. Detail Lowongan (Termasuk komentar dan balasan)
    public function show($id)
    {
        $opportunity = Opportunity::with([
                'creator.organization', 
                'organization',
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

    // 4. Update Data Lowongan
    public function update(Request $request, $id)
    {
        $opportunity = Opportunity::find($id);

        if (!$opportunity || $opportunity->created_by !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Tidak punya akses'], 403);
        }

        $path = $opportunity->foto;
        if ($request->hasFile('foto')) {
            if ($opportunity->foto) Storage::disk('public')->delete($opportunity->foto);
            $path = $request->file('foto')->store('opportunities', 'public');
        }

        // Gunakan update dengan data yang sudah di-filter
        $opportunity->update(array_merge($request->except('foto'), ['foto' => $path]));

        return response()->json([
            'success' => true, 
            'message' => 'Data diperbarui', 
            'data'    => $opportunity
        ], 200);
    }

    // 5. Hapus Data
    public function destroy($id)
    {
        $opportunity = Opportunity::find($id);
        if (!$opportunity || $opportunity->created_by !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
        }

        if ($opportunity->foto) Storage::disk('public')->delete($opportunity->foto);
        $opportunity->delete();

        return response()->json(['success' => true, 'message' => 'Data dihapus'], 200);
    }

    // 6. Like & Unlike (Toggle)
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

    // 7. Simpan Komentar Baru
    public function storeComment(Request $request, $id)
    {
        $request->validate(['comment' => 'required|string']);

        $comment = Comment::create([
            'user_id'        => Auth::id(),
            'opportunity_id' => $id,
            'comment'        => $request->comment,
            'parent_id'      => $request->parent_id // Untuk fitur reply
        ]);

        return response()->json([
            'success' => true, 
            'data'    => $comment->load('user:id,name,foto_profil')
        ], 201);
    }

    // 8. Ambil Komentar Berdasarkan Opportunity ID
    public function getComments($id)
    {
        $comments = Comment::with(['user:id,name,foto_profil', 'replies.user:id,name,foto_profil'])
            ->where('opportunity_id', $id)
            ->whereNull('parent_id') // Hanya ambil komentar utama, balasan masuk ke 'replies'
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $comments
        ], 200);
    }

    // 9. Cek Status Like User Saat Ini
    public function getLikeStatus($id)
    {
        $userId = Auth::id();
        $isLiked = Like::where('user_id', $userId)->where('opportunity_id', $id)->exists();
        $totalLikes = Like::where('opportunity_id', $id)->count();

        return response()->json([
            'success' => true,
            'is_liked' => $isLiked,
            'total' => $totalLikes
        ], 200);
    }
    // Tambahkan di OpportunityController
public function getCategories()
{
    $categories = \App\Models\Category::all();
    return response()->json([
        'success' => true,
        'data' => $categories
    ], 200);
}
}