<?php

namespace App\Http\Services\Api\V1\Dashboard;

use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function dashboard()
    {
        $userTerritory = auth('api')->user()->territory_id;
        $parameterRegion = DB::table('region_parameter')
            ->select('parameter_id')
            ->where('is_active', True)
            ->where('territory_id', $userTerritory)
            ->get();

        $parameter = DB::table('parameter_mitra')
            ->select('id', 'name')
            ->whereIn('parameter_mitra.id', $parameterRegion->pluck('parameter_id'))
            ->where('parameter_mitra.is_active', True)
            ->get();

        $subparameter = DB::table('subparameter_mitra')
            ->select('id', 'name', 'parameter_id', 'tabel', 'kolom')
            ->whereIn('parameter_id', $parameter->pluck('id'))
            ->where('subparameter_mitra.is_active', true)
            ->get();

        $achievementData = DB::table('achievement')->get();

        // Ambil nilai score_kpi dan score_compliance dari achievement
        $kpiScore = $achievementData->pluck('score_kpi')->first();
        $complianceScore = $achievementData->pluck('score_compliance')->first();

        // Buat mapping subparameter ke achievement data
        $subparameter = $subparameter->map(function ($sub) use ($achievementData) {
            if ($sub->tabel === 'achievement') {
                $sub->value = $achievementData->pluck($sub->kolom)->first();
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

    public function profile()
    {
        $profile = DB::table('mitra_table')
            ->select('id_mitra','nama_mitra','nama_owner')
            ->where('is_active', True)
            ->get();
        return $profile;
    }
}
