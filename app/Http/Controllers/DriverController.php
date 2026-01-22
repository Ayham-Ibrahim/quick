<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Services\DriverService;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\DriverResource;
use App\Http\Requests\DriverRequests\StoreDriverRequest;
use App\Http\Requests\DriverRequests\UpdateDriverRequest;
use App\Http\Requests\DriverRequests\UpdateDriverProfileRequest;

class DriverController extends Controller
{
    protected $driverService;

    public function __construct(DriverService $driverService)
    {
        $this->driverService = $driverService;
    }

    public function index()
    {
        return $this->success($this->driverService->paginate());
    }


    public function store(StoreDriverRequest $request)
    {
        $driver = $this->driverService->storeDriver($request->validated());
        return $this->success(new DriverResource($driver), 'تم انشاء السائق بنجاح', 201);
    }

    public function show($id)
    {
        $driver = $this->driverService->find($id);
        return $this->success(new DriverResource($driver));
    }

    public function update(UpdateDriverRequest $request, Driver $driver)
    {
        $driver = $this->driverService->updateDriver($request->validated(), $driver);
        return $this->success(new DriverResource($driver), 'تم تحديث بيانات السائق بنجاح');
    }
    /**
     * Update driver profile data.
     */
    public function updateDriverProfile(UpdateDriverProfileRequest $request)
    {
        $driver = $this->driverService->updateDriverProfile($request->validated());
        return $this->success(new DriverResource($driver), 'تم تحديث بياناتك بنجاح');
    }

    /**
     * Show driver profile
     * @return \Illuminate\Http\JsonResponse
     */
    public function profile()
    {
        /** @var \App\Models\Driver $driver */
        $driver = Auth::guard('driver')->user();

        return $this->success(
            new DriverResource($driver->load(['vehicleType','wallet'])),
            'بيانات السائق'
        );
    }

    public function destroy(Driver $driver)
    {
        $this->driverService->deleteDriver($driver);
        return $this->success([], 'تم حذف السائق بنجاح');
    }

    // toggle driver active status
    public function toggleActiveStatus()
    {
        $driver = Auth::guard('driver')->user();
        $driver->is_active = ! $driver->is_active;
        $driver->save();    
        return $this->success(new DriverResource($driver), 'تم تحديث حالة السائق بنجاح');
    }

    /**
     * update driver current location
     * 
     * The location should be sent approximately every 30 seconds from the app
     */
    public function updateLocation(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ], [
            'lat.required' => 'خط العرض مطلوب',
            'lat.numeric' => 'خط العرض يجب أن يكون رقماً',
            'lat.between' => 'خط العرض يجب أن يكون بين -90 و 90',
            'lng.required' => 'خط الطول مطلوب',
            'lng.numeric' => 'خط الطول يجب أن يكون رقماً',
            'lng.between' => 'خط الطول يجب أن يكون بين -180 و 180',
        ]);

        /** @var \App\Models\Driver $driver */
        $driver = Auth::guard('driver')->user();
        
        $driver->update([
            'current_lat' => $validated['lat'],
            'current_lng' => $validated['lng'],
            'last_location_update' => now(),
            'last_activity_at' => now(),
        ]);

        return $this->success([
            'lat' => (float) $driver->current_lat,
            'lng' => (float) $driver->current_lng,
            'updated_at' => $driver->last_location_update->toIso8601String(),
        ], 'تم تحديث الموقع بنجاح');
    }

    /**
     * Change connection status (online/offline)
     */
    public function toggleOnlineStatus()
    {
        /** @var \App\Models\Driver $driver */
        $driver = Auth::guard('driver')->user();
        
        $driver->is_online = !$driver->is_online;
        
        if ($driver->is_online) {
            $driver->last_activity_at = now();
        }
        
        $driver->save();

        return $this->success([
            'is_online' => $driver->is_online,
            'message' => $driver->is_online ? 'أنت الآن متصل' : 'أنت الآن غير متصل',
        ], $driver->is_online ? 'تم تفعيل الاتصال' : 'تم إيقاف الاتصال');
    }

    /**
     * Driver activity recording (heartbeat)
     * 
     * This request must be sent every minute to maintain the active state
     */
    public function heartbeat()
    {
        /** @var \App\Models\Driver $driver */
        $driver = Auth::guard('driver')->user();
        
        $driver->update([
            'last_activity_at' => now(),
        ]);

        return $this->success([
            'is_active' => $driver->is_active,
            'is_online' => $driver->is_online,
            'last_activity_at' => $driver->last_activity_at->toIso8601String(),
        ]);
    }
}
