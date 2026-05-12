<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class OrganizationController extends Controller
{
    /**
     * 1. INDEX: List semua organisasi
     * Bisa digunakan Super Admin untuk melihat yang belum diverifikasi
     */
    public function index(Request $request)
    {
        $query = Organization::query();

        // Filter jika ingin melihat yang belum diverifikasi saja (untuk Super Admin)
        if ($request->has('pending')) {
            $query->where('is_verified', 0);
        }

        $organizations = $query->latest()->get();
        
        return response()->json([
            'success' => true,
            'data' => $organizations
        ], 200);
    }

    /**
     * 2. STORE (Upgrade Premium): Membuat organisasi baru
     * User mengisi form ini untuk request menjadi Admin/Premium
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
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $user = Auth::user();

        // Cek jika user sudah dalam proses upgrade atau sudah punya organisasi
        if ($user->organization_id != null) {
            return response()->json([
                'success' => false, 
                'message' => 'Anda sudah memiliki organisasi atau sedang dalam proses verifikasi'
            ], 400);
        }

        try {
            $path = null;
            if ($request->hasFile('logo')) {
                $path = $request->file('logo')->store('logos', 'public');
            }

            $organization = DB::transaction(function () use ($request, $user, $path) {
                // Buat data Organisasi dengan status is_verified = 0
                $org = Organization::create([
                    'user_id'         => $user->id,
                    'nama_organisasi' => $request->nama_organisasi,
                    'deskripsi'       => $request->deskripsi,
                    'alamat'          => $request->alamat,
                    'website'         => $request->website,
                    'logo'            => $path,
                    'is_verified'     => 0, // Default: Menunggu verifikasi Super Admin
                ]);

                // Update organization_id di user, tapi ROLE tetap 'user' sampai diverifikasi
                $user->update([
                    'organization_id' => $org->id
                ]);

                return $org;
            });

            return response()->json([
                'success' => true,
                'message' => 'Permintaan upgrade berhasil dikirim. Menunggu verifikasi Super Admin.',
                'data' => $organization
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Gagal mengajukan upgrade organisasi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 3. SHOW: Profil organisasi milik user login
     */
    public function show()
    {
        $user = Auth::user();
        
        $organization = Organization::where('id', $user->organization_id)->first();

        if (!$organization) {
            return response()->json([
                'success' => false, 
                'message' => 'Anda belum memiliki profil organisasi'
            ], 404);
        }

        return response()->json([
            'success' => true, 
            'data' => $organization
        ]);
    }

    /**
     * 4. UPDATE: Edit data organisasi (Hanya bisa jika sudah diverifikasi/Admin)
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        $organization = Organization::where('id', $user->organization_id)->first();

        if (!$organization) {
            return response()->json(['success' => false, 'message' => 'Organisasi tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nama_organisasi' => 'required|string|max:255|unique:organizations,nama_organisasi,'.$organization->id,
            'deskripsi'       => 'nullable|string',
            'alamat'          => 'nullable|string',
            'website'         => 'nullable|url',
            'logo'            => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $data = $request->only(['nama_organisasi', 'deskripsi', 'alamat', 'website']);

            if ($request->hasFile('logo')) {
                if ($organization->logo) {
                    Storage::disk('public')->delete($organization->logo);
                }
                $data['logo'] = $request->file('logo')->store('logos', 'public');
            }

            $organization->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Profil organisasi berhasil diperbarui',
                'data' => $organization
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 5. DESTROY: Hapus organisasi
     */
    public function destroy()
    {
        $user = Auth::user();
        $organization = Organization::where('id', $user->organization_id)->first();

        if (!$organization) {
            return response()->json(['success' => false, 'message' => 'Organisasi tidak ditemukan'], 404);
        }

        try {
            if ($organization->logo) {
                Storage::disk('public')->delete($organization->logo);
            }

            // Putuskan hubungan di tabel users dan kembalikan role ke user biasa
            User::where('organization_id', $organization->id)->update([
                'organization_id' => null,
                'role' => 'user'
            ]);

            $organization->delete();

            return response()->json([
                'success' => true,
                'message' => 'Organisasi berhasil dihapus dan akun Anda kembali menjadi user biasa'
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}