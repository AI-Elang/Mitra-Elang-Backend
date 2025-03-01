<?php

namespace App\Http\Services\Api\V1\Dashboard;

use Illuminate\Support\Facades\DB;

class DashboardService
{
    private function castToFloat($value)
    {
        if (is_numeric($value) && strpos($value, '.') !== false) {
            return (float)$value;
        }
        return is_numeric($value) ? (int)$value : $value;
    }

    /**
     * Method untuk meng-cast nilai menjadi float jika memang angka float, selain itu dikembalikan seperti apa adanya.
     */

    public function getRegionId($mcId)
    {
        $branchId = DB::table('territory_dashboards')
            ->select('id', 'id_secondary')
            ->where('id', $mcId)
            ->first();

        $areaId = DB::table('territory_dashboards')
            ->select('id_secondary')
            ->where('id', $branchId->id_secondary)
            ->first();

        $regionId = DB::table('territory_dashboards')
            ->select('id_secondary')
            ->where('id', $areaId->id_secondary)
            ->first();
        return $regionId->id_secondary;
    }

    /**
     * Method untuk mengambil id region dari id mc yang diberikan.
     */

    public function dashboard()
    {
        $mcId = auth('api')->user()->territory_id;
        $regionId = $this->getRegionId($mcId);

        $parameterRegion = DB::table('region_parameter')
            ->select('parameter_id')
            ->where('is_active', True)
            ->where('territory_id', $regionId)
            ->get();

        $parameter = DB::table('parameter_mitra')
            ->select('id', 'name', 'is_active')
            ->whereIn('parameter_mitra.id', $parameterRegion->pluck('parameter_id'))
            ->where('parameter_mitra.is_active', True)
            ->get();

        $subparameter = DB::table('subparameter_mitra')
            ->select('id', 'name', 'parameter_id', 'tabel', 'kolom')
            ->whereIn('parameter_id', $parameter->pluck('id'))
            ->where('subparameter_mitra.is_active', true)
            ->get();

        $achievementData = DB::table('achievement')->get();

        // Ambil nilai score_kpi dan score_compliance dari achievement dengan casting ke float
        $kpiScore = $this->castToFloat($achievementData->pluck('score_kpi')->first());
        $complianceScore = $this->castToFloat($achievementData->pluck('score_compliance')->first());

        // Buat mapping subparameter ke achievement data dengan casting ke float jika perlu
        $subparameter = $subparameter->map(function ($sub) use ($achievementData) {
            if ($sub->tabel === 'achievement') {
                $sub->value = $this->castToFloat($achievementData->pluck($sub->kolom)->first());
            }
            return $sub;
        });

        $parameter = $parameter->map(function ($param) use ($subparameter, $kpiScore, $complianceScore) {
            if ($param->name === 'KPI') {
                $param->score_kpi = $kpiScore;
            }
            if ($param->name === 'COMPLIANCE') {
                $param->score_compliance = $complianceScore;
            }

            $param->subparameters = $subparameter->where('parameter_id', $param->id)->values();

            return $param;
        });

        return $parameter;
    }

    public function account()
    {
        $mcId = auth('api')->user()->territory_id;
        $mc_name = DB::table('territory_dashboards')
            ->select('name')
            ->where('id', $mcId)
            ->first();

        $profile = DB::table('mitra_table')
            ->select('id_mitra', 'nama_mitra', 'nama_owner')
            ->where('is_active', True)
            ->where('mc', $mc_name->name)
            ->get();
        return $profile;
    }
}

