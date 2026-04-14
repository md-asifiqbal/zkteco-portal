<?php

namespace App\Services\Applications\Api;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ApiResponse
{
    /**
     * Generate a success response.
     */
    public static function success(mixed $data = [], string $message = 'Success', int $status = 200): JsonResponse|ResourceCollection
    {
        if ($data instanceof ResourceCollection) {
            return $data;
        }

        if (
            is_array($data) &&
            isset($data['data']) &&
            (isset($data['links']) || isset($data['meta']))
        ) {
            return response()->json($data, $status);
        }

        return response()->json([
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Generate an error response dynamically based on the exception.
     */
    public static function error(Throwable $e, ?string $customMessage = null): JsonResponse
    {
        $status = self::getStatusCode($e);

        $response = [
            'message' => $customMessage ?? $e->getMessage(),
        ];

        if ($e instanceof ValidationException) {
            $response['errors'] = $e->errors();
        }

        if (config('app.debug')) {
            $response['exception'] = get_class($e);
            $response['trace'] = $e->getTraceAsString();
        }

        return response()->json($response, $status);
    }

    /**
     * Determine the correct status code based on the exception type.
     */
    private static function getStatusCode(Throwable $e): int
    {
        return match (true) {
            $e instanceof ValidationException => 422,
            $e instanceof AuthorizationException => 403,
            $e instanceof ModelNotFoundException => 404,
            $e instanceof HttpException => $e->getStatusCode(),
            default => 500, // Internal Server Error for unknown cases
        };
    }
}
