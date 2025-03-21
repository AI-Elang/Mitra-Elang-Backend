<?php

namespace App\Http\Services\Api\V1;

use Carbon\Carbon;
use Illuminate\Http\Request;
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

    public function dashboard(Request $request)
    {
        $mcId = auth('api')->user()->territory_id;
        $useRole = auth('api')->user()->role_label;
        $role = auth('api')->user()->role;
        $username = auth('api')->user()->username;
        $branch = $request->get('branch');

        if ($role == 6) {
            $usernameFilter = auth('api')->user()->username;
        }
        if ($role == 7) {
            $usernameFilter = DB::connection('pgsql')->table('mitra_table')
                ->select('nama_mitra')
                ->where('id_mitra', $username)
                ->first()
                ->nama_mitra;
        }
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
                ->where('id_mitra', $usernameFilter)
                ->when($role == 7, function ($query) use ($branch) {
                    return $query->where('territory', $branch);
                })
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
                ->where('id_mitra', $usernameFilter)
                ->when($role == 7, function ($query) use ($branch) {
                    return $query->where('territory', $branch);
                })
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
            ->select('item', 'nilai', 'tipe', 'last_update')
            ->where('id_mitra', $username)
            ->where('MC', $mc_name->name)
            ->get()
            ->map(function ($item) {
                // Convert 'nilai' based on 'status'
                if (is_numeric($item->nilai)) {
                    if (ctype_digit($item->nilai)) {
                        $item->nilai = (int) $item->nilai; // Convert to integer
                    } else {
                        $item->nilai = round($item->nilai,1);// Convert to float
                    }
                }
                if (!empty($item->last_update)) {
                    $item->last_update = Carbon::parse($item->last_update)->format('d-m-Y');
                }

                return $item;
            });

        return $insentif;
    }

    public function profile(Request $request)
    {
        $username = auth('api')->user()->username;
        $mcid = auth('api')->user()->territory_id;
        $role = auth('api')->user()->role;
        $branch = $request->get('branch');

        $pt_name = DB::table('mitra_table')
            ->select('nama_mitra')
            ->where('id_mitra', $username)
            ->first()
            ->nama_mitra;

        $mc_name = DB::table('territory_dashboards')
            ->select('name')
            ->where('id', $mcid)
            ->first();
        $profile = DB::table('elang_mitra_sampah')

            ->orderBy('URUTAN'); // Order by 'URUTAN' in ascending order

//        dd($profile->get());
        if ($role == 7) {
            $profile->where('MC', $branch)
                    ->where('id_mitra', $pt_name);
        } else if ($role == 6) {
            $profile->where('MC', $mc_name->name)
                    ->where('id_mitra', $username);
        }

// Eksekusi query dan ambil datanya
        $profileData = $profile->get()->map(function ($item) {
            $item->target = !empty($item->target) ? (int) $item->target : 0;
            $item->mtd = !empty($item->mtd) ? (int) $item->mtd : 0;
            $item->GROWTH = !empty($item->GROWTH) ? round($item->GROWTH, 2) : 0;
            $item->achievement = !empty($item->achievement) ? round($item->achievement, 2) : 0;
            $item->URUTAN = !empty($item->URUTAN) ? (int) $item->URUTAN : 0;

            // Format 'mtd_date' dan 'last_update' jika ada
            $item->mtd_date = !empty($item->mtd_date) ? Carbon::parse($item->mtd_date)->format('d-m-Y') : '00-00-0000';
            $item->last_update = !empty($item->last_update) ? Carbon::parse($item->last_update)->format('d-m-Y') : '00-00-0000';

            return $item;
        });

        return $profileData;
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


    public function account(Request $request)
    {
        $username = auth('api')->user()->username;
        $roleLabel = auth('api')->user()->role_label;
        $mc = auth('api')->user()->territory_id;

        $branch = $request->get('branch');

        $mc_name = DB::table('territory_dashboards')
            ->select('name')
            ->where('id', $mc)
            ->first()
            ->name;

        // Ambil data dari database pertama
        $profile = DB::connection('pgsql')->table('mitra_table')
            ->select('id_mitra',
                'nama_mitra',
                'type',
                DB::raw('CAST(0 AS INTEGER) as site_count'))
            ->where('is_active', true)
            ->where('id_mitra', $username)
            ->first(); // Ambil satu baris data

        if (!$profile) {
            return 'Profile data not found';
        }

        if ($roleLabel === 'MPC' || $roleLabel === 'MP3' || $roleLabel === '3KIOSK)') {
            if (substr($profile->nama_mitra, -4) === ' PT ') {
                $filter = substr($profile->nama_mitra, 0, -4) . ', PT';
            } else {
                $filter = $profile->nama_mitra;
            }


        } else if ($roleLabel === 'MITRAIM3') {
                $filter = $username;
        }else {
            // Optional: decide what the default filter should be
            $filter = $profile->nama_mitra; // or handle error
        }



        // Ambil data dari database kedua
        $site = DB::connection('pgsql2')->table('ELANG_MTD_PARTNER')
            ->where('PARTNER_NAME', $filter)
            ->where('STATUS', 'VALID');

//         Tambahkan kondisi jika rolelabel adalah MPC atau MP3
        if ($roleLabel === 'MPC' || $roleLabel === 'MP3') {
            $site->where('BSM', $branch);
        }
        else if ($roleLabel === 'MITRAIM3' || '3KIOSK') {
            $site->where('MC', $mc_name);
        }

        $site = $site->select(
            DB::raw('COALESCE("ADD SITE", 0) as site_count'),
            DB::raw('COALESCE("QR_CODE", 0) as outlet_count')
//        )->toRawSql();
        )->first() ?? (object)['site_count' => "0", 'outlet_count' => "0"];

//        dd($site);


        // Gabungkan data dari dua database
        $mergedData = array_merge((array) $profile, (array) $site);

        return $mergedData;
    }

    public function dropdown()
    {
        $username = auth('api')->user()->username;
        $ptName = DB::table('mitra_table')
            ->select('nama_mitra')
            ->where('id_mitra', $username)
            ->first()
            ->nama_mitra;

        $branches = DB::table('mitra_table')
            ->where('nama_mitra', $ptName)
            ->select('branch', 'id_mitra')
            ->distinct()
            ->get();

        // Prioritaskan branch dengan id_mitra = $username
        $sorted = $branches->sortBy(function($item) use ($username) {
            return $item->id_mitra === $username ? 0 : 1;
        })->pluck('branch')->unique()->values();



        return $sorted;
    }

}

