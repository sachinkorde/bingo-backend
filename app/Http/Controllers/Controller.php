<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    /**
     * Standard success envelope. Matches what the Unity client parses:
     * { success, message, data, ...extra }
     */
    protected function ok($data = null, string $message = 'Success', array $extra = [], int $code = 200): JsonResponse
    {
        return response()->json(array_merge([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $extra), $code);
    }

    /**
     * Standard failure envelope.
     */
    protected function fail(string $message = 'Something went wrong', int $code = 400, $data = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
        ], $code);
    }
}
