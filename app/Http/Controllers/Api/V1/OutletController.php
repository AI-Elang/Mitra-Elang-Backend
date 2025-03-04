<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseApiController as Controller;
use App\Http\Services\Api\V1\OutletService;
use Illuminate\Http\Request;

class OutletController extends Controller
{
    protected OutletService $service;
    public function __construct(OutletService $service)
    {
        $this->service = $service;
    }

}
