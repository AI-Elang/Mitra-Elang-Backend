<?php

namespace App\Http\Services\Api\V1\Dashboard;

use Illuminate\Support\Facades\DB;

class DashboardService
{

    public function getRegionId($mcId)
    {
        return DB::table('territory_dashboards as t1')
            ->join('territory_dashboards as t2', 't1.id_secondary', '=', 't2.id')
            ->join('territory_dashboards as t3', 't2.id_secondary', '=', 't3.id')
            ->where('t1.id', $mcId)
            ->value('t3.id_secondary');
    }
    /**
     * Method untuk mengambil id region dari id mc yang diberikan.
     */

    public function dashboard()
    {
        $mcId = auth('api')->user()->territory_id;
        $useRole = auth('api')->user()->role_label;
        $regionId = $this->getRegionId($mcId);

        $parameters = DB::table('parameter_mitra as p')
            ->join('subparameter_mitra as sp', 'p.id', '=', 'sp.parameter_id')
            ->join('region_parameter as rp', 'p.id', '=', 'rp.parameter_id')
            ->select(
                'p.id as parameter_id',
                'p.name as parameter_name',
                'p.kolom as parameter_column',
                'sp.id as subparameter_id',
                'sp.name as subparameter_name',
                'sp.kolom as subparameter_column',
                'p.tabel as parameter_table',
                'sp.tabel as subparameter_table',
                'p.is_active as parameter_is_active'
            )
            ->where('rp.is_active', true)
            ->where('sp.is_active', true)
            ->where('p.is_active', true)
            ->where('rp.territory_id', $regionId)
            ->where('rp.type', $useRole)
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
                    'value' => isset($parameterValue->{$param->parameter_column})
                        ? (is_numeric($parameterValue->{$param->parameter_column})
                            ? (is_float($floatValue = (float) $parameterValue->{$param->parameter_column})
                                ? round($floatValue, 1)
                                : $parameterValue->{$param->parameter_column})
                            : $parameterValue->{$param->parameter_column})
                        : 0.0,
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
                'value' => isset($subparameterValue->{$param->subparameter_column})
                    ? (is_numeric($subparameterValue->{$param->subparameter_column})
                        ? (is_float($floatValue = (float) $subparameterValue->{$param->subparameter_column})
                            ? round($floatValue, 1)
                            : $subparameterValue->{$param->subparameter_column})
                        : $subparameterValue->{$param->subparameter_column})
                    : 0.0,
            ];
        }
        return array_values($data);
    }


    public function account()
    {
        $username = auth('api')->user()->username;

        $profile = DB::table('mitra_table')
            ->select('id_mitra', 'nama_mitra', 'nama_owner', 'type')
            ->where('is_active', True)
            ->where('id_mitra', $username)
            ->get();
        return $profile;
    }
}

