<?php

namespace App\Http\Services\Api\V1\Auth;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthService
{

    public function login($request)
    {
        if (!$request->username || !$request->password) {
            throw new Exception('Username and password are required', 400);
        }

        // Attempt login terlebih dahulu sebelum mengatur TTL
        if ($token = auth('api')->attempt(['username' => $request->username, 'password' => $request->password])) {
            $user = auth('api')->user();

            if ($user->role_label == 'DSE') {
                throw new Exception('Unauthorized', 401);
            }

            // Hitung waktu hingga pukul 00:00 besok
            $now = Carbon::now('Asia/Jakarta');
            $tomorrowMidnight = Carbon::tomorrow('Asia/Jakarta')->startOfDay();
            $minutesUntilMidnight = $now->diffInMinutes($tomorrowMidnight);

            // Set TTL sebelum membuat ulang token
            JWTAuth::factory()->setTTL($minutesUntilMidnight);

            // Generate token baru dengan TTL yang diperbarui
            $token = JWTAuth::fromUser($user);

            // Ambil territory user
            $territory = DB::table('territory_dashboards')
                ->where('id', $user->territory_id)
                ->where('is_active', 1)
                ->first();
            $user['territory'] = $territory->name ?? null;

            // Periksa apakah akun aktif
            if (!$user->is_active) {
                throw new Exception('Account is deactivated', 403);
            }

            // Gunakan token yang sudah dibuat ulang
            $user['token'] = $token;

            return $user;
        } else {
            throw new Exception('Wrong email or password', 400);
        }
    }

    public function logout()
    {
        auth('api')->logout();
    }
}
