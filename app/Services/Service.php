<?php

namespace App\Services;

use Illuminate\Http\Exceptions\HttpResponseException;

class Service
{
    public function throwExceptionJson($message = 'An error occurred', $code = 500, $errors = null)
    {
        $response = [
            'status' => 'error',
            'message' => $message,
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        throw new HttpResponseException(response()->json($response, $code));
    }
}
