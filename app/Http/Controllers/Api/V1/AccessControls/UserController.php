<?php

namespace App\Http\Controllers\Api\V1\AccessControls;

use App\Http\Controllers\Controller;
use App\Services\Applications\Api\ApiResponse;
use App\Services\Modules\AccessControls\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(protected UserService $userService) {}

    public function index(Request $request)
    {
        return $this->handleRequest(function () use ($request) {
            $data = $this->userService->getUsers($request->all());

            return ApiResponse::success($data);
        });
    }

    public function store(Request $request)
    {
        return $this->handleRequest(function () use ($request) {
            $data = $this->userService->createUser($request->all());

            return ApiResponse::success($data);
        });
    }

    public function disabledUser(Request $request, string $userId)
    {
        return $this->handleRequest(function () use ($userId, $request) {

            $data = $this->userService->disabledUser($userId, $request->all());

            return ApiResponse::success($data);
        });
    }

    public function enabledUser(Request $request, string $userId)
    {
        return $this->handleRequest(function () use ($userId, $request) {
            $data = $this->userService->enabledUser($userId, $request->all());

            return ApiResponse::success($data);
        });
    }
}
