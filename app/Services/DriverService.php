<?php

namespace App\Services;

use App\Models\Driver;
use App\Services\Service;
use App\Services\FileStorage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\DriverResource;
use Illuminate\Http\Exceptions\HttpResponseException;

class DriverService extends Service
{
    /**
     * Paginate drivers
     */
    public function paginate($perPage = 10)
    {
        $drivers = Driver::with('vehicleType')->paginate($perPage);
        return DriverResource::collection($drivers);
    }

    /**
     * Find driver by ID
     */
    public function find($id)
    {
        $driver = Driver::with('vehicleType','ratings')->find($id);

        if (!$driver) {
            $this->throwExceptionJson('Driver not found', 404);
        }

        return $driver;
    }

    /**
     * Create new driver
     */
    public function storeDriver(array $data)
    {
        // try {
            $driver = Driver::create([
                'driver_name'      => $data['driver_name'],
                'phone'            => $data['phone'],
                'password'         => bcrypt($data['password']),

                'driver_image'     => FileStorage::storeFile($data['driver_image'], 'Driver', 'img'),
                'front_id_image'   => FileStorage::storeFile($data['front_id_image'], 'Driver', 'img'),
                'back_id_image'    => FileStorage::storeFile($data['back_id_image'], 'Driver', 'img'),

                'city'             => $data['city'] ?? null,
                'v_location'       => $data['v_location'] ?? null,
                'h_location'       => $data['h_location'] ?? null,

                'vehicle_type_id'  => $data['vehicle_type_id'],
            ]);

            return $driver->load('vehicleType');
        // } catch (\Throwable $th) {
        //     Log::error($th);

        //     if ($th instanceof HttpResponseException) {
        //         throw $th;
        //     }

        //     $this->throwExceptionJson();
        // }
    }

    /**
     * Update an existing driver
     */
    public function updateDriver(array $data, Driver $driver)
    {
        try {
            $driver->update(array_filter([
                'driver_name'      => $data['driver_name'] ?? null,
                'phone'            => $data['phone'] ?? null,
                'password'         => isset($data['password']) ? bcrypt($data['password']) : null,

                'driver_image'     => FileStorage::fileExists(
                    $data['driver_image'] ?? null,
                    $driver->driver_image,
                    'Driver',
                    'img'
                ),

                'front_id_image'   => FileStorage::fileExists(
                    $data['front_id_image'] ?? null,
                    $driver->front_id_image,
                    'Driver',
                    'img'
                ),

                'back_id_image'    => FileStorage::fileExists(
                    $data['back_id_image'] ?? null,
                    $driver->back_id_image,
                    'Driver',
                    'img'
                ),

                'city'             => array_key_exists('city', $data) ? $data['city'] : null,
                'v_location'       => $data['v_location'] ?? null,
                'h_location'       => $data['h_location'] ?? null,

                'vehicle_type_id'  => $data['vehicle_type_id'] ?? null,
            ]));

            return $driver->load('vehicleType');
        } catch (\Throwable $th) {
            Log::error($th);

            if ($th instanceof HttpResponseException) {
                throw $th;
            }

            $this->throwExceptionJson();
        }
    }

    /**
     * Update Driver Profile (Authenticated Driver)
     */
    public function updateDriverProfile(array $data)
    {
        try {
            $driver = Auth::guard('driver')->user();

            if (!$driver instanceof Driver) {
                throw new \Exception('غير مصرح لك بالقيام بهذا الإجراء.');
            }

            if (!empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            $driver->update(array_filter([
                'driver_name'      => $data['driver_name'] ?? null,
                'phone'            => $data['phone'] ?? null,
                'password'         => $data['password'] ?? null,

                'driver_image'     => FileStorage::fileExists(
                    $data['driver_image'] ?? null,
                    $driver->driver_image,
                    'Driver',
                    'img'
                ),

                'front_id_image'   => FileStorage::fileExists(
                    $data['front_id_image'] ?? null,
                    $driver->front_id_image,
                    'Driver',
                    'img'
                ),

                'back_id_image'    => FileStorage::fileExists(
                    $data['back_id_image'] ?? null,
                    $driver->back_id_image,
                    'Driver',
                    'img'
                ),

                'city'             => array_key_exists('city', $data) ? $data['city'] : null,
                'v_location'       => $data['v_location'] ?? null,
                'h_location'       => $data['h_location'] ?? null,

                'vehicle_type_id'  => $data['vehicle_type_id'] ?? null,
            ]));

            return $driver->load('vehicleType');
        } catch (\Throwable $e) {
            $this->throwExceptionJson(
                'حدث خطأ أثناء تحديث بياناتك',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Delete driver and images
     */
    public function deleteDriver(Driver $driver)
    {
        try {
            FileStorage::deleteFile($driver->driver_image);
            FileStorage::deleteFile($driver->front_id_image);
            FileStorage::deleteFile($driver->back_id_image);

            $driver->delete();
            return true;
        } catch (\Throwable $th) {
            Log::error($th);

            if ($th instanceof HttpResponseException) {
                throw $th;
            }

            $this->throwExceptionJson();
        }
    }
}
