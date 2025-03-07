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
        if ($mcId == 0 || $mcId == null) {
            throw new \Exception('MC ID is required', 400);
        }

        $month = $request->month;
        $year = $request->year;

        if ($month == null || $year == null) {
            throw new \Exception('Month and year are required', 400);
        }

        $mcName = DB::table('territory_dashboards')
            ->where('id', $mcId)
            ->value('name');

        $distinctDse = DB::connection('pgsql2')->table('IOH_OUTLET_BULAN_INI_RAPI_KEC')
            ->where('PARTNER_ID', $username)
            ->where('MC', $mcName)
            ->distinct()
            ->pluck('DSE_CODE')
            ->toArray();

        $dse = DB::table('dse as d')
            ->select('d.id_dse as dse_id', 'd.name as dse_name', 'd.id_unit', 'd.status', 't.name as territory_name', 't.id as territory_id')
            ->join('territories as t', 'd.id_unit', '=', 't.id')
            ->where('t.id', $mcId)
            ->whereIn('d.id_dse', $distinctDse)
            ->where('t.is_active', 1)
            ->get();

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
            // make mtd_dt as 2025-January
            $d->mtd_dt = Carbon::createFromDate($year, $month, 1)->format('F') . ' ' . $year;

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
                'mtd_dt' => $item->mtd_dt
            ];
        });

        return $dse;
    }

    public function getDseAiDaily($request)
    {
        $username = auth()->user()->username;
        $mcId = auth()->user()->territory_id;
        $date = $request->date;

        if ($date == null) {
            throw new \Exception('Date is required', 400);
        }

        $mcName = DB::table('territory_dashboards')
            ->where('id', $mcId)
            ->value('name');

        $distinctDse = DB::connection('pgsql2')->table('IOH_OUTLET_BULAN_INI_RAPI_KEC')
            ->where('PARTNER_ID', $username)
            ->where('MC', $mcName)
            ->distinct()
            ->pluck('DSE_CODE')
            ->toArray();

        $dse = DB::table('dse as d')
            ->select('d.id_dse as dse_id', 'd.name as dse_name', 'd.id_unit', 'd.status', 't.name as territory_name', 't.id as territory_id')
            ->join('territories as t', 'd.id_unit', '=', 't.id')
            ->whereIn('d.id_dse', $distinctDse)
            ->where('t.id', $mcId)
            ->where('t.is_active', 1)
            ->get();

        $dseIds = $dse->pluck('dse_id')->toArray();

        $dse_daily_data = DB::table('showcases')
            ->select(
                'username',
                DB::raw('SUM(voucher_im3) as voucher_im3'),
                DB::raw('SUM(voucher_tri) as voucher_tri'),
                DB::raw('SUM(perdana_im3) as perdana_im3'),
                DB::raw('SUM(perdana_tri) as perdana_tri'),
                DB::raw('SUM(sellin_sp) as sp'),
                DB::raw('SUM(sellin_voucher) as vou'),
                DB::raw('SUM(sellin_salmo) as salmo'),
                DB::raw('COUNT(outlet_id) as visit')
            )
            ->whereIn('username', $dseIds)
            ->whereDate('date', $date)
            ->groupBy('username')
            ->get()
            ->keyBy('username');

        $showcase_in_data = DB::table('showcases')
            ->select('username', DB::raw('MIN(date) as checkin'))
            ->whereDate('date', $date)
            ->whereIn('username', $dseIds)
            ->groupBy('username')
            ->get()
            ->keyBy('username');

        $showcase_out_data = DB::table('showcases')
            ->select('username', DB::raw('MAX(status_timestamp) as checkout'))
            ->whereDate('date', $date)
            ->whereIn('username', $dseIds)
            ->groupBy('username')
            ->get()
            ->keyBy('username');

        foreach ($dse as $d) {
            $d->perdana_im3 = "0";
            $d->perdana_tri = "0";
            $d->voucher_im3 = "0";
            $d->voucher_tri = "0";
            $d->visit = "0";
            $d->sp = "0";
            $d->vou = "0";
            $d->salmo = "0";
            $d->mtd_dt = $date;
            $d->checkin = '-';
            $d->checkout = '-';
            $d->duration = '-';

            if (isset($dse_daily_data[$d->dse_id])) {
                $dtd = $dse_daily_data[$d->dse_id];
                $d->perdana_im3 = $this->format_number($dtd->perdana_im3 ?? 0);
                $d->perdana_tri = $this->format_number($dtd->perdana_tri ?? 0);
                $d->voucher_im3 = $this->format_number($dtd->voucher_im3 ?? 0);
                $d->voucher_tri = $this->format_number($dtd->voucher_tri ?? 0);
                $d->visit = $this->format_number($dtd->visit ?? 0);
                $d->sp = $this->format_number($dtd->sp ?? 0);
                $d->vou = $this->format_number($dtd->vou ?? 0);
                $d->salmo = $this->format_number($dtd->salmo ?? 0);
            }

            if (isset($showcase_in_data[$d->dse_id])) {
                $d->checkin = Carbon::parse($showcase_in_data[$d->dse_id]->checkin)->format('H:i:s');
            }

            if (isset($showcase_out_data[$d->dse_id])) {
                $d->checkout = Carbon::parse($showcase_out_data[$d->dse_id]->checkout)->format('H:i:s');
            }

            if ($d->checkin !== '-' && $d->checkout !== '-') {
                $d->duration = Carbon::parse($showcase_in_data[$d->dse_id]->checkin)
                    ->diff(Carbon::parse($showcase_out_data[$d->dse_id]->checkout))
                    ->format('%H:%I:%S');
            }
        }

        return $dse->map(function ($item) {
            return [
                'id' => $item->dse_id,
                'name' => $item->dse_name,
                'mc_id' => (int) $item->territory_id,
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
                'checkin' => $item->checkin,
                'checkout' => $item->checkout,
                'duration' => $item->duration,
            ];
        });
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
