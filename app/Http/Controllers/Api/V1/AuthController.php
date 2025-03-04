<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseApiController as Controller;
use App\Http\Services\Api\V1\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected AuthService $service;

    public function __construct(AuthService $service)
    {
        $this->service = $service;
    }

    public function login(Request $request)
    {
        try {
            $data = $this->service->login($request);
            return $this->respond([
                'data' => [
                    'id' => $data['id'],
                    'username' => $data['username'],
                    'territory' => $data['territory'],
                    'brand' => $data['brand'],
                    'role' => $data['role'],
                ],
                'token' => $data['token'],
                'meta' => [
                    'status_code' => 200,
                    'success' => true,
                    'message' => 'Success Login',
                ],
            ]);
        } catch (\Exception $e) {
            return $this->ApiExceptionError($e->getMessage());
        }
    }

    public function logout()
    {
        try {
            $this->service->logout();
            return $this->respond([
                'meta' => [
                    'status_code' => 200,
                    'success' => true,
                    'message' => 'Success Logout',
                ],
            ]);
        } catch (\Exception $e) {
            return $this->ApiExceptionError($e->getMessage());
        }
    }
}
