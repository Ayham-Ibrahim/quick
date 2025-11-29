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
        return $this->success(new DriverResource($driver), 'Driver created successfully', 201);
    }

    public function show($id)
    {
        $driver = $this->driverService->find($id);
        return $this->success(new DriverResource($driver));
    }

    public function update(UpdateDriverRequest $request, Driver $driver)
    {
        $driver = $this->driverService->updateDriver($request->validated(), $driver);
        return $this->success(new DriverResource($driver), 'Driver updated successfully');
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
            new DriverResource($driver->load(['vehicleType'])),
            'بيانات السائق'
        );
    }

    public function destroy(Driver $driver)
    {
        $this->driverService->deleteDriver($driver);
        return $this->success([], 'Driver deleted successfully');
    }
}
