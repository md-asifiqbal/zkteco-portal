<?php

namespace App\Http\Controllers;

use App\Services\Applications\Api\ApiResponse;
use Exception;

abstract class Controller
{
    protected function handleRequest(callable $callback)
    {
        try {
            return $callback();
        } catch (Exception $e) {
            return ApiResponse::error($e);
        }
    }
}
