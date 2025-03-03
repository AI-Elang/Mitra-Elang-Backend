<?php

namespace App\Http\Services\Api\V1\Dashboard;

use Illuminate\Support\Facades\DB;

class DashboardService
{

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
        $useRole = auth('api')->user()->role_label;
        $regionId = $this->getRegionId($mcId);

        $parameterRegion = DB::table('region_parameter')
            ->where('is_active', true)
            ->where('territory_id', $regionId)
            ->where('type', $useRole)
            ->pluck('parameter_id')
            ->toArray();

        $parameters = DB::table('parameter_mitra as p')
            ->join('subparameter_mitra as sp', 'p.id', '=', 'sp.parameter_id')
            ->select(
                'p.id as parameter_id',
                'p.name as parameter_name',
                'p.kolom as parameter_column',
                'sp.id as subparameter_id',
                'sp.name as subparameter_name',
                'sp.kolom as subparameter_column',
                'p.tabel as parameter_table',
                'sp.tabel as subparameter_table',
                'p.is_active as parameter_is_active',
            )
            ->whereIn('p.id', $parameterRegion)
            ->get();

        $data = [];

        foreach ($parameters as $param) {
            // Cek apakah parameter sudah ada dalam array data
            if (!isset($data[$param->parameter_id])) {
                // Ambil nilai parameter
                $parameterValue = DB::table($param->parameter_table)
                    ->select($param->parameter_column)
                    ->first();

                $data[$param->parameter_id] = [
                    'id' => $param->parameter_id,
                    'name' => $param->parameter_name,
                    'is_active' => $param->parameter_is_active,
                    $param->parameter_column => isset($parameterValue->{$param->parameter_column}) ? (float)number_format((float) $parameterValue->{$param->parameter_column}, 1) : "0.0",
                    'subparameters' => []
                ];
            }

            // Ambil nilai subparameter
            $subparameterValue = DB::table($param->subparameter_table)
                ->select($param->subparameter_column)
                ->first();

            $data[$param->parameter_id]['subparameters'][] = [
                'id' => $param->subparameter_id,
                'name' => $param->subparameter_name,
                'parameter_id' => $param->parameter_id,
                'tabel' => $param->subparameter_table,
                'kolom' => $param->subparameter_column,
                'value' => isset($subparameterValue->{$param->subparameter_column}) ? $subparameterValue->{$param->subparameter_column} : null
            ];
        }

        return array_values($data);
    }


    public function account()
    {
        $username = auth('api')->user()->username;
        $mcId = auth('api')->user()->territory_id;
        $mc_name = DB::table('territory_dashboards')
            ->select('name')
            ->where('id', $mcId)
            ->first();

        $profile = DB::table('mitra_table')
            ->select('id_mitra', 'nama_mitra', 'nama_owner')
            ->where('is_active', True)
            ->where('id_mitra', $username)
            ->get();
        return $profile;
    }
}

