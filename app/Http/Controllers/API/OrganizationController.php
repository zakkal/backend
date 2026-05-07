<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class OrganizationController extends Controller
{
    /**
     * Menampilkan profil organisasi milik user yang sedang login
     */
    public function show()
    {
        $user = auth()->user();
        
        // Load relasi organisasi
        $organization = Organization::where('user_id', $user->id)->first();

        if (!$organization) {
            return response()->json([
                'status' => 'error',
                'message' => 'Organisasi tidak ditemukan untuk user ini'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $organization
        ]);
    }

    /**
     * Update data organisasi
     */
    public function update(Request $request)
    {
        $user = auth()->user();
        $organization = Organization::where('user_id', $user->id)->first();

        if (!$organization) {
            return response()->json(['message' => 'Hanya admin organisasi yang bisa akses'], 403);
        }

        $validator = Validator::make($request->all(), [
            'nama_organisasi' => 'required|string|max:255',
            'deskripsi'       => 'nullable|string',
            'alamat'          => 'nullable|string',
            'website'         => 'nullable|url',
            'logo'            => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // Max 2MB
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $data = $request->only(['nama_organisasi', 'deskripsi', 'alamat', 'website']);

        // Logic Upload Logo
        if ($request->hasFile('logo')) {
            // Hapus logo lama jika ada
            if ($organization->logo) {
                Storage::delete('public/' . $organization->logo);
            }
            $path = $request->file('logo')->store('logos', 'public');
            $data['logo'] = $path;
        }

        $organization->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Profil organisasi berhasil diperbarui',
            'data' => $organization
        ]);
    }
}
