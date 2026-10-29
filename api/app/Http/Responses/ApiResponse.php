<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

/**
 * Builds the {success, message, data, errors, meta} envelope used by every API response.
 */
class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'OK', int $status = 200, ?array $meta = null): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
            'meta' => $meta,
        ], $status);
    }

    public static function error(string $message, int $status, ?array $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
            'meta' => null,
        ], $status);
    }
}
