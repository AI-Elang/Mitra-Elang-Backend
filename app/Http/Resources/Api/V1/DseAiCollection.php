<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class DseAiCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->transformCollection($this->collection),
            'meta' => [
                'status_code' => 200,
                'success' => true,
                'message' => 'Success get all DSE from an MC'
            ],
        ];
    }

    public function transformData($data): array
    {
        return [
            'id' => $data->dse_id,
            'name' => $data->dse_name ?? '-',
            'mc_id' => (int) $data->territory_id ?? '-',
            'mc_name' => $data->territory_name,
            'pjp' => isset($data->pjp) ? $this->formatNumber($data->pjp) : '0',
            'actual_pjp' => isset($data->actual_pjp) ? $this->formatNumber($data->actual_pjp) : '0',
            'zero' => isset($data->zero) ? $this->formatNumber($data->zero) : '0',
            'sp' => isset($data->sp) ? $this->formatNumber($data->sp) : '0',
            'vou' => isset($data->vou) ? $this->formatNumber($data->vou) : '0',
            'salmo' => isset($data->salmo) ? $this->formatNumber($data->salmo) : '0',
            'mtd_dt' => $data->mtd_dt ?? '-',
            'checkin' => $data->checkin ?? '-',
            'checkout' => $data->checkout ?? '-',
            'duration' => $data->duration ?? '-',
        ];
    }

    private function transformCollection($collection)
    {
        return $collection->transform(function ($data) {
            return $this->transformData($data);
        });
    }

    private function formatNumber($number)
    {
        if ($number == null || $number == 0) {
            return '0';
        }

        return number_format($number, 0, ',', '.');
    }
}

