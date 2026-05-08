<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
    protected function paginationResponse(array $data, int $statusCode = 200, string $message = '', string $error = '')
    {
        return response()->json([
            'code' => $statusCode,
            'data' => $data,
            'error' => $error,
            'message' => $message,
        ], $statusCode);
    }

    protected function successResponse($data = null, string $message = 'Success', int $statusCode = 200)
    {
        return $this->apiResponse($statusCode, $data, $message, (object) []);
    }

    protected function errorResponse(string $message, int $statusCode = 400, $errors = null, $data = null)
    {
        $normalizedErrors = $errors;

        if ($normalizedErrors === null) {
            $normalizedErrors = (object) [];
        }

        return $this->apiResponse($statusCode, $data, $message, $normalizedErrors);
    }

    protected function apiResponse(int $statusCode, $data, string $message, $errors)
    {
        $normalizedData = $data;

        if ($normalizedData === null) {
            $normalizedData = (object) [];
        }

        return response()->json([
            'code' => $statusCode,
            'data' => $normalizedData,
            'message' => $message,
            'errors' => $errors,
        ], $statusCode);
    }
}
