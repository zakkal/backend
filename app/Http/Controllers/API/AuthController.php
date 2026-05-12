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
     * Register Manual (Sekarang defaultnya adalah Role: User)
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

        // Semua registrasi baru defaultnya adalah user biasa
        $user = User::create([
            'name'        => $request->name,
            'username'    => explode('@', $request->email)[0] . rand(10, 99),
            'email'       => $request->email,
            'password'    => Hash::make($request->password),
            'role'        => 'user',
            'is_verified' => true, // User biasa langsung aktif
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

            // Jika user sedang menunggu verifikasi upgrade (misal kamu tambah status is_verified di tabel users)
            // Tapi untuk sekarang kita asumsikan user bisa login meski belum diverifikasi organisasinya
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
     * Get Pending Upgrades (Untuk Super Admin melihat siapa yang request jadi premium)
     */
    public function getPendingAdmins()
    {
        if (auth()->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Akses ditolak!'], 403);
        }

        // Cari user yang sudah isi organization_id tapi rolenya masih 'user'
        $pending = User::with('organization')
            ->where('role', 'user')
            ->whereNotNull('organization_id')
            ->get();

        return response()->json(['status' => 'success', 'data' => $pending]);
    }

    /**
     * Approve Upgrade (User -> Admin)
     */
    public function approveAdmin($id)
    {
        if (auth()->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Akses ditolak! Anda bukan Super Admin'], 403);
        }

        $user = User::find($id);
        if (!$user) return response()->json(['message' => 'User tidak ditemukan'], 404);

        // 1. Upgrade Role & Verifikasi User
        $user->update([
            'role' => 'admin',
            'is_verified' => true
        ]);

        // 2. Verifikasi Organisasinya juga
        if ($user->organization_id) {
            Organization::where('id', $user->organization_id)->update([
                'is_verified' => true
            ]);
        }

        // 3. Kirim Notifikasi
        try {
            Notification::create([
                'user_id' => $user->id,
                'judul'   => 'Upgrade Premium Berhasil',
                'isi'     => 'Selamat! Akun Anda kini menjadi Admin dan organisasi Anda telah aktif.',
                'is_read' => false
            ]);
        } catch (\Exception $e) { }

        return response()->json(['status' => 'success', 'message' => "User {$user->name} sekarang menjadi Admin!"]);
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