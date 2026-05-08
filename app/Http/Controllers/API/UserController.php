<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * UserController handles user profile updates and other user-related actions.
 */
class UserController extends Controller
{
    /**
     * Update Profil dan Foto Profil User
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        // Validasi input
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            'bio'         => 'nullable|string',
            'lokasi'      => 'nullable|string',
            'foto_profil' => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // Max 2MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only(['name', 'bio', 'lokasi']);

        // Logika Upload Foto
        if ($request->hasFile('foto_profil')) {
            // Hapus foto lama di storage jika ada dan bukan URL Google
            if ($user->foto_profil && !filter_var($user->foto_profil, FILTER_VALIDATE_URL)) {
                Storage::disk('public')->delete($user->foto_profil);
            }

            // Simpan file baru ke folder 'profiles' di dalam storage/app/public
            $path = $request->file('foto_profil')->store('profiles', 'public');
            $data['foto_profil'] = $path;
        }

        // Update data user
        $user->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Profil berhasil diperbarui',
            'data' => $user // Ini akan menyertakan foto_profil_url secara otomatis
        ]);
    }

    /**
     * Mendapatkan data profil user yang sedang login
     */
    public function me()
    {
        return response()->json([
            'status' => 'success',
            'data' => Auth::user()
        ]);
    }
}