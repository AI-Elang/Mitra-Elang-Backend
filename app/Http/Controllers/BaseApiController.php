<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class BaseApiController extends Controller
{
    public function ApiExceptionError($exception, $status = 400): JsonResponse
    {
        return response()->json([
            'meta' => [
                'status_code' => $status,
                "success" => false,
                'error' => is_array($exception) ? $exception : $exception
            ]
        ], $status);
    }

    public function ApiMessageError($message = "Error", $status = 400): JsonResponse
    {
        return response()->json([
            'meta' => [
                'status_code' => $status,
                "success" => false,
                'error' => $message
            ]
        ], $status);
    }

    public function respond($data, $status = 200): JsonResponse
    {
        return response()->json($data, $status);
    }

    /**
     * Paginate from array
     *
     * @param array $data
     * @return \Illuminate\Pagination\AbstractPaginator|LengthAwarePaginator
     */
    public function paginate($data, $per_page = 5, $options = null): \Illuminate\Pagination\AbstractPaginator|LengthAwarePaginator
    {
        //Get current page form url e.g. &page=6
        $currentPage = LengthAwarePaginator::resolveCurrentPage();

        //Create a new Laravel collection from the array data
        $collection = new Collection($data);

        //Slice the collection to get the items to display in current page
        $currentPageResults = $collection->slice(($currentPage - 1) * $per_page, $per_page)->values();

        //Create our paginator and add it to the data array
        $data['results'] = new LengthAwarePaginator($currentPageResults, count($collection), $per_page);

        return $data['results']->setPath(url()->current() . '?' . http_build_query(['per_page' => $per_page]));
    }
}
