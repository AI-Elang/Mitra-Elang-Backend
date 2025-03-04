<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseApiController as Controller;
use App\Http\Services\Api\V1\OutletService;
use Illuminate\Http\Request;

class OutletController extends Controller
{
    protected OutletService $service;
    public function __construct(OutletService $service)
    {
        $this->service = $service;
    }

    public function listOutletByPartnerName($pt, Request $request)
    {
        try {
            $data = $this->service->listOutletByPartnerName($pt, $request);
            $result = [
                'data' => $data,
                'meta' => [
                    'status_code' => 200,
                    'success' => true,
                    'message' => 'Success get list outlet by partner name'
                ],
            ];

            return $this->respond($result);
        } catch (\Exception $e) {
            return $this->ApiExceptionError($e->getMessage());
        }
    }

    public function dropdown(Request $request)
    {
        try {
            $data = $this->service->dropdown($request);
            $result = [
                'data' => $data,
                'meta' => [
                    'status_code' => 200,
                    'success' => true,
                    'message' => 'Success get dropdown data'
                ],
            ];

            return $this->respond($result);
        } catch (\Exception $e) {
            return $this->ApiExceptionError($e->getMessage());
        }
    }

    public function listOutletLocation()
    {
        try {
            $data = $this->service->listOutletLocation();
            $result = [
                'data' => $data,
                'meta' => [
                    'status_code' => 200,
                    'success' => true,
                    'message' => 'Success get list outlet location'
                ],
            ];

            return $this->respond($result);
        } catch (\Exception $e) {
            return $this->ApiExceptionError($e->getMessage());
        }
    }

    public function outletDetail($qrCode)
    {
        try {
            $data = $this->service->outletDetail($qrCode);
            $result = [
                'data' => [
                    'qr_code' => $data->qr_code,
                    'site_id' => $data->site_id,
                    'outlet_name' => $data->outlet_name,
                    'partner_name' => $data->partner_name,
                    'category' => $data->category,
                    'brand' => $data->brand,
                    'latitude' => $data->latitude,
                    'longitude' => $data->longitude,
                    'mtd_dt' => $data->mtd_dt,
                    'status' => $data->status,
                ],
                'meta' => [
                    'status_code' => 200,
                    'success' => true,
                    'message' => 'Success get outlet detail'
                ],
            ];

            return $this->respond($result);
        } catch (\Exception $e) {
            return $this->ApiExceptionError($e->getMessage());
        }
    }

    public function outletDetailGa($qrCode)
    {
        try {
            $data = $this->service->outletDetailGa($qrCode);
            $result = [
                'data' => $data,
                'meta' => [
                    'status_code' => 200,
                    'success' => true,
                    'message' => 'Success get outlet detail'
                ],
            ];

            return $this->respond($result);
        } catch (\Exception $e) {
            return $this->ApiExceptionError($e->getMessage());
        }
    }

    public function outletDetailSec($qrCode)
    {
        try {
            $data = $this->service->outletDetailSec($qrCode);
            $result = [
                'data' => $data,
                'meta' => [
                    'status_code' => 200,
                    'success' => true,
                    'message' => 'Success get outlet detail'
                ],
            ];

            return $this->respond($result);
        } catch (\Exception $e) {
            return $this->ApiExceptionError($e->getMessage());
        }
    }

    public function outletDetailSupply($qrCode)
    {
        try {
            $data = $this->service->outletDetailSupply($qrCode);
            $result = [
                'data' => $data,
                'meta' => [
                    'status_code' => 200,
                    'success' => true,
                    'message' => 'Success get outlet detail'
                ],
            ];

            return $this->respond($result);
        } catch (\Exception $e) {
            return $this->ApiExceptionError($e->getMessage());
        }
    }

    public function outletDetailDemand($qrCode)
    {
        try {
            $data = $this->service->outletDetailDemand($qrCode);
            $result = [
                'data' => $data,
                'meta' => [
                    'status_code' => 200,
                    'success' => true,
                    'message' => 'Success get outlet detail'
                ],
            ];

            return $this->respond($result);
        } catch (\Exception $e) {
            return $this->ApiExceptionError($e->getMessage());
        }
    }

}
