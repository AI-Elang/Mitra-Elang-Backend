<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\BaseApiController as Controller;
use App\Http\Services\Api\V1\Dashboard\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected DashboardService $service;
    public function __construct(DashboardService $service)
    {
        $this->service = $service;
    }

    public function dashboard()
    {
        try{
            $data = $this->service->dashboard();
            return $this->respond([
                'data' => $data,
                'meta' => [
                    'status_code' => 200,
                    'success' => true,
                    'message' => 'Success Get Dashboard',
                ],
            ]);
        } catch (\Exception $e) {
            return $this->ApiExceptionError($e->getMessage());
        }
    }

    public function profile()
    {
        try {
            $data = $this->service->profile();
            return $this->respond([
                'data' => $data,
                'meta' => [
                    'status_code' => 200,
                    'success' => true,
                    'message' => 'Success Get Profile',
                ],
            ]);
        } catch (\Exception $e) {
            return $this->ApiExceptionError($e->getMessage());
        }
    }
}
