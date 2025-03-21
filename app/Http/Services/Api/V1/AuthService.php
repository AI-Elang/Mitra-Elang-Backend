<?php

namespace App\Http\Services\Api\V1;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthService
{

    public function login($request)
    {
        if (!$request->username || !$request->password) {
            throw new Exception('Username dan password harus diisi', 400);
        }

        // Attempt login terlebih dahulu sebelum mengatur TTL
        if ($token = auth('api')->attempt(['username' => $request->username, 'password' => $request->password])) {
            $user = auth('api')->user();

            if ($user->role != 6 && $user->role != 7) {
                throw new Exception('Anda tidak memiliki akses', 401);
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
                throw new Exception('Akun tidak aktif', 403);
            }

            if ($user->role == 6) {
                $filtervalue = 'MC';
            }
            else if ($user->role == 7) {
                $filtervalue = 'BSM';
            }

            $nama_mitra = DB::connection('pgsql')->table('mitra_table')
                ->select('id_mitra',
                    'nama_mitra')
                ->where('is_active', true)
                ->where('id_mitra', $request->username)
                ->first()
                ->nama_mitra; // Ambil satu baris data

            $isnotnull =  DB::connection('pgsql2')->table('ELANG_MTD_PARTNER')
                ->where('PARTNER_NAME', $nama_mitra)
                ->where('STATUS', 'VALID')
                ->whereNotNull($filtervalue);

            if($isnotnull)
            {
                throw new Exception('Anda tidak memiliki akses', 401);
            }

            // Gunakan token yang sudah dibuat ulang
            $user['token'] = $token;

            return $user;
        } else {
            throw new Exception('Username atau password salah', 400);
        }
    }

    public function logout()
    {
        auth('api')->logout();
    }
}
