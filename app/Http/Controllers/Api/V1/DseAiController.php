<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseApiController as Controller;
use App\Http\Resources\Api\V1\DseAiCollection;
use App\Http\Services\Api\V1\DseAiService;
use Illuminate\Http\Request;

class DseAiController extends Controller
{
    protected DseAiService $service;

    public function __construct(DseAiService $service)
    {
        $this->service = $service;
    }

    public function listMcById($mcId)
    {
        try {
            $data = $this->service->listMcById($mcId);
            $result = new DseAiCollection($data);

            return $this->respond($result);
        } catch (\Exception $e) {
            return $this->ApiExceptionError($e->getMessage());
        }
    }

    public function listOutletByDseDate($dseId, Request $request)
    {
        try {
            $data = $this->service->listOutletByDseDate($dseId, $request);
            $result = [
                'data' => $data,
                'meta' => [
                    'status_code' => 200,
                    'success' => true,
                    'message' => 'Success get all outlet by DSE and date'
                ],
            ];

            return $this->respond($result);
        } catch (\Exception $e) {
            return $this->ApiExceptionError($e->getMessage());
        }
    }

    public function getOutletImages($outletId, Request $request)
    {
        try {
            $data = $this->service->getOutletImages($outletId, $request);
            $result = [
                'data' => $data,
                'meta' => [
                    'status_code' => 200,
                    'success' => true,
                    'message' => 'Success get all outlet images'
                ],
            ];

            return $this->respond($result);
        } catch (\Exception $e) {
            return $this->ApiExceptionError($e->getMessage());
        }
    }

    public function getDseAiComparisons($mcId)
    {
        try {
            $data = $this->service->getDseAiComparisons($mcId);
            $result = [
                'data' => $data,
                'meta' => [
                    'status_code' => 200,
                    'success' => true,
                    'message' => 'Success get all DSE AI comparisons'
                ],
            ];

            return $this->respond($result);
        } catch (\Exception $e) {
            return $this->ApiExceptionError($e->getMessage());
        }
    }

    public function getDseAiSummary($mcId, Request $request)
    {
        try {
            $data = $this->service->getDseAiSummary($mcId, $request);
            $result = [
                'data' => $data,
                'meta' => [
                    'status_code' => 200,
                    'success' => true,
                    'message' => 'Success get DSE AI summary'
                ],
            ];

            return $this->respond($result);
        } catch (\Exception $e) {
            return $this->ApiExceptionError($e->getMessage());
        }
    }

    public function getDseAiDaily($mcId, Request $request)
    {
        try {
            $data = $this->service->getDseAiDaily($mcId, $request);
            $result = [
                'data' => $data,
                'meta' => [
                    'status_code' => 200,
                    'success' => true,
                    'message' => 'Success get DSE AI daily'
                ],
            ];

            return $this->respond($result);
        } catch (\Exception $e) {
            return $this->ApiExceptionError($e->getMessage());
        }
    }

    public function getDseAiDashboard(Request $request)
    {
        try {
            $data = $this->service->getDseAiDashboard($request);
            $result = [
                'data' => $data,
                'meta' => [
                    'status_code' => 200,
                    'success' => true,
                    'message' => 'Success get DSE AI dashboard'
                ],
            ];

            return $this->respond($result);
        } catch (\Exception $e) {
            return $this->ApiExceptionError($e->getMessage());
        }
    }
}
