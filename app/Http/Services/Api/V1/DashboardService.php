<?php

namespace App\Http\Services\Api\V1;

use Carbon\Carbon;
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
        $username = auth('api')->user()->username;
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
            // Fetch parameter value safely
            $parameterValue = DB::table($param->parameter_table)
                ->select($param->parameter_column, 'last_update')
                ->where('id_mitra', $username)
                ->first();

            if (!isset($data[$param->parameter_id])) {
                $data[$param->parameter_id] = [
                    'id' => $param->parameter_id,
                    'name' => $param->parameter_name,
                    'is_active' => $param->parameter_is_active,
                    'last_update' => isset($parameterValue->last_update)
                        ? Carbon::parse($parameterValue->last_update)->format('d-m-Y')
                        : null, // Format date if exists
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

            // Fetch all subparameter values safely
            $subparameterValues = DB::table($param->subparameter_table)
                ->select($param->subparameter_column, 'last_update')
                ->where('id_mitra', $username)
                ->get();

            foreach ($subparameterValues as $subparamValue) {
                $data[$param->parameter_id]['subparameters'][] = [
                    'id' => $param->subparameter_id,
                    'name' => $param->subparameter_name,
                    'parameter_id' => $param->parameter_id,
                    'last_update' => isset($subparamValue->last_update)
                        ? Carbon::parse($subparamValue->last_update)->format('d-m-Y')
                        : null, // Format date if exists
                    'value' => isset($subparamValue->{$param->subparameter_column})
                        ? (is_numeric($subparamValue->{$param->subparameter_column})
                            ? (is_float($floatValue = (float) $subparamValue->{$param->subparameter_column})
                                ? round($floatValue, 1)
                                : $subparamValue->{$param->subparameter_column})
                            : $subparamValue->{$param->subparameter_column})
                        : 0.0,
                ];
            }
        }

        return array_values($data);
    }



    public function insentif()
    {
        $username = auth('api')->user()->username;
        $mcid = auth('api')->user()->territory_id;
        $mc_name = DB::table('territory_dashboards')
            ->select('name')
            ->where('id', $mcid)
            ->first();
        $insentif = DB::table('total_achievement')
            ->select('item', 'nilai', 'status', 'tipe', 'last_update')
            ->where('id_mitra', $username)
            ->where('MC', $mc_name->name)
            ->get()
            ->map(function ($item) {
                // Convert 'nilai' based on 'status'
                if (is_numeric($item->nilai)) {
                    if (ctype_digit($item->nilai)) {
                        $item->nilai = (int) $item->nilai; // Convert to integer
                    } else {
                        $item->nilai = (float) $item->nilai; // Convert to float
                    }
                }
                if (!empty($item->last_update)) {
                    $item->last_update = Carbon::parse($item->last_update)->format('d-m-Y');
                }

                return $item;
            });

        return $insentif;
    }

    public function profile()
    {
        $username = auth('api')->user()->username;
        $mcid = auth('api')->user()->territory_id;
        $mc_name = DB::table('territory_dashboards')
            ->select('name')
            ->where('id', $mcid)
            ->first();
        $profile = DB::table('elang_mitra_sampah')
            ->where('id_mitra', $username)
            ->where('MC', $mc_name->name)
            ->orderBy('URUTAN') // Order by 'urutan' in ascending order
            ->get()
            ->map(function ($item) {
                foreach ($item as $key => $value) {
                    if (is_numeric($value)) {
                        // Check if value is an integer (no decimal point)
                        if (ctype_digit(strval($value))) {
                            $item->$key = (int) $value; // Convert to integer
                        } else {
                            $item->$key = (float) $value; // Convert to float
                        }
                    }
                }

                // Format 'last_update' to DD-MM-YYYY if it exists
                if (!empty($item->mtd_date)) {
                    $item->mtd_date = Carbon::parse($item->mtd_date)->format('d-m-Y');
                }
                if (!empty($item->last_update)) {
                    $item->last_update = Carbon::parse($item->last_update)->format('d-m-Y');
                }

                return $item; // Return modified item after processing all fields
            });

        return $profile;
    }

    public function sliders($request)
    {
        $params = $request->status;

        return DB::table('sliders')
            ->select([
                'id',
                'name',
                'image',
                'link_to',
                'is_active',
                'order',
                'status',
            ])
            ->when(!$params, function ($query) use ($request) {
                return $query->where('is_active', true);
            })
            ->when($params == 'active', function ($query) use ($request) {
                return $query->where('is_active', true);
            })
            ->when($params == 'inactive', function ($query) use ($request) {
                return $query->where('is_active', false);
            })
            ->when($params == 'all', function ($query) use ($request) {
                return $query;
            })
            ->whereIn('for_elang', ['all'])
            ->orderBy('order')
            ->get();
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

