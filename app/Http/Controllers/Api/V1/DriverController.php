<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDriverRequest;
use App\Http\Requests\UpdateDriverRequest;
use App\Models\Driver;

class DriverController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Driver::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDriverRequest $request)
    {
        $data = $request->validated();

        // if ($request->hasFile('license_photo')) {
        //     $data['license_photo'] = $request->file('license_photo')->store('license_photos', 'public');
        // }

        // if ($request->hasFile('car_photo')) {
        //     $data['car_photo'] = $request->file('car_photo')->store('car_photos', 'public');
        // }

        if ($request->hasFile('license_photo')) {
            $filename = uniqid() . '.' . $request->file('license_photo')->getClientOriginalExtension();
            $request->file('license_photo')->move(public_path('img/license_photos'), $filename);
            $data['license_photo'] = 'img/license_photos/' . $filename;
        }

        if ($request->hasFile('car_photo')) {
            $filename = uniqid() . '.' . $request->file('car_photo')->getClientOriginalExtension();
            $request->file('car_photo')->move(public_path('img/car_photos'), $filename);
            $data['car_photo'] = 'img/car_photos/' . $filename;
        }

        

        $driver = Driver::create($data);

        return response()->json($driver, 201);
    }


    /**
     * Display the specified resource.
     */
    public function show(Driver $driver)
    {
        return $driver;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDriverRequest $request, Driver $driver)
    {
        $driver->update($request->all());
        return $driver;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Driver $driver)
    {
        $driver->delete();
        return response()->json([
            'message' => 'Driver was removed',
        ]);
    }
}
