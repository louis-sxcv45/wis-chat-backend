<?php

namespace App\Helpers;

class ResponseFormatter
{
    public static function success(array $payload = [], int $code = 200)
    {
        return response()->json([
            'status' => 'success',
            'code' => $code,
            ...$payload,
        ], $code);
    }

    public static function error($payload = [], $code = 400)
    {
        return response()->json([
            'status' => 'error',
            'code' => $code,
            ...$payload,
        ], $code);
    }
}
