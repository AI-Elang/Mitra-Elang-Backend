<?php

namespace App\Http\Services\Api\V1;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DseAiService
{

    public function listMcById()
    {
        $mcId = auth()->user()->territory_id;
        if ($mcId == 0 || $mcId == null) {
            throw new \Exception('MC ID is required', 400);
        }

        // Ambil data DSE dan Territory dalam satu query
        $dse = DB::table('dse as d')
            ->select('d.id_dse as dse_id', 'd.name as dse_name', 'd.id_unit', 'd.status', 't.name as territory_name', 't.id as territory_id')
            ->join('territories as t', 'd.id_unit', '=', 't.id')
            ->where('t.id', $mcId)
            ->where('t.is_active', 1)
            ->get();

        $dseIds = $dse->pluck('dse_id')->toArray();

        $dtId = DB::table('showcases')
            ->selectRaw('MAX(date) as date')
            ->first()
            ->date;

        // Format date ke format Y-m-d
        $dtId = Carbon::parse($dtId)->format('Y-m-d');

        // Ambil data ELANG_DSE_DAILY_LENGKAP sekaligus
        $dse_daily_data = DB::table('showcases')
            ->select(
                'username',
                DB::raw('COALESCE(SUM(sellin_sp), 0) as sp'),
                DB::raw('COALESCE(SUM(sellin_voucher), 0) as vou'),
                DB::raw('COALESCE(SUM(sellin_salmo), 0) as salmo'),
                DB::raw('COALESCE(COUNT(outlet_id), 0) as actual_pjp'),
                DB::raw("'25' as pjp")
            )
            ->groupBy('username')
            ->whereIn('username', $dseIds)
            ->whereRaw('DATE(date) = ?', [$dtId])
            ->get();

        foreach ($dse_daily_data as $d) {
            $dse_daily_data[$d->username] = $d;

            $showcase_in = DB::table('showcases')
                ->selectRaw('COALESCE(date::TEXT, \'-\') as date')
                ->where('username', $d->username)
                ->whereRaw('DATE(date) = ?', [$dtId])
                ->orderBy('date')
                ->first();

            // Ambil check-out showcase
            $showcase_out = DB::table('showcases')
                ->selectRaw('COALESCE(status_timestamp::TEXT, \'-\') as status_timestamp')
                ->where('username', $d->username)
                ->whereRaw('DATE(date) = ?', [$dtId])
                ->orderBy('status_timestamp', 'desc')
                ->first();

            $zero = DB::table('showcases')
                ->selectRaw('COALESCE(COUNT(outlet_id), 0) as zero')
                ->where('username', $d->username)
                ->whereRaw('DATE(date) = ?', [$dtId])
                ->whereRaw('sellin_sp = 0 AND sellin_voucher = 0 AND sellin_salmo = 0')
                ->first();

            $d->checkin = ($showcase_in && $showcase_in->date !== '-' && !empty($showcase_in->date))
                ? Carbon::parse($showcase_in->date)->format('H:i:s')
                : '-';

            $d->checkout = ($showcase_out && $showcase_out->status_timestamp !== '-' && !empty($showcase_out->status_timestamp))
                ? Carbon::parse($showcase_out->status_timestamp)->format('H:i:s')
                : '-';

            // Add durasi from checkout - checkin
            $d->duration = ($d->checkin !== '-' && $d->checkout !== '-')
                ? Carbon::parse($showcase_in->date)->diff(Carbon::parse($showcase_out->status_timestamp))->format('%H:%I:%S')
                : '-';

            $d->zero = $zero->zero;
        }

        // merge data dse dan dse_daily_data
        foreach ($dse as $d) {
            $d->pjp = 25;
            $d->actual_pjp = 0;
            $d->zero = 0;
            $d->sp = 0;
            $d->vou = 0;
            $d->salmo = 0;
            $d->mtd_dt = $dtId;
            $d->checkin = '-';
            $d->checkout = '-';
            $d->duration = '-';

            if (isset($dse_daily_data[$d->dse_id])) {
                $d->pjp = $dse_daily_data[$d->dse_id]->pjp;
                $d->actual_pjp = $dse_daily_data[$d->dse_id]->actual_pjp;
                $d->zero = $dse_daily_data[$d->dse_id]->zero;
                $d->sp = $dse_daily_data[$d->dse_id]->sp;
                $d->vou = $dse_daily_data[$d->dse_id]->vou;
                $d->salmo = $dse_daily_data[$d->dse_id]->salmo;
                $d->mtd_dt = $dtId;
                $d->checkin = $dse_daily_data[$d->dse_id]->checkin;
                $d->checkout = $dse_daily_data[$d->dse_id]->checkout;
                $d->duration = $dse_daily_data[$d->dse_id]->duration;
            }
        }

        return $dse;
    }

    public function listOutletByDseDate($dseId, $request)
    {
        if ($dseId == 0 || $dseId == null) {
            throw new \Exception('DSE ID is required', 400);
        }

        $date = $request->date;

        if ($date == null) {
            throw new \Exception('Date is required', 400);
        }

        $data = DB::table('showcases')
            ->select(
                'outlet_id',
                'date as checkin',
                'status_timestamp as checkout',
                'username',
                'voucher_im3',
                'voucher_tri',
                'perdana_im3',
                'perdana_tri',
                'voucher_im3_DSE as voucher_im3_dse',
                'voucher_tri_DSE as voucher_tri_dse',
                'perdana_im3_DSE as perdana_im3_dse',
                'perdana_tri_DSE as perdana_tri_dse',
                'attract',
                'purchase',
                'educate',
                'face',
                'sellin_sp',
                'sellin_voucher',
                'sellin_salmo'
            )
            ->where('username', $dseId)
            ->whereRaw('DATE(date) = ?', [$date])
            ->get();

        // Get the outlet name data from table IOH_OUTLET_BULAN_INI_RAPI_KEC and merge it with the data
        $outlet_name = DB::connection('pgsql2')
            ->table('IOH_OUTLET_BULAN_INI_RAPI_KEC')
            ->select('QR_CODE as outlet_id', 'NAMA_TOKO as outlet_name', 'STATUS as category')
            ->whereIn('QR_CODE', $data->pluck('outlet_id')->toArray())
            ->where('STATUS', 'VALID')
            ->get();

        foreach ($data as $d) {
            $outlet = $outlet_name->where('outlet_id', $d->outlet_id)->first();
            $d->outlet_name = $outlet ? $outlet->outlet_name : '-';
            $d->category = $outlet ? $outlet->category : '-';
        }

        // Make outlet name positioned after outlet_id
        $data = $data->map(function ($d) {
            return [
                'outlet_id' => $d->outlet_id,
                'outlet_name' => $d->outlet_name,
                'category' => $d->category,
                'checkin' => Carbon::parse($d->checkin)->format('H:i:s'),
                'checkout' => isset($d->checkout) ? Carbon::parse($d->checkout)->format('H:i:s') : '-',
                'duration' => isset($d->checkout) ? Carbon::parse($d->checkin)->diff(Carbon::parse($d->checkout))->format('%H:%I:%S') : '-',
                'username' => $d->username,
                'voucher_im3' => $this->format_number($d->voucher_im3),
                'voucher_tri' => $this->format_number($d->voucher_tri),
                'perdana_im3' => $this->format_number($d->perdana_im3),
                'perdana_tri' => $this->format_number($d->perdana_tri),
                'voucher_im3_dse' => $this->format_number($d->voucher_im3_dse),
                'voucher_tri_dse' => $this->format_number($d->voucher_tri_dse),
                'perdana_im3_dse' => $this->format_number($d->perdana_im3_dse),
                'perdana_tri_dse' => $this->format_number($d->perdana_tri_dse),
                'attract' => $this->format_number($d->attract),
                'purchase' => $this->format_number($d->purchase),
                'educate' => $this->format_number($d->educate),
                'face' => $this->format_number($d->face),
                'sellin_sp' => $this->format_number($d->sellin_sp),
                'sellin_voucher' => $this->format_number($d->sellin_voucher),
                'sellin_salmo' => $this->format_number($d->sellin_salmo),
            ];
        });

        return $data;
    }

    public function format_number($number)
    {
        if ($number == null || $number == 0) {
            return '0';
        }

        return number_format($number, 0, ',', '.');
    }

    public function getOutletImages($outletId, $request)
    {
        $date = $request->date;

        if ($outletId == 0 || $outletId == null) {
            throw new \Exception('Outlet ID is required', 400);
        }

        if ($date == null) {
            throw new \Exception('Date is required', 400);
        }

        $image_base_url = 'http://103.157.116.221:8000';

        $data = DB::table('showcases')
            ->select(
                DB::raw("CONCAT('$image_base_url', foto_original_visibility) as foto_original_visibility"),
                DB::raw("CONCAT('$image_base_url', foto_original_availability) as foto_original_availability"),
                DB::raw("CONCAT('$image_base_url', foto_ai_visibility) as foto_ai_visibility"),
                DB::raw("CONCAT('$image_base_url', foto_ai_availability) as foto_ai_availability"),
                DB::raw("CONCAT('$image_base_url', foto_owner_outlet) as foto_owner_outlet")
            )
            ->where('outlet_id', $outletId)
            ->whereRaw('DATE(date) = ?', [$date])
            ->first();

        return $data;
    }

    public function getDseAiComparisons()
    {
        $mcId = auth()->user()->territory_id;

        if ($mcId == 0 || $mcId == null) {
            throw new \Exception('MC ID is required', 400);
        }

        $dse = DB::table('dse as d')
            ->select('d.id_dse as dse_id', 'd.name as dse_name', 'd.id_unit', 'd.status', 't.name as territory_name', 't.id as territory_id')
            ->join('territories as t', 'd.id_unit', '=', 't.id')
            ->where('t.id', $mcId)
            ->where('t.is_active', 1)
            ->get();

        $dseIds = $dse->pluck('dse_id')->toArray();

        $dtId = DB::table('showcases')
            ->selectRaw('MAX(date) as date')
            ->first()
            ->date;

        $dtId = Carbon::parse($dtId)->format('Y-m-d');

        $dse_daily_data_today = DB::table('showcases')
            ->select(
                'username',
                DB::raw('COALESCE(SUM(sellin_sp), 0) as sp_today'),
                DB::raw('COALESCE(SUM(sellin_voucher), 0) as vou_today'),
                DB::raw('COALESCE(SUM(sellin_salmo), 0) as salmo_today'),
                DB::raw('COALESCE(COUNT(outlet_id), 0) as visit_today'),
            )
            ->groupBy('username')
            ->whereIn('username', $dseIds)
            ->whereRaw('DATE(date) = ?', [$dtId])
            ->get();

        $dse_daily_data_yesterday = DB::table('showcases')
            ->select(
                'username',
                DB::raw('COALESCE(SUM(sellin_sp), 0) as sp_yesterday'),
                DB::raw('COALESCE(SUM(sellin_voucher), 0) as vou_yesterday'),
                DB::raw('COALESCE(SUM(sellin_salmo), 0) as salmo_yesterday'),
                DB::raw('COALESCE(COUNT(outlet_id), 0) as visit_yesterday'),
            )
            ->groupBy('username')
            ->whereIn('username', $dseIds)
            ->whereRaw('DATE(date) = ?', [Carbon::parse($dtId)->subDay()->format('Y-m-d')])
            ->get();

        // merge data dse dan dse_daily_data_today
        foreach ($dse as $d) {
            $d->visit_today = "0";
            $d->sp_today = "0";
            $d->vou_today = "0";
            $d->salmo_today = "0";
            $d->visit_yesterday = "0";
            $d->sp_yesterday = "0";
            $d->vou_yesterday = "0";
            $d->salmo_yesterday = "0";
            $d->mtd_dt = $dtId;

            foreach ($dse_daily_data_today as $dtd) {
                if ($d->dse_id == $dtd->username) {
                    $d->visit_today = $this->format_number($dtd->visit_today);
                    $d->sp_today = $this->format_number($dtd->sp_today);
                    $d->vou_today = $this->format_number($dtd->vou_today);
                    $d->salmo_today = $this->format_number($dtd->salmo_today);
                }
            }

            foreach ($dse_daily_data_yesterday as $dyd) {
                if ($d->dse_id == $dyd->username) {
                    $d->visit_yesterday = $this->format_number($dyd->visit_yesterday);
                    $d->sp_yesterday = $this->format_number($dyd->sp_yesterday);
                    $d->vou_yesterday = $this->format_number($dyd->vou_yesterday);
                    $d->salmo_yesterday = $this->format_number($dyd->salmo_yesterday);
                }
            }
        }

        // Show only the selected data
        $dse = collect($dse)->transform(function ($item) {
            return [
                'id' => $item->dse_id,
                'name' => $item->dse_name,
                'mc_id' => (int)$item->territory_id,
                'mc_name' => $item->territory_name,
                'visit_today' => $item->visit_today,
                'sp_today' => $item->sp_today,
                'vou_today' => $item->vou_today,
                'salmo_today' => $item->salmo_today,
                'visit_yesterday' => $item->visit_yesterday,
                'sp_yesterday' => $item->sp_yesterday,
                'vou_yesterday' => $item->vou_yesterday,
                'salmo_yesterday' => $item->salmo_yesterday,
                'mtd_dt' => $item->mtd_dt
            ];
        });

        return $dse;
    }

    public function getDseAiSummary($request)
    {
        $username = auth()->user()->username;
        $mcId = auth()->user()->territory_id;
        $brand = auth()->user()->brand;

        $branch = $request->get('branch');
        $month = $request->get('month');
        $year = $request->get('year');

        $branch_brand = $branch . ' ' . $brand;

        $branchId = DB::table('territories')
            ->where('name', $branch_brand)
            ->value('id');

        $role = auth()->user()->role;
        $role_label = auth()->user()->role_label;

        if ($month == null || $year == null) {
            throw new \Exception('Month and year are required', 400);
        }

        $mc_no_brand = DB::table('territory_dashboards')
            ->where('id', $mcId)
            ->value('name');

        $mcName = $mc_no_brand . ' ' . $brand;

        if ($role == 6)
        {
            $mcId = DB::table('territories')
                ->where('name', $mcName)
                ->value('id');

            $getFilter = 'MC';
            $valueFilter = $mc_no_brand;
            $userfilterValue = $username;
            $userfilter = 'PARTNER_ID';

            $dseFilter = 't.id';
            $dseValue = $mcId;
        }
        else if ($role == 7)
        {
            $getFilter = 'BSM';
            $valueFilter = $branch;


            if ($role_label == 'MPC')
            {
                $userfilter = 'PARTNER_NAME';
                $userfilterValue = optional(DB::connection('pgsql2')->table('IOH_OUTLET_BULAN_INI_RAPI_KEC')
                    ->select('PARTNER_NAME')
                    ->where('PARTNER_ID', $username)
                    ->first())->PARTNER_NAME;
            }
            else
            {
                $userfilter = 'NAMA_PT';
                $userfilterValue = optional(DB::connection('pgsql2')->table('IOH_OUTLET_BULAN_INI_RAPI_KEC')
                    ->select('NAMA_PT')
                    ->where('PARTNER_ID', $username)
                    ->first())->NAMA_PT;
            }

//            $userfilterValue = DB::table('mitra_table')
//                ->select('nama_mitra')
//                ->where('id_mitra', $username)
//                ->first()
//                ->nama_mitra;

            $dseFilter = 't2.id';
            $dseValue = $branchId;
        }

//        dd($getFilter, $valueFilter, $userfilter, $userfilterValue, $dseFilter, $dseValue);

        //FILTER DSE BY PT NAME + BRANCH OR MC
        $distinctDse = DB::connection('pgsql2')->table('IOH_OUTLET_BULAN_INI_RAPI_KEC')
            ->where($userfilter, $userfilterValue)
            ->where($getFilter, $valueFilter)
            ->distinct()
//            ->toRawSql()
            ->pluck('DSE_CODE')
            ->toArray()
        ;

//        dd($distinctDse);

        $dse = DB::table('dse as d')
            ->select('d.id_dse as dse_id', 'd.name as dse_name', 'd.id_unit', 'd.status', 't.name as territory_name', 't.id as territory_id')
            ->join('territories as t', 'd.id_unit', '=', 't.id')
            ->join('territories as t2', 't.id_secondary', '=', 't2.id')
            ->where($dseFilter, $dseValue)
            ->whereIn('d.id_dse', $distinctDse)
            ->where('t.is_active', 1)
            ->where('d.status', 1)
            ->get()
//            ->toRawSql()
        ;

//        dd($dse);

        $dseIds = $dse->pluck('dse_id')->toArray();

        $dse_ai_summary = DB::table('showcases')
            ->select(
                'username',
                DB::raw('COALESCE(SUM(perdana_im3), 0) as perdana_im3'),
                DB::raw('COALESCE(SUM(perdana_tri), 0) as perdana_tri'),
                DB::raw('COALESCE(SUM(voucher_im3), 0) as voucher_im3'),
                DB::raw('COALESCE(SUM(voucher_tri), 0) as voucher_tri'),
                DB::raw('COALESCE(SUM(sellin_sp), 0) as sp'),
                DB::raw('COALESCE(SUM(sellin_voucher), 0) as vou'),
                DB::raw('COALESCE(SUM(sellin_salmo), 0) as salmo'),
                DB::raw('COALESCE(COUNT(outlet_id), 0) as visit'),
            )
            ->groupBy('username')
            ->whereIn('username', $dseIds)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->get();

        $targetPjps = DB::connection('pgsql2')
            ->table('IOH_OUTLET_BULAN_INI_RAPI_KEC')
            ->select('DSE_CODE', DB::raw('COUNT("QR_CODE") as target_pjp'))
            ->whereIn('DSE_CODE', $dseIds)
            ->groupBy('DSE_CODE')
            ->get()
            ->keyBy('DSE_CODE');

        // Match the data count within showcase and the existing outlet data on the second database
        // based on the data of outlet_ids within showcases
        $outletIds = DB::table('showcases')
            ->select(DB::raw('DISTINCT ON (outlet_id) outlet_id'), 'username')
            ->whereIn('username', $dseIds)
            ->whereRaw('EXTRACT(MONTH FROM date) = ?', [$month])
            ->whereRaw('EXTRACT(YEAR FROM date) = ?', [$year])
            ->orderBy('outlet_id')
            ->orderBy('date', 'desc')  // This determines which row is kept when duplicates exist
            ->get();

        $actualUniquePjp = collect($outletIds)->groupBy('username')->map(function ($item) {
            return [
                'username' => $item->first()->username,
                'actual_pjp' => $item->count(),
            ];
        })
            ->keyBy('username');

        $outletIds = DB::table('showcases')
            ->select('outlet_id', 'username')
            ->whereIn('username', $dseIds)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->get();

        $actualPjps = collect($outletIds)->groupBy('username')->map(function ($item) {
            return [
                'username' => $item->first()->username,
                'actual_pjp' => $item->count(),
            ];
        })
            ->keyBy('username');

        foreach ($dse as $d) {
            $d->perdana_im3 = "0";
            $d->perdana_tri = "0";
            $d->voucher_im3 = "0";
            $d->voucher_tri = "0";
            $d->actual_pjp = "0";
            $d->sp = "0";
            $d->vou = "0";
            $d->salmo = "0";
            $d->visit = "0";
            $d->mtd_dt = Carbon::createFromDate($year, $month, 1)->format('F') . ' ' . $year;
            $d->target_pjp = "0";
            $d->actual_pjp = "0";
            $d->actual_unique_pjp = "0";
            $d->percentage_pjp = "0";

            foreach ($dse_ai_summary as $das) {
                if ($d->dse_id == $das->username) {
                    $d->perdana_im3 = $this->format_number($das->perdana_im3);
                    $d->perdana_tri = $this->format_number($das->perdana_tri);
                    $d->voucher_im3 = $this->format_number($das->voucher_im3);
                    $d->voucher_tri = $this->format_number($das->voucher_tri);
                    $d->visit = $this->format_number($das->visit);
                    $d->sp = $this->format_number($das->sp);
                    $d->vou = $this->format_number($das->vou);
                    $d->salmo = $this->format_number($das->salmo);
                }
            }

            foreach ($targetPjps as $targetPjp) {
                if ($d->dse_id == $targetPjp->DSE_CODE) {
                    $d->target_pjp = $this->format_number($targetPjp->target_pjp);
                }
            }

            foreach ($actualPjps as $actualPjp) {
                if ($d->dse_id == $actualPjp['username']) {
                    $d->actual_pjp = $this->format_number($actualPjp['actual_pjp']);
                }
            }

            foreach ($actualUniquePjp as $actualPjp) {
                if ($d->dse_id == $actualPjp['username']) {
                    $d->actual_unique_pjp = $this->format_number($actualPjp['actual_pjp']);
                }
            }

            if ($d->target_pjp != 0) {
                $d->percentage_pjp = number_format(($d->actual_unique_pjp / $d->target_pjp) * 100, 2) . '%';
            }
        }

        // Show only the selected data
        $dse = collect($dse)->transform(function ($item) {
            return [
                'id' => $item->dse_id,
                'name' => $item->dse_name,
                'mc_id' => (int)$item->territory_id,
                'mc_name' => $item->territory_name,
                'perdana_im3' => $item->perdana_im3,
                'perdana_tri' => $item->perdana_tri,
                'voucher_im3' => $item->voucher_im3,
                'voucher_tri' => $item->voucher_tri,
                'visit' => $item->visit,
                'sp' => $item->sp,
                'vou' => $item->vou,
                'salmo' => $item->salmo,
                'mtd_dt' => $item->mtd_dt,
                'target_pjp' => $item->target_pjp,
                'actual_pjp' => $item->actual_pjp,
                'actual_unique_pjp' => $item->actual_unique_pjp,
                'percentage_pjp' => $item->percentage_pjp
            ];
        });

        return $dse;
    }

    public function getDseAiDaily($request)
    {
        $user = auth()->user();
        $date = $request->get('date');
        $branch = $request->get('branch');

        if (!$date) throw new \Exception('Date is required', 400);

        $branch_brand = $branch . ' ' . $user->brand;
        $branchId = DB::table('territories')->where('name', $branch_brand)->value('id');
        $mcName = DB::table('territory_dashboards')->where('id', $user->territory_id)->value('name') . ' ' . $user->brand;

        if ($user->role == 6) {
            $mcId = DB::table('territories')->where('name', $mcName)->value('id');
            $getFilter = 'MC';
            $valueFilter = $mcName;
            $userfilter = 'PARTNER_ID';
            $userfilterValue = $user->username;
            $dseFilter = 't.id';
            $dseValue = $mcId;
        } else {
            $getFilter = 'BSM';
            $valueFilter = $branch;
            $userfilter = ($user->role_label == 'MPC') ? 'PARTNER_NAME' : 'NAMA_PT';
            $userfilterValue = optional(DB::connection('pgsql2')
                ->table('IOH_OUTLET_BULAN_INI_RAPI_KEC')
                ->select($userfilter)
                ->where('PARTNER_ID', $user->username)
                ->first())->$userfilter;
            $dseFilter = 't2.id';
            $dseValue = $branchId;
        }

        $distinctDse = DB::connection('pgsql2')->table('IOH_OUTLET_BULAN_INI_RAPI_KEC')
            ->where($userfilter, $userfilterValue)
            ->where($getFilter, $valueFilter)
            ->distinct()
            ->pluck('DSE_CODE')
            ->toArray();

        $dse = DB::table('dse as d')
            ->select('d.id_dse as dse_id', 'd.name as dse_name', 't.name as territory_name', 't.id as territory_id')
            ->join('territories as t', 'd.id_unit', '=', 't.id')
            ->join('territories as t2', 't.id_secondary', '=', 't2.id')
            ->whereIn('d.id_dse', $distinctDse)
            ->where($dseFilter, $dseValue)
            ->where('t.is_active', 1)
            ->get();

        $dseIds = $dse->pluck('dse_id')->toArray();

        $dse_daily_data = DB::table('showcases')
            ->select('username',
                DB::raw('COALESCE(SUM(voucher_im3),0) as voucher_im3'),
                DB::raw('COALESCE(SUM(voucher_tri),0) as voucher_tri'),
                DB::raw('COALESCE(SUM(perdana_im3),0) as perdana_im3'),
                DB::raw('COALESCE(SUM(perdana_tri),0) as perdana_tri'),
                DB::raw('COALESCE(SUM(sellin_sp),0) as sp'),
                DB::raw('COALESCE(SUM(sellin_voucher),0) as vou'),
                DB::raw('COALESCE(SUM(sellin_salmo),0) as salmo'),
                DB::raw('COALESCE(COUNT(outlet_id),0) as visit'),

                DB::raw('COALESCE(SUM("voucher_tri_DSE"),0) as "voucher_tri_DSE"'),
                DB::raw('COALESCE(SUM("voucher_im3_DSE"),0) as "voucher_im3_DSE"'),
                DB::raw('COALESCE(SUM("perdana_im3_DSE"),0) as "perdana_im3_DSE"'),
                DB::raw('COALESCE(SUM("perdana_tri_DSE"),0) as "perdana_tri_DSE"'),
                DB::raw('COALESCE(SUM("attract"),0) as attract'),
                DB::raw('COALESCE(SUM("educate"),0) as educate'),
                DB::raw('COALESCE(SUM("purchase"),0) as purchase'))

            ->whereIn('username', $dseIds)
            ->whereDate('date', $date)
            ->groupBy('username')
            ->get();

        $checkins = DB::table('showcases')->select('username', DB::raw('MIN(date) as date'))
            ->whereIn('username', $dseIds)->whereDate('date', $date)->groupBy('username')->get()->keyBy('username');

        $checkouts = DB::table('showcases')->select('username', DB::raw('MAX(status_timestamp) as status_timestamp'))
            ->whereIn('username', $dseIds)->whereDate('date', $date)->groupBy('username')->get()->keyBy('username');

        $zeroCounts = DB::table('showcases')->select('username', DB::raw('COUNT(outlet_id) as zero'))
            ->whereIn('username', $dseIds)->whereDate('date', $date)
            ->whereRaw('sellin_sp = 0 AND sellin_voucher = 0 AND sellin_salmo = 0')
            ->groupBy('username')->get()->keyBy('username');

        $targetPjps = DB::connection('pgsql2')->table('IOH_OUTLET_BULAN_INI_RAPI_KEC')
            ->select('DSE_CODE', DB::raw('COUNT("QR_CODE") as target_pjp'))
            ->whereIn('DSE_CODE', $dseIds)
            ->groupBy('DSE_CODE')->get()->keyBy('DSE_CODE');

        $outletCounts = DB::table('showcases')
            ->select('outlet_id', 'username')
            ->whereIn('username', $dseIds)
            ->whereDate('date', $date)
            ->get()
            ->groupBy('username')
            ->map(fn($outlets) => ['actual_pjp' => $outlets->count()]);

        foreach ($dse_daily_data as $data) {
            $username = $data->username;
            $data->checkin = isset($checkins[$username]) ? Carbon::parse($checkins[$username]->date)->format('H:i:s') : '-';
            $data->checkout = isset($checkouts[$username]) ? Carbon::parse($checkouts[$username]->status_timestamp)->format('H:i:s') : '-';
            $data->duration = ($data->checkin !== '-' && $data->checkout !== '-') ?
                Carbon::parse($checkins[$username]->date)->diff(Carbon::parse($checkouts[$username]->status_timestamp))->format('%H:%I:%S') : '-';
            $data->zero = $zeroCounts[$username]->zero ?? 0;
            $data->target_pjp = 15; // Default nilai target PJP
            $data->actual_pjp = $outletCounts[$username]['actual_pjp'] ?? 0;
        }

        foreach ($dse as $d) {
            $match = $dse_daily_data->firstWhere('username', $d->dse_id);
            $d->perdana_im3 = $this->format_number($match->perdana_im3 ?? 0);
            $d->perdana_tri = $this->format_number($match->perdana_tri ?? 0);
            $d->voucher_im3 = $this->format_number($match->voucher_im3 ?? 0);
            $d->voucher_tri = $this->format_number($match->voucher_tri ?? 0);
            $d->voucher_tri_DSE = $this->format_number($match->voucher_tri_DSE ?? 0);
            $d->voucher_im3_DSE = $this->format_number($match->voucher_im3_DSE ?? 0);
            $d->perdana_im3_DSE = $this->format_number($match->perdana_im3_DSE ?? 0);
            $d->perdana_tri_DSE = $this->format_number($match->perdana_tri_DSE ?? 0);
            $d->attract = $this->format_number($match->attract ?? 0);
            $d->educate = $this->format_number($match->educate ?? 0);
            $d->purchase = $this->format_number($match->purchase ?? 0);
            $d->visit = $this->format_number($match->visit ?? 0);
            $d->sp = $this->format_number($match->sp ?? 0);
            $d->vou = $this->format_number($match->vou ?? 0);
            $d->salmo = $this->format_number($match->salmo ?? 0);
            $d->mtd_dt = $date;
            $d->checkin = $match->checkin ?? '-';
            $d->checkout = $match->checkout ?? '-';
            $d->duration = $match->duration ?? '-';
            $d->target_pjp = $this->format_number($match->target_pjp ?? 0);
            $d->actual_pjp = $this->format_number($match->actual_pjp ?? 0);
            $d->percentage = ($match->target_pjp ?? 0) > 0
                ? number_format(($match->actual_pjp / $match->target_pjp) * 100, 2) . '%'
                : '0';
        }

        return $dse->map(fn($d) => [
            'id' => $d->dse_id,
            'name' => $d->dse_name,
            'mc_id' => (int) $d->territory_id,
            'mc_name' => $d->territory_name,
            'perdana_im3' => $d->perdana_im3,
            'perdana_tri' => $d->perdana_tri,
            'voucher_im3' => $d->voucher_im3,
            'voucher_tri' => $d->voucher_tri,
            'voucher_tri_DSE' => $d->voucher_tri_DSE,
            'voucher_im3_DSE' => $d->voucher_im3_DSE,
            'perdana_im3_DSE' => $d->perdana_im3_DSE,
            'perdana_tri_DSE' => $d->perdana_tri_DSE,
            'attract' => $d->attract,
            'educate' => $d->educate,
            'purchase' => $d->purchase,
            'visit' => $d->visit,
            'sp' => $d->sp,
            'vou' => $d->vou,
            'salmo' => $d->salmo,
            'mtd_dt' => $d->mtd_dt,
            'checkin' => $d->checkin,
            'checkout' => $d->checkout,
            'duration' => $d->duration,
            'target_pjp' => $d->target_pjp,
            'actual_pjp' => $d->actual_pjp,
            'percentage' => $d->percentage,
        ]);
    }


    public function getDseAiDashboard($request)
    {
        $date = $request->date;
        $circle_id = $request->circle;
        $region_id = $request->region ?? null;
        $area_id = $request->area ?? null;
        $branch_id = $request->branch ?? null;

        if ($date == null) {
            throw new \Exception('Date is required', 400);
        }

        if ($area_id && !$region_id) {
            throw new \Exception('Region is required when Area is selected', 400);
        }

        if ($branch_id && !$area_id) {
            throw new \Exception('Area is required when Branch is selected', 400);
        }

        if ($branch_id) {
            $mc = DB::table('territories as t')
                ->selectRaw('
                    t.id,
                    t.name,
                    t.brand,
                    COUNT(DISTINCT d.id_dse) as total_dse,
                    COUNT(DISTINCT CASE WHEN DATE(s.date) = ? THEN s.username END) as dse_active,
                    SUM(CASE WHEN DATE(s.date) = ? THEN s.sellin_sp ELSE 0 END) as sp,
                    SUM(CASE WHEN DATE(s.date) = ? THEN s.sellin_voucher ELSE 0 END) as vou,
                    SUM(CASE WHEN DATE(s.date) = ? THEN s.sellin_salmo ELSE 0 END) as salmo
                ', [$date, $date, $date, $date])
                ->leftJoin('dse as d', 'd.id_unit', '=', 't.id')
                ->leftJoin('showcases as s', function ($join) {
                    $join->on('s.username', '=', 'd.name');
                })
                ->where('t.id_secondary', $branch_id)
                ->where('t.is_active', 1)
                ->groupBy('t.id', 't.name', 't.brand')
                ->get();

            // Format data
            $mc = $mc->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'brand' => $item->brand,
                    'total_dse' => $this->format_number($item->total_dse),
                    'dse_active' => $this->format_number($item->dse_active),
                    'sp' => $this->format_number($item->sp),
                    'vou' => $this->format_number($item->vou),
                    'salmo' => $this->format_number($item->salmo),
                ];
            });

            return $mc;
        } elseif ($area_id) {
            $dateParam = $date; // pastikan format tanggal sesuai, misalnya "2025-02-14"

            // Query untuk Branch dengan brand IM3
            $query_im3 = DB::table('territories as branch')
                ->selectRaw("
                    branch.id,
                    CONCAT(branch.name, ' IM3') as name,
                    'IM3' as brand,
                    COALESCE(mc.total_dse, 0) as total_dse,
                    COALESCE(mc.dse_active, 0) as dse_active,
                    COALESCE(mc.sp, 0) as sp,
                    COALESCE(mc.vou, 0) as vou,
                    COALESCE(mc.salmo, 0) as salmo
                ")
                ->leftJoin(DB::raw("(
                    SELECT
                        t.id_secondary,
                        COUNT(DISTINCT d.id_dse) as total_dse,
                        COUNT(DISTINCT CASE WHEN DATE(s.date) = '{$dateParam}' THEN s.username END) as dse_active,
                        SUM(CASE WHEN DATE(s.date) = '{$dateParam}' THEN s.sellin_sp ELSE 0 END) as sp,
                        SUM(CASE WHEN DATE(s.date) = '{$dateParam}' THEN s.sellin_voucher ELSE 0 END) as vou,
                        SUM(CASE WHEN DATE(s.date) = '{$dateParam}' THEN s.sellin_salmo ELSE 0 END) as salmo
                    FROM territories t
                    LEFT JOIN dse d ON d.id_unit = t.id
                    LEFT JOIN showcases s ON s.username = d.name
                    WHERE t.brand = 'IM3'
                    AND t.is_active = true
                    GROUP BY t.id_secondary
                ) as mc"), "mc.id_secondary", "=", "branch.id")
                ->where('branch.id_secondary', $area_id);

            // Query untuk Branch dengan brand 3ID
            $query_3id = DB::table('territories as branch')
                ->selectRaw("
                    branch.id,
                    CONCAT(branch.name, ' 3ID') as name,
                    '3ID' as brand,
                    COALESCE(mc.total_dse, 0) as total_dse,
                    COALESCE(mc.dse_active, 0) as dse_active,
                    COALESCE(mc.sp, 0) as sp,
                    COALESCE(mc.vou, 0) as vou,
                    COALESCE(mc.salmo, 0) as salmo
                ")
                ->leftJoin(DB::raw("(
                    SELECT
                        t.id_secondary,
                        COUNT(DISTINCT d.id_dse) as total_dse,
                        COUNT(DISTINCT CASE WHEN DATE(s.date) = '{$dateParam}' THEN s.username END) as dse_active,
                        SUM(CASE WHEN DATE(s.date) = '{$dateParam}' THEN s.sellin_sp ELSE 0 END) as sp,
                        SUM(CASE WHEN DATE(s.date) = '{$dateParam}' THEN s.sellin_voucher ELSE 0 END) as vou,
                        SUM(CASE WHEN DATE(s.date) = '{$dateParam}' THEN s.sellin_salmo ELSE 0 END) as salmo
                    FROM territories t
                    LEFT JOIN dse d ON d.id_unit = t.id
                    LEFT JOIN showcases s ON s.username = d.name
                    WHERE t.brand = '3ID'
                    AND t.is_active = true
                    GROUP BY t.id_secondary
                ) as mc"), "mc.id_secondary", "=", "branch.id")
                ->where('branch.id_secondary', $area_id);

            // Gabungkan kedua query menggunakan UNION ALL
            $branch = $query_im3->unionAll($query_3id)->GET();

            // Jika perlu, format angka sesuai kebutuhan misalnya:
            $branch = $branch->map(function($item) {
                $item->total_dse = $this->format_number($item->total_dse);
                $item->dse_active = $this->format_number($item->dse_active);
                $item->sp = $this->format_number($item->sp);
                $item->vou = $this->format_number($item->vou);
                $item->salmo = $this->format_number($item->salmo);
                return $item;
            });

            return $branch;
        } elseif ($region_id) {
            $dateParam   = $date;         // misalnya "2025-02-14"
            $regionParam = $region_id;    // ID region (parent)

            $queryIM3 = DB::table('territories as area')
                ->selectRaw("
                    area.id,
                    CONCAT(area.name, ' IM3') as name,
                    'IM3' as brand,
                    COALESCE(SUM(mc.total_dse), 0) as total_dse,
                    COALESCE(SUM(mc.dse_active), 0) as dse_active,
                    COALESCE(SUM(mc.sp), 0) as sp,
                    COALESCE(SUM(mc.vou), 0) as vou,
                    COALESCE(SUM(mc.salmo), 0) as salmo
                ")
                // Join branch: area → branch (branch.id_secondary = area.id)
                ->leftJoin('territories as branch', 'branch.id_secondary', '=', 'area.id')
                // Join subquery untuk akumulasi data MC dengan brand IM3
                ->leftJoin(DB::raw("(
                    SELECT
                        t.id_secondary,
                        COUNT(DISTINCT d.id_dse) as total_dse,
                        COUNT(DISTINCT CASE WHEN DATE(s.date) = '{$dateParam}' THEN s.username END) as dse_active,
                        SUM(CASE WHEN DATE(s.date) = '{$dateParam}' THEN s.sellin_sp ELSE 0 END) as sp,
                        SUM(CASE WHEN DATE(s.date) = '{$dateParam}' THEN s.sellin_voucher ELSE 0 END) as vou,
                        SUM(CASE WHEN DATE(s.date) = '{$dateParam}' THEN s.sellin_salmo ELSE 0 END) as salmo
                    FROM territories t
                    LEFT JOIN dse d ON d.id_unit = t.id
                    LEFT JOIN showcases s ON s.username = d.name
                    WHERE t.brand = 'IM3'
                    AND t.is_active = true
                    GROUP BY t.id_secondary
                ) as mc"), 'mc.id_secondary', '=', 'branch.id')
                ->where('area.id_secondary', $regionParam)
                ->groupBy('area.id', 'area.name');

            $query3ID = DB::table('territories as area')
                ->selectRaw("
                    area.id,
                    CONCAT(area.name, ' 3ID') as name,
                    '3ID' as brand,
                    COALESCE(SUM(mc.total_dse), 0) as total_dse,
                    COALESCE(SUM(mc.dse_active), 0) as dse_active,
                    COALESCE(SUM(mc.sp), 0) as sp,
                    COALESCE(SUM(mc.vou), 0) as vou,
                    COALESCE(SUM(mc.salmo), 0) as salmo
                ")
                ->leftJoin('territories as branch', 'branch.id_secondary', '=', 'area.id')
                ->leftJoin(DB::raw("(
                    SELECT
                        t.id_secondary,
                        COUNT(DISTINCT d.id_dse) as total_dse,
                        COUNT(DISTINCT CASE WHEN DATE(s.date) = '{$dateParam}' THEN s.username END) as dse_active,
                        SUM(CASE WHEN DATE(s.date) = '{$dateParam}' THEN s.sellin_sp ELSE 0 END) as sp,
                        SUM(CASE WHEN DATE(s.date) = '{$dateParam}' THEN s.sellin_voucher ELSE 0 END) as vou,
                        SUM(CASE WHEN DATE(s.date) = '{$dateParam}' THEN s.sellin_salmo ELSE 0 END) as salmo
                    FROM territories t
                    LEFT JOIN dse d ON d.id_unit = t.id
                    LEFT JOIN showcases s ON s.username = d.name
                    WHERE t.brand = '3ID'
                    AND t.is_active = true
                    GROUP BY t.id_secondary
                ) as mc"), 'mc.id_secondary', '=', 'branch.id')
                ->where('area.id_secondary', $regionParam)
                ->groupBy('area.id', 'area.name');

            // Gabungkan kedua query dengan UNION ALL
            $areas = $queryIM3->unionAll($query3ID)->get();

            // Jika diperlukan, format nilai angka:
            $areas = $areas->map(function ($item) {
                $item->total_dse  = $this->format_number($item->total_dse);
                $item->dse_active = $this->format_number($item->dse_active);
                $item->sp         = $this->format_number($item->sp);
                $item->vou        = $this->format_number($item->vou);
                $item->salmo      = $this->format_number($item->salmo);
                return $item;
            });

            return $areas;
        } elseif ($circle_id) {
            $dateParam   = $date;      // Misal: "2025-02-14"
            $circleParam = $circle_id; // ID circle (parent dari region)

            // Subquery untuk mengakumulasi data MC per MC (dari join dengan dse & showcases)
            $mcSub = DB::table('territories as t')
                ->selectRaw("
                    t.id,
                    t.id_secondary,
                    COUNT(DISTINCT d.id_dse) as total_dse,
                    COUNT(DISTINCT CASE WHEN DATE(s.date) = '{$dateParam}' THEN s.username END) as dse_active,
                    SUM(CASE WHEN DATE(s.date) = '{$dateParam}' THEN s.sellin_sp ELSE 0 END) as sp,
                    SUM(CASE WHEN DATE(s.date) = '{$dateParam}' THEN s.sellin_voucher ELSE 0 END) as vou,
                    SUM(CASE WHEN DATE(s.date) = '{$dateParam}' THEN s.sellin_salmo ELSE 0 END) as salmo
                ")
                ->leftJoin('dse as d', 'd.id_unit', '=', 't.id')
                ->leftJoin('showcases as s', 's.username', '=', 'd.name')
                // Jika diperlukan, Anda bisa menambahkan filter misalnya berdasarkan brand (tidak ditampilkan di sini)
                ->where('t.is_active', 1)
                ->groupBy('t.id', 't.id_secondary');

            // Query utama: ambil data region beserta akumulasi data MC dari seluruh branch (melalui area)
            $regionData = DB::table('territories as r')
                ->selectRaw("
                    r.id,
                    r.name,
                    COALESCE(SUM(mc.total_dse), 0) as total_dse,
                    COALESCE(SUM(mc.dse_active), 0) as dse_active,
                    COALESCE(SUM(mc.sp), 0) as sp,
                    COALESCE(SUM(mc.vou), 0) as vou,
                    COALESCE(SUM(mc.salmo), 0) as salmo
                ")
                // Join ke table area (child dari region)
                ->leftJoin('territories as a', 'a.id_secondary', '=', 'r.id')
                // Join ke table branch (child dari area)
                ->leftJoin('territories as b', 'b.id_secondary', '=', 'a.id')
                // Join ke subquery MC, di mana MC.parent (id_secondary) adalah branch.id (b.id)
                ->leftJoin(DB::raw("({$mcSub->toSql()}) as mc"), 'mc.id_secondary', '=', 'b.id')
                ->mergeBindings($mcSub) // Pastikan binding parameter dari subquery ikut digunakan
                ->where('r.id_secondary', $circleParam)
                ->where('r.is_active', 1)
                ->groupBy('r.id', 'r.name')
                ->get();

            // Optional: Format angka sesuai kebutuhan
            $regionData = $regionData->map(function ($item) {
                $item->total_dse = $this->format_number($item->total_dse);
                $item->dse_active = $this->format_number($item->dse_active);
                $item->sp         = $this->format_number($item->sp);
                $item->vou        = $this->format_number($item->vou);
                $item->salmo      = $this->format_number($item->salmo);
                return $item;
            });

            return $regionData;
        } else {
            $circle = DB::table('territories')
                ->select('id', 'name')
                ->where('id', $circle_id)
                ->get();

            dd($circle, 'ok');
        }
    }
}
