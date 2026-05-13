<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Notification;
use App\Models\Organization;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    /**
     * Google Login
     */
    public function googleLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        try {
            $googleUser = Socialite::driver('google')->stateless()->userFromToken($request->token);

            $user = User::where('email', $googleUser->getEmail())->first();
            $isNewUser = false;

            if (!$user) {
                $user = User::create([
                    'name'        => $googleUser->getName(),
                    'email'       => $googleUser->getEmail(),
                    'google_id'   => $googleUser->getId(),
                    'username'    => explode('@', $googleUser->getEmail())[0] . rand(10, 99),
                    'role'        => 'user',
                    'is_verified' => true,
                    'password'    => null,
                ]);
                $isNewUser = true;
            } else {
                $user->update(['google_id' => $googleUser->getId()]);
            }

            $token = JWTAuth::fromUser($user);

            return response()->json([
                'status'  => 'success',
                'message' => 'Login Google berhasil',
                'is_new_user' => $isNewUser,
                'data'    => [
                    'user'  => $user->load('organization'),
                    'token' => $this->respondWithToken($token)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal autentikasi Google',
                'error'   => $e->getMessage()
            ], 401);
        }
    }

    /**
     * Register Manual
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255|unique:users',
            'email'    => 'required|email|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name'        => $request->name,
            'username'    => explode('@', $request->email)[0] . rand(10, 99),
            'email'       => $request->email,
            'password'    => Hash::make($request->password),
            'role'        => 'user',
            'is_verified' => true,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Registrasi berhasil. Silahkan login.',
            'data'    => $user
        ], 201);
    }

    /**
     * Login Manual
     */
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        try {
            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Email atau Password salah'
                ], 401);
            }

            $user = auth()->user();
        } catch (JWTException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal membuat token',
                'error'   => $e->getMessage()
            ], 500);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Login berhasil',
            'data'    => [
                'user' => $user->load('organization'),
                'token' => $this->respondWithToken($token)
            ]
        ]);
    }

    /**
     * FITUR BARU: Request Upgrade (User mengisi form organisasi)
     */
    public function requestUpgrade(Request $request)
    {
        $user = auth()->user();

        // Cek apakah user sudah pernah mengajukan upgrade
        if ($user->organization_id != null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda sudah mengajukan upgrade atau sudah memiliki organisasi.'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'nama_organisasi' => 'required|string|max:255',
            'deskripsi'       => 'required|string',
            'lokasi'          => 'nullable|string',
            'website'         => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        // 1. Buat data organisasi (is_verified default false)
        $organization = Organization::create([
            'nama_organisasi' => $request->nama_organisasi,
            'deskripsi'       => $request->deskripsi,
            'lokasi'          => $request->lokasi,
            'website'         => $request->website,
            'is_verified'     => false, 
        ]);

        // 2. Hubungkan User dengan Organisasi tersebut
        $user->update([
            'organization_id' => $organization->id
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Permintaan upgrade berhasil dikirim. Mohon tunggu verifikasi Super Admin.',
            'data'    => $user->load('organization')
        ]);
    }

    /**
     * Get Pending Upgrades (Untuk Super Admin)
     */
    public function getPendingAdmins()
{
    // Ambil user yang punya organization_id tapi role-nya masih 'user'
    $pendingUsers = User::with('organization')
        ->whereNotNull('organization_id')
        ->where('role', 'user')
        ->get();

    // Cek jika data kosong
    if ($pendingUsers->isEmpty()) {
        return response()->json([
            'success' => true,
            'message' => 'Saat ini tidak ada permintaan verifikasi admin baru.',
            'data' => []
        ], 200);
    }

    // Jika ada datanya
    return response()->json([
        'success' => true,
        'message' => 'Daftar permintaan verifikasi admin berhasil diambil.',
        'count' => $pendingUsers->count(),
        'data' => $pendingUsers
    ], 200);
}
    /**
     * Approve Upgrade (Super Admin menyetujui)
     */
    public function approveAdmin($id)
{
    // 1. Cek apakah yang akses adalah Super Admin
    if (auth()->user()->role !== 'super_admin') {
        return response()->json(['message' => 'Akses ditolak! Anda bukan Super Admin'], 403);
    }

    $user = User::find($id);
    
    // 2. Cek apakah user ada
    if (!$user) {
        return response()->json(['message' => 'User tidak ditemukan'], 404);
    }

    // 3. PROTEKSI: Cek apakah user sudah mengisi data organisasi
    if ($user->organization_id === null) {
        return response()->json([
            'status'  => 'error',
            'message' => 'Gagal verifikasi! User ini belum mengajukan upgrade atau belum mengisi data organisasi.'
        ], 400);
    }

    // 4. Jalankan Upgrade Role & Verifikasi User
    $user->update([
        'role' => 'admin',
        'is_verified' => true
    ]);

    // 5. Verifikasi Organisasinya
    Organization::where('id', $user->organization_id)->update([
        'is_verified' => true
    ]);

    // 6. Kirim Notifikasi
    try {
        Notification::create([
            'user_id' => $user->id,
            'judul'   => 'Upgrade Premium Berhasil',
            'isi'     => 'Selamat! Akun Anda kini menjadi Admin dan organisasi Anda telah aktif.',
            'is_read' => false
        ]);
    } catch (\Exception $e) { }

    return response()->json([
        'status'  => 'success', 
        'message' => "User {$user->name} sekarang resmi menjadi Admin!"
    ]);
}
    /**
     * Ambil Notifikasi
     */
    public function getNotifications()
    {
        $notifications = Notification::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $notifications
        ]);
    }

    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        return response()->json(['status' => 'success', 'message' => 'Logout berhasil']);
    }

    public function me()
    {
        return response()->json(['status' => 'success', 'data' => auth()->user()->load('organization')]);
    }

    protected function respondWithToken($token)
    {
        return [
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => JWTAuth::factory()->getTTL() * 60,
        ];
    }
}