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
        $userRole = auth('api')->user()->role_label;
        $role = auth('api')->user()->role;
        $username = auth('api')->user()->username;
        $branch = $request->get('branch');
        $role_label = auth('api')->user()->role_label;

        if ($role == 6) {
            $usernameFilter = auth('api')->user()->username;
        }
        if ($role == 7) {
            if ($role_label == "MPC") {
                $usernameFilter = optional(DB::connection('pgsql2')->table('IOH_OUTLET_BULAN_INI_RAPI_KEC')
                    ->select('PARTNER_NAME')
                    ->where('PARTNER_ID', $username)
                    ->first())->PARTNER_NAME;
            } else if ($role_label == "MP3") {
                $usernameFilter = optional(DB::connection('pgsql2')->table('IOH_OUTLET_BULAN_INI_RAPI_KEC')
                    ->select('NAMA_PT')
                    ->where('PARTNER_ID', $username)
                    ->first())->NAMA_PT;
            }
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
            ->where('rp.type', $userRole)
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

//    public function profile(Request $request)
//    {
//        $username = auth('api')->user()->username;
//        $mcid = auth('api')->user()->territory_id;
//        $role = auth('api')->user()->role;
//        $branch = $request->get('branch');
//        $role_label= auth('api')->user()->role_label;
//
//        if ($role_label == "MPC" || $role_label == "3KIOSK" || $role_label == "MITRAIM3") {
//            $pt_name = optional(DB::connection('pgsql2')->table('IOH_OUTLET_BULAN_INI_RAPI_KEC')
//                ->select('PARTNER_NAME')
//                ->where('PARTNER_ID', $username)
//                ->first())->PARTNER_NAME;
//        } else if ($role_label == "MP3") {
//            $pt_name = optional(DB::connection('pgsql2')->table('IOH_OUTLET_BULAN_INI_RAPI_KEC')
//                ->select('NAMA_PT')
//                ->where('PARTNER_ID', $username)
//                ->first())->NAMA_PT;
//        }
//
//        $mc_name = DB::table('territory_dashboards')
//            ->select('name')
//            ->where('id', $mcid)
//            ->first();
//        $profile = DB::table('elang_mitra_sampah')
//            ->orderBy('URUTAN'); // Order by 'URUTAN' in ascending order
//
//        if ($role == 7) {
//            $profile->where('MC', $branch)
//                    ->where('id_mitra', $pt_name);
//        } else if ($role == 6) {
//            $profile->where('MC', $mc_name->name)
//                    ->where('id_mitra', $username);
//        }
//
//// Eksekusi query dan ambil datanya
//        $profileData = $profile->get()->map(function ($item) {
//            $item->target = !empty($item->target) ? (int) $item->target : 0;
//            $item->mtd = !empty($item->mtd) ? (int) $item->mtd : 0;
//            $item->GROWTH = !empty($item->GROWTH) ? round($item->GROWTH, 2) : 0;
//            $item->achievement = !empty($item->achievement) ? round($item->achievement, 2) : 0;
//            $item->URUTAN = !empty($item->URUTAN) ? (int) $item->URUTAN : 0;
//
//            // Format 'mtd_date' dan 'last_update' jika ada
//            $item->mtd_date = !empty($item->mtd_date) ? Carbon::parse($item->mtd_date)->format('d-m-Y') : '00-00-0000';
//            $item->last_update = !empty($item->last_update) ? Carbon::parse($item->last_update)->format('d-m-Y') : '00-00-0000';
//
//            return $item;
//        });
//
//        return $profileData;
//    }

    public function profile(Request $request)
    {
        $username = auth('api')->user()->username;
        $mcid = auth('api')->user()->territory_id;
        $role = auth('api')->user()->role;
        $branch = $request->get('branch');
        $role_label = auth('api')->user()->role_label;

        // Ambil nama partner berdasarkan role
        if (in_array($role_label, ["MPC", "3KIOSK", "MITRAIM3"])) {
            $pt_name = optional(DB::connection('pgsql2')
                ->table('IOH_OUTLET_BULAN_INI_RAPI_KEC')
                ->select('PARTNER_NAME')
                ->where('PARTNER_ID', $username)
                ->first())->PARTNER_NAME;
        } else if ($role_label === "MP3") {
            $pt_name = optional(DB::connection('pgsql2')
                ->table('IOH_OUTLET_BULAN_INI_RAPI_KEC')
                ->select('NAMA_PT')
                ->where('PARTNER_ID', $username)
                ->first())->NAMA_PT;
        }

        // Ambil nama MC berdasarkan ID
        $mc_name = DB::table('territory_dashboards')
            ->select('name')
            ->where('id', $mcid)
            ->first();

        // Base query
        $profile = DB::connection('pgsql2')
            ->table('TRADE_PARTNER_SUMMARY')
            ->orderBy('URUTAN')
            ->where('VIEW', '1')
            ->when($role == 7, function ($query) use ($branch, $pt_name) {
                return $query->where('BSM', $branch)
                    ->where('PARTNER_NAME', 'like', '%' . $pt_name . '%');
            })
            ->when($role == 6, function ($query) use ($mc_name, $username) {
                return $query->where('MC', $mc_name->name)
                    ->where('ID_PARTNER', $username);
            })
            ->selectRaw('
        "URUTAN",
        "KPI_NAME",
        mtd_dt as last_update,
        SUM("TARGET") as target,
        SUM("POIN") as poin,
        SUM("MTD") as mtd
    ')
            ->groupBy('KPI_NAME', 'URUTAN', 'mtd_dt')
            ->get()
            ->map(function ($item) {
                $target = (int) ($item->target ?? 0);
                $mtd = (int) ($item->mtd ?? 0);
                $poin = (int) ($item->poin ?? 0);

                $achv = $target != 0 ? round($mtd / $target, 2) : 0;

                return [
                    'URUTAN'      => $item->URUTAN ?? 0,
                    'KPI_NAME'    => $item->KPI_NAME,
                    'last_update' => !empty($item->last_update)
                        ? Carbon::parse($item->last_update)->format('d-m-Y')
                        : '00-00-0000',
                    'target' => $target,
                    'poin'   => $poin,
                    'mtd'    => $mtd,
                    'achv'   => $achv,
                ];
            });

        return $profile;

    }



    public function reward (Request $request)
    {
        $mcId = auth('api')->user()->territory_id;
        $role = auth('api')->user()->role;
        $username = auth('api')->user()->username;
        $branch = $request->get('branch');
        $regionId = $this->getRegionId($mcId);

        $parameters = DB::table('payout_parameter as p')
            ->join('payout_mapping as pm', 'p.id_param', '=', 'pm.id_param')
            ->select(
                'p.id_param as parameter_id',
                'p.nama_param as parameter_name',
                'p.status_param as parameter_status',
                'pm.role as role',
                'pm.is_active as parameter_is_active',
                'pm.territory_id as territory_id',
            )
            ->where('pm.is_active', true)
            ->where('p.status_param', true)
            ->where('pm.territory_id', $regionId)
            ->where('pm.role', $role)
            ->get();

        $data = DB::table('payout_data')
            ->where('status', 'Official Letter & IOM Release')
            ->whereIn('id_param', $parameters->pluck('parameter_id'))
            ->where('id_mitra', $username)
            ->where('branch', $branch)
            ->get()
//            ->toRawSql()
        ;

//        dd($data);


        return $data;

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

        if ($roleLabel === 'MPC' || $roleLabel === '3KIOSK' || $roleLabel === 'MITRAIM3') {
            $ptfilter = 'PARTNER_NAME';
        } else if ($roleLabel === 'MP3') {
            $ptfilter = 'NAMA_PT'; // atau 'nama_pt' sesuai hasil cek database
        } else {
            $ptfilter = 'PARTNER_NAME';
        }

        $nama_pt = DB::connection('pgsql2')->table('IOH_OUTLET_BULAN_INI_RAPI_KEC')
            ->select(DB::raw("\"$ptfilter\" AS nama_mitra"))
            ->where('PARTNER_ID', $username)
            ->first()
            ;
        ;

//        if ($roleLabel == 'MITRAIM3') {
//            $nama_ptfilter = $username;
//        }
//        else {
//            $nama_ptfilter = $nama_pt->nama_mitra;
//        }
        $nama_ptfilter = $nama_pt->nama_mitra;



        $mc_name = DB::table('territory_dashboards')
            ->select('name')
            ->where('id', $mc)
            ->first()
            ->name;

        // Ambil data dari database pertama
        $profile = DB::connection('pgsql')->table('mitra_table')
            ->select('id_mitra',
                'nama_mitra',
                'type')
            ->where('is_active', true)
            ->where('id_mitra', $username)
            ->first(); // Ambil satu baris data



        if (!$profile) {
            return 'Profile data not found';
        }
        // Ambil data dari database kedua
        $site = DB::connection('pgsql2')->table('ELANG_MTD_PARTNER')
            ->where('PARTNER_NAME', 'like', '%' . $nama_ptfilter . '%' )
            ->where('STATUS', 'VALID');

        //Tambahkan kondisi jika rolelabel adalah MPC atau MP3
        if ($roleLabel === 'MPC' || $roleLabel === 'MP3') {
            $site->where('BSM', $branch);
        }
        else if ($roleLabel === 'MITRAIM3' || '3KIOSK') {
            $site->where('MC', $mc_name);
        }

        $siteList = $site->select(
            DB::raw('COALESCE("ADD SITE", 0) as site_count'),
            DB::raw('COALESCE("QR_CODE", 0) as outlet_count')
        )->get();
//        )->toRawSql();
//        dd($siteList);

        $maxData = $siteList->sortByDesc(function($item) {
            return max($item->site_count, $item->outlet_count);
        })->first();

        $maxData = $maxData ?? (object)['site_count' => "0", 'outlet_count' => "0"];

        $maxData->site_count = (string) $maxData->site_count;
        $maxData->outlet_count = (string) $maxData->outlet_count;

        // Gabungkan data dari dua database
        $profileArray = (array) $profile;
        unset($profileArray['nama_mitra']); // buang nama_mitra dari profile

        $mergedData = array_merge((array) $nama_pt, $profileArray, (array) $maxData);

        return $mergedData;
    }

    public function dropdown()
    {
        $role_label = auth('api')->user()->role_label;
        $username = auth('api')->user()->username;

        if ($role_label == "MPC" || $role_label == "3KIOSK" || $role_label == "MITRAIM3") {
            $ptName = optional(DB::connection('pgsql2')->table('IOH_OUTLET_BULAN_INI_RAPI_KEC')
                ->select('PARTNER_NAME')
                ->where('PARTNER_ID', $username)
                ->first())->PARTNER_NAME;
        } else if ($role_label == "MP3") {
            $ptName = optional(DB::connection('pgsql2')->table('IOH_OUTLET_BULAN_INI_RAPI_KEC')
                ->select('NAMA_PT')
                ->where('PARTNER_ID', $username)
                ->first())->NAMA_PT;
        }

        $branches = DB::table('mitra_table')
            ->where('nama_mitra', 'like', '%' . $ptName . '%')
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

