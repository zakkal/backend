<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class OrganizationController extends Controller
{
    /**
     * 1. INDEX: Menampilkan semua daftar organisasi (Public/Admin)
     */
    public function index()
    {
        $organizations = Organization::latest()->get();
        
        return response()->json([
            'status' => 'success',
            'data' => $organizations
        ], 200);
    }

    /**
     * 2. STORE: Membuat organisasi baru (Create)
     * Biasanya dipanggil saat user mendaftar sebagai admin organisasi
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_organisasi' => 'required|string|max:255|unique:organizations',
            'deskripsi'       => 'nullable|string',
            'alamat'          => 'nullable|string',
            'website'         => 'nullable|url',
            'logo'            => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = auth()->user();

        // Cek jika user sudah punya organisasi sebelumnya
        if (Organization::where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'User sudah memiliki organisasi'], 400);
        }

        $path = null;
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('logos', 'public');
        }

        // Gunakan Transaction agar data User & Organization sinkron
        $organization = DB::transaction(function () use ($request, $user, $path) {
            $org = Organization::create([
                'user_id' => $user->id,
                'nama_organisasi' => $request->nama_organisasi,
                'deskripsi' => $request->deskripsi,
                'alamat' => $request->alamat,
                'website' => $request->website,
                'logo' => $path,
            ]);

            // Update kolom organization_id di tabel users agar sinkron
            $user->update(['organization_id' => $org->id]);

            return $org;
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Organisasi berhasil didaftarkan',
            'data' => $organization
        ], 201);
    }

    /**
     * 3. SHOW: Menampilkan profil organisasi milik user login (Read)
     */
    public function show()
    {
        $user = auth()->user();
        $organization = Organization::where('user_id', $user->id)->first();

        if (!$organization) {
            return response()->json(['status' => 'error', 'message' => 'Organisasi tidak ditemukan'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $organization]);
    }

    /**
     * 4. UPDATE: Memperbarui data organisasi (Update)
     */
    public function update(Request $request)
    {
        $user = auth()->user();
        $organization = Organization::where('user_id', $user->id)->first();

        if (!$organization) {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $validator = Validator::make($request->all(), [
            'nama_organisasi' => 'required|string|max:255',
            'deskripsi'       => 'nullable|string',
            'alamat'          => 'nullable|string',
            'website'         => 'nullable|url',
            'logo'            => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $data = $request->only(['nama_organisasi', 'deskripsi', 'alamat', 'website']);

        if ($request->hasFile('logo')) {
            if ($organization->logo) {
                Storage::disk('public')->delete($organization->logo);
            }
            $data['logo'] = $request->file('logo')->store('logos', 'public');
        }

        $organization->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Profil organisasi berhasil diperbarui',
            'data' => $organization
        ]);
    }

    /**
     * 5. DESTROY: Menghapus organisasi (Delete)
     */
    public function destroy()
    {
        $user = auth()->user();
        $organization = Organization::where('user_id', $user->id)->first();

        if (!$organization) {
            return response()->json(['message' => 'Organisasi tidak ditemukan'], 404);
        }

        // Hapus logo dari storage
        if ($organization->logo) {
            Storage::disk('public')->delete($organization->logo);
        }

        // Hapus link organization_id di user (opsional)
        $user->update(['organization_id' => null]);

        $organization->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Organisasi berhasil dihapus'
        ]);
    }
}