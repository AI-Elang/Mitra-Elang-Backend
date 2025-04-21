<?php

namespace App\Http\Services\Api\V1;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class OutletService
{
    public function listOutletByPartnerName(Request $request) // ✅ Ensure Request is expected
    {
        $partner_name = $request->route('pt');
        $kecamatan = $request->route('kecamatan');
        $mc_id = auth('api')->user()->territory_id;
        $role_label = auth('api')->user()->role_label;
        $brand = auth('api')->user()->brand;

        if (!$partner_name || $partner_name == ":pt") {
            throw new \Exception('Partner Name is required', 400);
        }

        if (!$kecamatan || $kecamatan == ":kecamatan") {
            throw new \Exception('Kecamatan is required', 400);
        }

        if (substr($partner_name, -3) === ' PT') {
            $partner_name = substr($partner_name, 0, -4);
        }

        $mc = DB::table('territories')
            ->select('id', 'name', 'brand')
            ->where('id', $mc_id)
            ->where('is_active', 1)
            ->first();

        if (!$mc) {
            throw new \Exception('MC not found', 404);
        }

        if ($role_label == "MPC" || $role_label == "3KIOSK" || $role_label == "MITRAIM3") {
            if($role_label == "3KIOSK") {
                $qrFilter = "QR_RAPI";
            }
            else {
                $qrFilter = "QR_CODE";
            }
            $ptfilter = "PARTNER_NAME";
        } else if ($role_label == "MP3") {
            $ptfilter = "NAMA_PT";
            $qrFilter = "QR_RAPI";
        }

//        $qrFilter = 'QR_CODE'; // atau 'DSE_CODE', dll

// SQL untuk SELECT
        $sql = sprintf('
            ioh."%s" AS qr_code,
            ioh."NAMA_TOKO" AS outlet_name,
            ioh."NAMA_PT" AS partner_name,
            ioh."CATEGORY" AS category,
            ioh."brand",
            ioh."STATUS" AS status,
            ioh."longitude",
            ioh."latitude",
            trade."KPI_NAME" AS "program"
            ', $qrFilter);

        $iohJoinCol = 'ioh.' . $qrFilter;

        $data = DB::connection('pgsql2')
            ->table('IOH_OUTLET_BULAN_INI_RAPI_KEC as ioh')
            ->leftJoin('TRADE_PARTNER_OUTLET as trade', $iohJoinCol, '=', 'trade.QR_CODE')
            ->selectRaw($sql)
            ->where($ptfilter, 'like', '%' . $partner_name . '%')
            ->where('ioh.brand', $brand)
            ->where('ioh.STATUS', 'VALID')
            ->whereNotNull('ioh.NAMA_TOKO')
            ->whereNotNull('ioh.CATEGORY')
            ->where('ioh.KEC_BRANCHH', $kecamatan)
            ->where(function($query) {
//                $query->where('trade.VIEW', '=', '1')
                $query->where('trade.KPI_NAME', '=', 'PestaIM3 Poin')
                    ->orWhere('trade.KPI_NAME', '=', 'FUNtasTRI Poin')
                    ->orWhereNull('trade.VIEW');
            })
            ->get();

//        dd($data);

        return $data;
    }



public function listKecamatanByMc(Request $request)
    {
        $brand = auth('api')->user()->brand;
        $mc_id = auth('api')->user()->territory_id;
        $role = auth('api')->user()->role;
        $username = auth('api')->user()->username;
        $role_label = auth('api')->user()->role_label;

        $branch = $request->get('branch');

        if ($role_label == "MPC" || $role_label == "3KIOSK" || $role_label == "MITRAIM3") {
            $pt_name = optional(DB::connection('pgsql2')->table('IOH_OUTLET_BULAN_INI_RAPI_KEC')
                ->select('PARTNER_NAME')
                ->where('PARTNER_ID', $username)
                ->first())->PARTNER_NAME;
            $pt_filter = "PARTNER_NAME";
        } else if ($role_label == "MP3") {
            $pt_name = optional(DB::connection('pgsql2')->table('IOH_OUTLET_BULAN_INI_RAPI_KEC')
                ->select('NAMA_PT')
                ->where('PARTNER_ID', $username)
                ->first())->NAMA_PT;
            $pt_filter = "NAMA_PT";
        }

        $mc_data = DB::table('territories')
            ->select("id", "id_secondary", "name")
            ->where('id', $mc_id)
            ->where('is_active', 1)
            ->first();

        if (!$mc_data) {
            throw new \Exception('Microcluster not found', 404);
        }

        $mc_upper = Str::upper($mc_data->name);
        // Check if string not contains 'MC-'
        if (!Str::contains($mc_upper, 'MC-')) {
            throw new \Exception('Invalid Microcluster ID', 400);
        }

        // Get the name and brand from the microcluster name
        $mc_name = Str::substr($mc_upper, 0, -4);


        // To Do : To CONNECT TO SERVER
        $data = DB::connection('pgsql2')
            ->table('IOH_OUTLET_BULAN_INI_RAPI_KEC')
            ->selectRaw('DISTINCT "KEC_BRANCHH", "NAMA_PT"')
            ->where($pt_filter, $pt_name)
            ->whereNotNull("NAMA_PT");
//            ->whereNotNull("CATEGORY");

        if ($role == 7) {
            $data->where('BSM', $branch);
        }
        else if ($role == 6) {
            $data->where('MC', $mc_name);
        }
        return $data->get();
    }


        public function dropdown()
    {
        $mc = auth()->user()->territory_id;

        if (!$mc) {
            throw new \Exception('MC is required', 400);
        }

        $mc_data = DB::table('territories')
            ->select('id', 'name', 'brand')
            ->where('id', $mc)
            ->where('is_active', 1)
            ->whereLike('name', 'MC-%')
            ->first();

        if (!$mc_data) {
            throw new \Exception('MC not found', 404);
        }

        $mc_id = $mc_data->id;
        $mc_name = Str::substr($mc_data->name, 0, -4);
        $mc_name = Str::upper($mc_name);
        $mc_brand = Str::substr($mc_data->name, -3);

        if ($mc_brand != $mc_data->brand) {
            throw new \Exception('Brand with the name of ' . $mc_data->brand . ' does not match with Microcluster\'s brand of ' . $mc_brand, 400);
        }

        $data = DB::connection('pgsql2')
            ->table('IOH_OUTLET_BULAN_INI_RAPI_KEC')
            ->selectRaw(
                'DISTINCT "NAMA_PT" as partner_name'
            )
            ->where('MC', $mc_name)
            ->where('brand', $mc_brand)
            ->where('STATUS', 'VALID')
            ->whereNotNull('NAMA_PT')
            ->get();

        return collect($data)->transform(function ($item) use ($mc_id, $mc_brand) {
            return [
                'id_secondary' => (int) $mc_id,
                'name' => $item->partner_name,
                'brand' => $mc_brand,
            ];
        });
    }

    public function outletDetail($qrCode)
    {
        $qr_code = $qrCode;
        $brand = auth('api')->user()->brand;

        if (!$qr_code) {
            throw new \Exception('QR Code is required', 400);
        }

        if ($brand == "3ID") {
            $qrFilter = "QR_RAPI";
        } else {
            $qrFilter = "QR_CODE";
        }

        $data = DB::connection('pgsql2')
            ->table('IOH_OUTLET_BULAN_INI_RAPI_KEC')
            ->selectRaw(
                "\"$qrFilter\" as qr_code,
                site_id,
                \"NAMA_TOKO\" as outlet_name,
                \"PARTNER_NAME\" as partner_name,
                \"CATEGORY\" as category,
                brand,
                latitude,
                longitude,
                to_char(mtd_dt, 'DD-MM-YYYY') as mtd_dt,
                \"STATUS\" as status"
            )
            ->where($qrFilter, $qr_code)
            ->where('STATUS', 'VALID')
//            ->toRawSql()
            ->first()
        ;

//        dd($data);


        if (!$data) {
            throw new \Exception('Data not found', 404);
        }

        return $data;
    }

    public function outletProgram($qrCode)
    {
        if (!$qrCode) {
            throw new \Exception('QR Code is required', 400);
        }

        $data = DB::connection('pgsql2')
            ->table('TRADE_PARTNER_OUTLET')
            ->selectRaw(
                'COALESCE(CAST("URUTAN" AS INTEGER), 0) as urutan,
                COALESCE("KPI_NAME", \'data tidak ada\') as kpi_name,
                COALESCE(CAST("MTD" AS INTEGER), 0) as mtd,
                COALESCE(CAST("TARGET" AS INTEGER), 0) as target,
                COALESCE(CAST("ACH" AS FLOAT), 0) as achievement,
                COALESCE(CAST("POIN" AS INTEGER), 0) as poin'
            )
            ->where('QR_CODE', $qrCode)
            ->whereNotNull('URUTAN')
            ->whereNotNull('KPI_NAME')
//            ->whereNotNull('TARGET')
//            ->whereNotNull('ACH')
            ->orderBy('URUTAN', 'asc')
            ->get();

        if ($data->isEmpty()) {
            return 'data not found';
        }

        // Convert achievement to float if needed
        $data = $data->map(function ($item) {
            $item->achievement = (int) $item->achievement;
            $item->mtd = (int) $item->mtd;
            $item->target = (int) $item->target;
            return $item;
        });

        return $data;
    }


    public function outletDetailGa($qrCode)
    {
        $brand = auth('api')->user()->brand;
        if ($brand == "3ID") {
            $qrFilter = "QR_RAPI";
        } else {
            $qrFilter = "QR_CODE";
        }

        $qr_code = $qrCode;

        if (!$qr_code) {
            throw new \Exception('QR Code is required', 400);
        }

        $data = DB::connection('pgsql2')
            ->table('IOH_OUTLET_BULAN_INI_RAPI_KEC')
            ->selectRaw(
                "\"$qrFilter\" as qr_code,
                \"NAMA_TOKO\" as outlet_name,
                ga_lmtd,
                ga_mtd,
                q_sso_lmtd,
                q_sso_mtd,
                q_uro_lmtd,
                q_uro_mtd"
            )
            ->where($qrFilter, $qr_code)
            ->where('STATUS', 'VALID')
            ->first();


        if (!$data) {
            throw new \Exception('Data not found', 404);
        }

        $data = collect($data);

        // Hitung pertumbuhan dan konversi ke integer
        $data['ga_growth'] = $data['ga_lmtd'] == 0 ? 0 :
            (int) round((($data['ga_mtd'] / $data['ga_lmtd']) - 1) * 100);
        $data['q_sso_growth'] = $data['q_sso_lmtd'] == 0 ? 0 :
            (int) round((($data['q_sso_mtd'] / $data['q_sso_lmtd']) - 1) * 100);
        $data['q_uro_growth'] = $data['q_uro_lmtd'] == 0 ? 0 :
            (int) round((($data['q_uro_mtd'] / $data['q_uro_lmtd']) - 1) * 100);

        // Konversi semua nilai numerik ke integer, kecuali qr_code dan outlet_name
        $data['ga_lmtd'] = (int) $data['ga_lmtd'];
        $data['ga_mtd'] = (int) $data['ga_mtd'];
        $data['q_sso_lmtd'] = (int) $data['q_sso_lmtd'];
        $data['q_sso_mtd'] = (int) $data['q_sso_mtd'];
        $data['q_uro_lmtd'] = (int) $data['q_uro_lmtd'];
        $data['q_uro_mtd'] = (int) $data['q_uro_mtd'];

        // Atur urutan key
        $orderedKeys = [
            'qr_code',
            'outlet_name',
            'ga_lmtd',
            'ga_mtd',
            'ga_growth',
            'q_sso_lmtd',
            'q_sso_mtd',
            'q_sso_growth',
            'q_uro_lmtd',
            'q_uro_mtd',
            'q_uro_growth',
        ];

        $data = collect($orderedKeys)->mapWithKeys(function ($key) use ($data) {
            return [$key => $data[$key]];
        });

        return $data;
    }


    public function outletDetailSec($qrCode)
    {
        $qr_code = $qrCode;

        $brand = auth('api')->user()->brand;

        if (!$qr_code) {
            throw new \Exception('QR Code is required', 400);
        }

        if ($brand == "3ID") {
            $qrFilter = "QR_RAPI";
        } else {
            $qrFilter = "QR_CODE";
        }

        if (!$qr_code) {
            throw new \Exception('QR Code is required', 400);
        }

        $data = DB::connection('pgsql2')
            ->table('IOH_OUTLET_BULAN_INI_RAPI_KEC')
            ->selectRaw(
                "\"$qrFilter\" as qr_code,
                \"NAMA_TOKO\" as outlet_name,
                sec_sp_hits_lmtd,
                sec_sp_hits_mtd,
                sec_vou_hits_lmtd,
                sec_vou_hits_mtd"
            )
            ->where($qrFilter, $qr_code)
            ->where('STATUS', 'VALID')
            ->first();


        if (!$data) {
            throw new \Exception('Data not found', 404);
        }

        $data = collect($data);

        // Hitung pertumbuhan dan konversi ke integer
        $data['sec_sp_hits_growth'] = $data['sec_sp_hits_lmtd'] == 0 ? 0 :
            (int) round((($data['sec_sp_hits_mtd'] / $data['sec_sp_hits_lmtd']) - 1) * 100);
        $data['sec_vou_hits_growth'] = $data['sec_vou_hits_lmtd'] == 0 ? 0 :
            (int) round((($data['sec_vou_hits_mtd'] / $data['sec_vou_hits_lmtd']) - 1) * 100);

        // Konversi semua nilai numerik ke integer, kecuali qr_code dan outlet_name
        $data['sec_sp_hits_lmtd'] = (int) $data['sec_sp_hits_lmtd'];
        $data['sec_sp_hits_mtd'] = (int) $data['sec_sp_hits_mtd'];
        $data['sec_vou_hits_lmtd'] = (int) $data['sec_vou_hits_lmtd'];
        $data['sec_vou_hits_mtd'] = (int) $data['sec_vou_hits_mtd'];

        // Atur urutan key
        $orderedKeys = [
            'qr_code',
            'outlet_name',
            'sec_sp_hits_lmtd',
            'sec_sp_hits_mtd',
            'sec_sp_hits_growth',
            'sec_vou_hits_lmtd',
            'sec_vou_hits_mtd',
            'sec_vou_hits_growth',
        ];

        $data = collect($orderedKeys)->mapWithKeys(function ($key) use ($data) {
            return [$key => $data[$key]];
        });

        return $data;
    }


    public function outletDetailSupply($qrCode)
    {
        $brand = auth('api')->user()->brand;

        if ($brand == "3ID") {
            $qrFilter = "QR_RAPI";
        } else {
            $qrFilter = "QR_CODE";
        }

        $qr_code = $qrCode;

        if (!$qr_code) {
            throw new \Exception('QR Code is required', 400);
        }

        if (!$qr_code) {
            throw new \Exception('QR Code is required', 400);
        }

        $data = DB::connection('pgsql2')
            ->table('IOH_OUTLET_BULAN_INI_RAPI_KEC')
            ->selectRaw(
                "\"$qrFilter\" as qr_code,
                \"NAMA_TOKO\" as outlet_name,
                supply_sp_lmtd,
                supply_sp_mtd,
                supply_vo_lmtd,
                supply_vo_mtd"
            )
            ->where($qrFilter, $qr_code)
            ->where('STATUS', 'VALID')
            ->first();


        if (!$data) {
            throw new \Exception('Data not found', 404);
        }

        $data = collect($data);

        // Hitung growth dan konversi ke integer
        $data['supply_sp_growth'] = $data['supply_sp_lmtd'] == 0 ? 0 :
            (int) round((($data['supply_sp_mtd'] / $data['supply_sp_lmtd']) - 1) * 100);
        $data['supply_vo_growth'] = $data['supply_vo_lmtd'] == 0 ? 0 :
            (int) round((($data['supply_vo_mtd'] / $data['supply_vo_lmtd']) - 1) * 100);

        // Konversi nilai numerik ke integer
        $data['supply_sp_lmtd'] = (int) $data['supply_sp_lmtd'];
        $data['supply_sp_mtd'] = (int) $data['supply_sp_mtd'];
        $data['supply_vo_lmtd'] = (int) $data['supply_vo_lmtd'];
        $data['supply_vo_mtd'] = (int) $data['supply_vo_mtd'];

        // Atur urutan key
        $orderedKeys = [
            'qr_code',
            'outlet_name',
            'supply_sp_lmtd',
            'supply_sp_mtd',
            'supply_sp_growth',
            'supply_vo_lmtd',
            'supply_vo_mtd',
            'supply_vo_growth',
        ];

        $data = collect($orderedKeys)->mapWithKeys(function ($key) use ($data) {
            return [$key => $data[$key]];
        });

        return $data;
    }


    public function outletDetailDemand($qrCode)
    {
        $qr_code = $qrCode;

        $brand = auth('api')->user()->brand;

        if (!$qr_code) {
            throw new \Exception('QR Code is required', 400);
        }

        if ($brand == "3ID") {
            $qrFilter = "QR_RAPI";
        } else {
            $qrFilter = "QR_CODE";
        }

        if (!$qr_code) {
            throw new \Exception('QR Code is required', 400);
        }

        $data = DB::connection('pgsql2')
            ->table('IOH_OUTLET_BULAN_INI_RAPI_KEC')
            ->selectRaw(
                "\"$qrFilter\" as qr_code,
                \"NAMA_TOKO\" as outlet_name,
                tert_sp_lmtd,
                tert_sp_mtd,
                tert_vo_lmtd,
                tert_vo_mtd"
            )
            ->where($qrFilter, $qr_code)
            ->where('STATUS', 'VALID')
            ->first();


        if (!$data) {
            throw new \Exception('Data not found', 404);
        }

        $data = collect($data);

        // Hitung growth dan konversi ke integer
        $data['tert_sp_growth'] = $data['tert_sp_lmtd'] == 0 ? 0 :
            (int) round((($data['tert_sp_mtd'] / $data['tert_sp_lmtd']) - 1) * 100);
        $data['tert_vo_growth'] = $data['tert_vo_lmtd'] == 0 ? 0 :
            (int) round((($data['tert_vo_mtd'] / $data['tert_vo_lmtd']) - 1) * 100);

        // Konversi semua nilai numerik ke integer
        $data['tert_sp_lmtd'] = (int) $data['tert_sp_lmtd'];
        $data['tert_sp_mtd'] = (int) $data['tert_sp_mtd'];
        $data['tert_vo_lmtd'] = (int) $data['tert_vo_lmtd'];
        $data['tert_vo_mtd'] = (int) $data['tert_vo_mtd'];

        // Atur urutan key
        $orderedKeys = [
            'qr_code',
            'outlet_name',
            'tert_sp_lmtd',
            'tert_sp_mtd',
            'tert_sp_growth',
            'tert_vo_lmtd',
            'tert_vo_mtd',
            'tert_vo_growth',
        ];

        $data = collect($orderedKeys)->mapWithKeys(function ($key) use ($data) {
            return [$key => $data[$key]];
        });

        return $data;
    }


    public function listOutletLocation()
    {
        $data = DB::connection('pgsql2')
            ->table('IOH_OUTLET_BULAN_INI_RAPI_KEC')
            ->selectRaw(
                '
                "QR_CODE" as qr_code,
                "NAMA_TOKO" as outlet_name,
                latitude,
                longitude'
            )
            ->distinct()
            ->limit(4)
            ->get();

        return $data;
    }
}
