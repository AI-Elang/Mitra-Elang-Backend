<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseApiController as Controller;
use App\Http\Services\Api\V1\SiteService;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    protected SiteService $service;
    public function __construct(SiteService $service)
    {
        $this->service = $service;
    }

    public function listKecamatanByMc()
    {
        try {
            $data = $this->service->listKecamatanByMc();
            $result = [
                'data' => $data->transform(function ($data) {
                    return [
                        'kecamatan' => $data->kecamatan_unik,
                        'pt_name' => $data->pt_name,
                        'status' => $data->lrk,
                    ];
                }),
                'meta' => [
                    'status_code' => 200,
                    'success' => true,
                    'message' => 'Success get list Kecamatan by MC'
                ],
            ];

            return $this->respond($result);
        } catch (\Exception $e) {
            return $this->ApiExceptionError($e->getMessage());
        }
    }

    public function listSiteByKecamatan(Request $request)
    {
        try {
            $data = $this->service->listSiteByKecamatan($request);
            $result = [
                'data' => $data,
                'meta' => [
                    'status_code' => 200,
                    'success' => true,
                    'message' => 'Success get list site by PT name'
                ],
            ];

            return $this->respond($result);
        } catch (\Exception $e) {
            return $this->ApiExceptionError($e->getMessage());
        }
    }

    public function siteDetail($site_id)
    {
        try {
            $data = $this->service->siteDetail($site_id);
            $result = [
                'data' => $data,
                'meta' => [
                    'status_code' => 200,
                    'success' => true,
                    'message' => 'Success get site detail'
                ],
            ];

            return $this->respond($result);
        } catch (\Exception $e) {
            return $this->ApiExceptionError($e->getMessage());
        }
    }

    public function siteDetailRevenue($site_id)
    {
        try {
            $data = $this->service->siteDetailRevenue($site_id);
            $result = [
                'data' => $data,
                'meta' => [
                    'status_code' => 200,
                    'success' => true,
                    'message' => 'Success get site detail revenue'
                ],
            ];

            return $this->respond($result);
        } catch (\Exception $e) {
            return $this->ApiExceptionError($e->getMessage());
        }
    }

    public function siteDetailRgu($site_id, Request $request)
    {
        try {
            $data = $this->service->siteDetailRgu($site_id, $request);
            $result = [
                'data' => $data,
                'meta' => [
                    'status_code' => 200,
                    'success' => true,
                    'message' => 'Success get site detail RGU'
                ],
            ];

            return $this->respond($result);
        } catch (\Exception $e) {
            return $this->ApiExceptionError($e->getMessage());
        }
    }

    public function siteDetailGa($site_id)
    {
        try {
            $data = $this->service->siteDetailGa($site_id);
            $result = [
                'data' => $data,
                'meta' => [
                    'status_code' => 200,
                    'success' => true,
                    'message' => 'Success get site detail GA'
                ],
            ];

            return $this->respond($result);
        } catch (\Exception $e) {
            return $this->ApiExceptionError($e->getMessage());
        }
    }

    public function siteDetailVlr($site_id)
    {
        try {
            $data = $this->service->siteDetailVlr($site_id);
            $result = [
                'data' => $data,
                'meta' => [
                    'status_code' => 200,
                    'success' => true,
                    'message' => 'Success get site detail VLR'
                ],
            ];

            return $this->respond($result);
        } catch (\Exception $e) {
            return $this->ApiExceptionError($e->getMessage());
        }
    }

    public function siteDetailOutlet($site_id)
    {
        try {
            $data = $this->service->siteDetailOutlet($site_id);
            $result = [
                'data' => $data,
                'meta' => [
                    'status_code' => 200,
                    'success' => true,
                    'message' => 'Success get site detail Outlet'
                ],
            ];

            return $this->respond($result);
        } catch (\Exception $e) {
            return $this->ApiExceptionError($e->getMessage());
        }
    }

    public function getSiteDashboard(Request $request)
    {
        try {
            $data = $this->service->getSiteDashboard($request);
            $result = [
                'data' => $data,
                'meta' => [
                    'status_code' => 200,
                    'success' => true,
                    'message' => 'Success get site dashboard'
                ],
            ];

            return $this->respond($result);
        } catch (\Exception $e) {
            return $this->ApiExceptionError($e->getMessage());
        }
    }

}
