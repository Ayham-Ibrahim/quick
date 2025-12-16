<?php

namespace App\Http\Controllers;

use App\Http\Requests\VehicleTypeRequest;
use App\Models\VehicleType;
use Illuminate\Http\Request;

class VehicleTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $types = VehicleType::paginate(10);

        return $this->paginate($types, 'Vehicle types retrieved successfully');
    }

    /**
     * Store a newly created resource.
     */
    public function store(VehicleTypeRequest $request)
    {
        try {
            $validated = $request->validated();

            $vehicleType = VehicleType::create($validated);

            return $this->success($vehicleType, 'Vehicle type created successfully', 201);

        } catch (\Exception $e) {
            return $this->error('Failed to create vehicle type', 500, $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $vehicleType = VehicleType::find($id);

        if (! $vehicleType) {
            return $this->error('Vehicle type not found', 404);
        }

        return $this->success($vehicleType, 'Vehicle type retrieved successfully');
    }

    /**
     * Remove the specified resource.
     */
    public function destroy(string $id)
    {
        $vehicleType = VehicleType::find($id);

        if (! $vehicleType) {
            return $this->error('Vehicle type not found', 404);
        }

        $vehicleType->delete();

        return $this->success(null, 'Vehicle type deleted successfully');
    }
}
