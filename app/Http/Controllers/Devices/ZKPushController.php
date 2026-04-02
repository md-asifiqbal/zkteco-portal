<?php

namespace App\Http\Controllers\Devices;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ZKPushController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('ZK Request', [
            'method' => $request->method(),
            'query' => $request->query(),
            'body' => $request->getContent(),
        ]);

        return response('OK');
    }
}
