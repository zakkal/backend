<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    /**
     * Google Login (Sudah diperbaiki dengan stateless untuk Flutter)
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
            // PENTING: Tambahkan ->stateless() agar Socialite tidak mencari session/cookie
            // Gunakan userFromToken untuk ID Token yang dikirim Flutter
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
                // Pesan error ini membantu debugging jika token salah/expired
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
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'role'     => 'required|in:admin,user',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $isVerified = ($request->role === 'user') ? true : false;

        $user = User::create([
            'name'        => $request->name,
            'username'    => explode('@', $request->email)[0] . rand(10, 99),
            'email'       => $request->email,
            'password'    => Hash::make($request->password),
            'role'        => $request->role,
            'is_verified' => $isVerified, 
        ]);

        if ($user->role === 'admin') {
            \App\Models\Organization::create([
                'user_id' => $user->id,
                'nama_organisasi' => 'Organisasi ' . $user->name,
                'deskripsi' => 'Deskripsi organisasi baru.',
            ]);
        }

        $message = ($user->role === 'admin') 
            ? 'Registrasi Admin berhasil. Mohon tunggu verifikasi Programmer.' 
            : 'Registrasi User berhasil. Silahkan login.';

        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'data'    => $user->load('organization')
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

            if ($user->role === 'admin' && !$user->is_verified) {
                JWTAuth::invalidate($token);
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Akun Admin Anda belum diverifikasi oleh Programmer.'
                ], 403);
            }

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
     * Admin Approval & Pending List
     */
    public function getPendingAdmins()
    {
        if (auth()->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Akses ditolak!'], 403);
        }

        $pending = User::where('role', 'admin')->where('is_verified', false)->get();
        return response()->json(['status' => 'success', 'data' => $pending]);
    }

    public function approveAdmin($id)
    {
        if (!auth()->check()) {
            return response()->json(['message' => 'Silahkan login terlebih dahulu'], 401);
        }

        if (auth()->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Akses ditolak! Anda bukan Super Admin'], 403);
        }

        $user = User::find($id);
        if (!$user) return response()->json(['message' => 'User tidak ditemukan'], 404);

        $user->update(['is_verified' => true]);

        try {
            Notification::create([
                'user_id' => $user->id,
                'judul'   => 'Akun Diverifikasi',
                'isi'     => 'Selamat! Akun organisasi kamu telah aktif.',
                'is_read' => false
            ]);
        } catch (\Exception $e) {
            // Log error jika diperlukan
        }

        return response()->json(['status' => 'success', 'message' => "Akun {$user->name} aktif!"]);
    }

    /**
     * Ambil Notifikasi
     */
    public function getNotifications()
    {
        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $notifications = Notification::where('user_id', auth()->id())
                            ->orderBy('created_at', 'desc')
                            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $notifications
        ]);
    }

    /**
     * Auth Helpers
     */
    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        return response()->json(['status' => 'success', 'message' => 'Logout berhasil']);
    }

    public function me()
    {
        return response()->json(['status' => 'success', 'data' => auth()->user()]);
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