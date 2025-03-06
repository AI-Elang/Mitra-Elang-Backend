<?php

namespace App\Http\Services\Api\V1;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SiteService
{
    public function listKecamatanByMc()
    {
        $mc_id = auth('api')->user()->territory_id;
        $brand = auth('api')->user()->brand;
        $username = auth('api')->user()->username;

        if (!$brand) {
            throw new \Exception('Brand is required', 400);
        }

        if ($brand != '3ID' && $brand != 'IM3') {
            throw new \Exception('Invalid Brand', 400);
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
        $mc_brand = Str::substr($mc_upper, -3);

        if ($mc_brand !== $brand) {
            throw new \Exception('Brand with the name of ' . $brand . ' does not match with Microcluster\'s brand of ' . $mc_brand, 400);
        }

        // Set which column to be queried based on the brand
        if ($brand === '3ID') {
            $pt_column = "NAMA_PT3ID";
        } else {
            $pt_column = "NAMA_PTIM3";
        }

        // To Do : To CONNECT TO SERVER
        $data = DB::connection('pgsql2')
            ->table('PREP_RAPI_IMPALA_SITE_BULAN_INI')
            ->selectRaw('
            DISTINCT(kecamatan_unik),
            "' . $pt_column . '" AS pt_name,
            "LRK" AS lrk
            ')
            ->where($pt_column, $username)
            ->where('MC', $mc_name)
            ->get();

        return $data;
    }

    public function listSiteByKecamatan(Request $request)
    {
        $mcId = auth('api')->user()->territory_id;
        $brand = auth('api')->user()->brand;
        $kecamatan = Str::upper($request->kecamatan);

        if (!$kecamatan) {
            throw new \Exception('Kecamatan is required', 400);
        }

        if (!$brand) {
            throw new \Exception('Brand is required', 400);
        }

        // Check if brand is valid
        if ($brand != '3ID' && $brand != 'IM3') {
            throw new \Exception('Invalid Brand', 400);
        }

        $mc_data = DB::table('territories')
            ->select( "name")
            ->where('id', $mcId)
            ->where('is_active', 1)
            ->first();

        $data = DB::connection('pgsql2')
            ->table('PREP_RAPI_IMPALA_SITE_BULAN_INI')
            ->select(
                "site_id",
                "site_name",
                // when the brand is 3ID, take from column "LRS_3ID", otherwise "LRS_IM3"
                DB::raw('CASE WHEN \'' . $brand . '\' = \'3ID\' THEN lrs_3id ELSE lrs_im3 END as lrs'),
                'SITE_CATEGORY as category')
            ->where('kecamatan_unik', $kecamatan)
            ->get();

        $data->transform(function ($data) use ($brand) {
            return [
                'id' => $data->site_id,
                'name' => $data->site_name,
                'brand' => $brand,
                'status' => $data->lrs . ' - ' . $data->category,
            ];
        });

        return $data;
    }

    public function siteDetail($site_id)
    {
        $brand = auth('api')->user()->brand;

        if (!$brand) {
            throw new \Exception('Brand is required', 400);
        }

        if ($brand != '3ID' && $brand != 'IM3') {
            throw new \Exception('Invalid Brand', 400);
        }

        if ($brand === 'IM3') {
            $select_query = 'site_id, site_name, "NAMA_PTIM3" as pt_name, lrs_im3 as category, lat, long, asof_dt, "IM3_OUTLET_VALID" as outlet_valid';
        }

        if ($brand === '3ID') {
            $select_query = 'site_id, site_name, "NAMA_PT3ID" as pt_name, lrs_3id as category, lat, long, asof_dt, "3ID_OUTLET_VALID" as outlet_valid';
        }

        $data = DB::connection('pgsql2')
            ->table('PREP_RAPI_IMPALA_SITE_BULAN_INI')
            ->selectRaw($select_query)
            ->where('site_id', $site_id)
            ->first();

        if (!$data) {
            throw new \Exception('Site not found', 404);
        }

        $data = collect($data);

        // Temukan posisi key `category`
        $index = $data->keys()->search('category');

        // Pisahkan collection menjadi dua bagian: sebelum dan sesudah `category`
        $before = $data->take($index + 1);
        $after = $data->skip($index + 1);

        // Gabungkan dengan key `brand`
        return $before->merge(['brand' => $brand])->merge($after);
    }

    public function siteDetailRevenue($site_id)
    {
        $brand = auth('api')->user()->brand;

        if (!$brand) {
            throw new \Exception('Brand is required', 400);
        }

        if ($brand != '3ID' && $brand != 'IM3') {
            throw new \Exception('Invalid Brand', 400);
        }

        if ($brand === 'IM3') {
            $select_query = '"IM3_TOTAL_REV_NET+lmtd" as rev_lmtd, "IM3_TOTAL_REV_NET+mtd" as rev_mtd, "G_IM3_TOTAL_REV" as growth';
        }

        if ($brand === '3ID') {
            $select_query = '"3ID_TOTAL_REV_NET+lmtd" as rev_lmtd, "3ID_TOTAL_REV_NET+mtd" as rev_mtd, "G_3ID_TOTAL_REV" as growth';
        }

        $data = DB::connection('pgsql2')
            ->table('PREP_RAPI_IMPALA_SITE_BULAN_INI')
            ->selectRaw($select_query)
            ->where('site_id', $site_id)
            ->first();

        if (!$data) {
            throw new \Exception('Site not found', 404);
        }

        // Transform the data value into integer except where the key contains word "GROWTH"
        return collect($data)->map(function ($value, $key) {
            if (Str::contains($key, 'growth')) {
                // return the value as percentage
                return round($value * 100, 2);
            }

            return intval($value);
        });
    }

    public function siteDetailRgu($site_id)
    {
        $brand = auth('api')->user()->brand;

        if (!$brand) {
            throw new \Exception('Brand is required', 400);
        }

        if ($brand != '3ID' && $brand != 'IM3') {
            throw new \Exception('Invalid Brand', 400);
        }

        if ($brand === 'IM3') {
            $select_query = '"IM3_RGU_90D+lmtd" as rgu90_lmtd, "IM3_RGU_90D+mtd" as rgu90_mtd, "G_IM3_RGU90D" as growth';
        }

        if ($brand === '3ID') {
            $select_query = '"3ID_RGU_90D+lmtd" as rgu90_lmtd, "3ID_RGU_90D+mtd" as rgu90_mtd, "G_3ID_RGU90D" as growth';
        }

        $data = DB::connection('pgsql2')
            ->table('PREP_RAPI_IMPALA_SITE_BULAN_INI')
            ->selectRaw($select_query)
            ->where('site_id', $site_id)
            ->first();

        if (!$data) {
            throw new \Exception('Site not found', 404);
        }

        // Transform the data value into integer except where the key contains word "GROWTH"
        return collect($data)->map(function ($value, $key) {
            if (Str::contains($key, 'growth')) {
                // return the value as percentage
                return round($value * 100, 2);
            }

            return intval($value);
        });
    }

    public function siteDetailGa($site_id)
    {
        $brand = auth('api')->user()->brand;

        if (!$brand) {
            throw new \Exception('Brand is required', 400);
        }

        if ($brand != '3ID' && $brand != 'IM3') {
            throw new \Exception('Invalid Brand', 400);
        }

        if ($brand === 'IM3') {
            $select_query = '"IM3_RGU_GA+lmtd" as ga_lmtd, "IM3_RGU_GA+mtd" as ga_mtd, "G_IM3_RGU_GA" as growth';
        }

        if ($brand === '3ID') {
            $select_query = '"3ID_RGU_GA+lmtd" as ga_lmtd, "3ID_RGU_GA+mtd" as ga_mtd, "G_3ID_RGU_GA" as growth';
        }

        $data = DB::connection('pgsql2')
            ->table('PREP_RAPI_IMPALA_SITE_BULAN_INI')
            ->selectRaw($select_query)
            ->where('site_id', $site_id)
            ->first();

        if (!$data) {
            throw new \Exception('Site not found', 404);
        }

        // Transform the data value into integer except where the key contains word "GROWTH"
        return collect($data)->map(function ($value, $key) {
            if (Str::contains($key, 'growth')) {
                // return the value as percentage
                return round($value * 100, 2);
            }

            return intval($value);
        });
    }

    public function siteDetailVlr($site_id)
    {
        $brand = auth('api')->user()->brand;

        if (!$brand) {
            throw new \Exception('Brand is required', 400);
        }

        if ($brand != '3ID' && $brand != 'IM3') {
            throw new \Exception('Invalid Brand', 400);
        }

        if ($brand === 'IM3') {
            $select_query = '"IM3_VLR_PREPAID+lmtd" as vlr_lmtd, "IM3_VLR_PREPAID+mtd" as vlr_mtd, "G_IM3_VLR_PREPAID" as growth';
        }

        if ($brand === '3ID') {
            $select_query = '"3ID_VLR_PREPAID+lmtd" as vlr_lmtd, "3ID_VLR_PREPAID+mtd" as vlr_mtd, "G_3ID_VLR_PREPAID" as growth';
        }

        $data = DB::connection('pgsql2')
            ->table('PREP_RAPI_IMPALA_SITE_BULAN_INI')
            ->selectRaw($select_query)
            ->where('site_id', $site_id)
            ->first();

        if (!$data) {
            throw new \Exception('Site not found', 404);
        }

        // Transform the data value into integer except where the key contains word "GROWTH"
        return collect($data)->map(function ($value, $key) {
            if (Str::contains($key, 'growth')) {
                // return the value as percentage
                return round($value * 100, 2);
            }

            return intval($value);
        });
    }

    public function siteDetailOutlet($site_id)
    {
        $brand = auth('api')->user()->brand;

        if (!$brand) {
            throw new \Exception('Brand is required', 400);
        }

        if ($brand != '3ID' && $brand != 'IM3') {
            throw new \Exception('Invalid Brand', 400);
        }

        $data = DB::connection('pgsql2')
            ->table('IOH_OUTLET_BULAN_INI_RAPI')
            ->select(
                'QR_CODE as qr_code',
                'NAMA_TOKO as outlet_name',
                'brand',
                'ga_mtd',
                'sec_saldo_mtd',
                'supply_sp_mtd',
                'supply_vo_mtd',
            )
            ->where('site_id', $site_id)
            ->where('brand', $brand)
            ->where('STATUS', 'VALID')
            ->get();

        $data->transform(function ($data) {
            return [
                'qr_code' => $data->qr_code,
                'outlet_name' => $data->outlet_name,
                'brand' => $data->brand,
                'ga_mtd' => $this->formatNumber($data->ga_mtd),
                'sec_saldo_mtd' => $this->formatNumber($data->sec_saldo_mtd),
                'supply_sp_mtd' => $this->formatNumber($data->supply_sp_mtd),
                'supply_vo_mtd' => $this->formatNumber($data->supply_vo_mtd),
            ];
        });

        return $data;
    }

    public function formatNumber($number): string
    {
        return number_format($number, 0, ',', '.');
    }

    public function getSiteDashboard($request)
    {
        $circle_id = $request->circle;
        $region_id = $request->region ?? null;
        $area_id = $request->area ?? null;
        $branch_id = $request->branch ?? null;

        if (!$circle_id) {
            throw new \Exception('Circle is required', 400);
        }

        if ($area_id && !$region_id) {
            throw new \Exception('Region is required when Area is selected', 400);
        }

        if ($branch_id && !$area_id) {
            throw new \Exception('Area is required when Branch is selected', 400);
        }

        // Ambil data territory dari koneksi utama
        $territory = DB::table('territories')
            ->select('id', "name")
            ->where('is_active', 1);

        if ($branch_id) {
            $territory = $territory->where('id_secondary', $branch_id)->get();
        } elseif ($area_id) {
            $territory = $territory->where('id_secondary', $area_id)->get();
        } elseif ($region_id) {
            $territory = $territory->where('id_secondary', $region_id)->get();
        } else {
            $territory = $territory->where('id_secondary', $circle_id)->get();
        }

        // Simpan territory names untuk digunakan di query kedua
        $territory_names = $territory->pluck('name');
        if ($branch_id) {
            $territory_names_no_brand = $territory_names->map(function ($name) {
                return Str::substr($name, 0, -4);
            });
        }

        // Query data dari koneksi pgsql2
        $siteSummary = DB::connection('pgsql2')
            ->table('ELANG_SAMPAH_SITE_SUMMARY_FINAL')
            ->select(
                'ROLE as name',
                'KECAMATAN as kec_count',
                'KECAMATAN_LRK as kec_lrk_count',
                'LRS_IOH as site_lrs',
                'SITE as site_count',
            )
            ->whereIn('ROLE', $territory_names_no_brand ?? $territory_names)
            ->distinct();

        if ($branch_id) {
            $siteSummary = $siteSummary->where('JABATAN', 'MC');
        } elseif ($area_id) {
            $siteSummary = $siteSummary->where('JABATAN', 'BSM');
        } elseif ($region_id) {
            $siteSummary = $siteSummary->where('JABATAN', 'HOS');
        } else {
            $siteSummary = $siteSummary->where('JABATAN', 'HOR');
        }

        $siteSummary = $siteSummary->get();

        // Lakukan join di PHP menggunakan Collection
        if ($branch_id) {
            // Duplicate the result from site summary
            $siteSummary3ID = $siteSummary->map(function ($item) {
                $newItem = clone $item; // Clone object agar tidak menimpa data asli
                $newItem->name = $newItem->name . ' 3ID';
                return $newItem;
            });

            $siteSummaryIM3 = $siteSummary->map(function ($item) {
                $newItem = clone $item; // Clone lagi untuk dataset yang berbeda
                $newItem->name = $newItem->name . ' IM3';
                return $newItem;
            });

            $siteSummary = $siteSummary3ID->merge($siteSummaryIM3);
        }

        // Transform the data
        // merge it with territory to get the id of the territories
        $siteSummary = $siteSummary->map(function ($item) use ($territory, $branch_id) {
            $territory_id = $territory->where('name', $item->name)->first()->id;

            return collect($item)->merge([
                'id' => $territory_id,
            ]);
        });

        // Arrange the data returned response
        return $siteSummary->transform(function ($item) {
            return [
                'id' => $item->get('id'),  // Menggunakan get() untuk mengambil nilai
                'name' => $item->get('name'),
                'kecamatan' => $this->formatNumber((int)$item->get('kec_count')),
                'kecamatan_lrk' => $this->formatNumber((int)$item->get('kec_lrk_count')),
                'site_lrs' => $this->formatNumber((int)$item->get('site_lrs')),
                'site' => $this->formatNumber((int)$item->get('site_count')),
            ];
        })->sortBy('id')->values();
    }
}
