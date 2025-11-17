<?php

namespace App\Http\Controllers;

abstract class Controller
{
     /**
     * Standardized success response.
     *
     * @param mixed $data
     * @param string $message
     * @param int $code
     * @return \Illuminate\Http\JsonResponse
     */
    protected function success($data, $message = 'Operation successful', $code = 200)
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $code);
    }

    /**
     * Standardized error response.
     *
     * @param string $message
     * @param int $code
     * @param mixed $errors
     * @return \Illuminate\Http\JsonResponse
     */
    protected function error($message = 'An error occurred', $code = 500, $errors = null)
    {
        $response = [
            'status' => 'error',
            'message' => $message,
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Standardized paginated response.
     *
     * @param \Illuminate\Pagination\LengthAwarePaginator $paginatedData
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function paginate($paginatedData, $message = 'Data retrieved successfully')
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $paginatedData->items(),
            'meta' => [
                'current_page' => $paginatedData->currentPage(),
                'per_page' => $paginatedData->perPage(),
                'total' => $paginatedData->total(),
                'last_page' => $paginatedData->lastPage()
            ]
        ], 200);
    }
}
