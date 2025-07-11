<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    // Calculate distance between two lat/lng points using Haversine formula (meters)
    private function haversineDistance(
        float $lat1, float $lng1,
        float $lat2, float $lng2
    ): float {
        $earthRadius = 6371000; // meters

        $latFrom = deg2rad($lat1);
        $lngFrom = deg2rad($lng1);
        $latTo = deg2rad($lat2);
        $lngTo = deg2rad($lng2);

        $latDelta = $latTo - $latFrom;
        $lngDelta = $lngTo - $lngFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lngDelta / 2), 2)));

        return $angle * $earthRadius;
    }

    // Find campus location matching given latitude and longitude.
    // Returns location name or 'Unknown Location' if none matches.
    private function matchLocation(float $lat, float $lng): string
    {
        $locations = Location::all();

        foreach ($locations as $location) {
            $distance = $this->haversineDistance(
                $lat,
                $lng,
                $location->latitude,
                $location->longitude
            );

            if ($distance <= $location->radius_meters) {
                return $location->name;
            }
        }

        return 'Unknown Location';
    }

    // Store new report
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'description' => 'required|string',
            'image' => 'nullable|image|max:5120', // max 5MB
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('public/reports'); 
            $imageUrl = Storage::url($path); 
        }

        $locationName = $this->matchLocation($request->latitude, $request->longitude);

        $report = Report::create([
            'user_id' => $request->user()->id,
            'description' => $request->description,
            'image_url' => $imageUrl, 
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'location_name' => $locationName,
            'status' => 'Pending',
            'timestamp' => now(),
            'address' => null,
        ]);

        return response()->json(['message' => 'Report created', 'report' => $report], 201);
    }


    // List all reports for the authenticated user
    public function index(Request $request)
    {
        $reports = Report::where('user_id', $request->user()->id)
            ->orderBy('timestamp', 'desc')
            ->get()
            ->map(function ($report) {
                return [
                    'id' => $report->id,
                    'description' => $report->description,
                    'status' => $report->status,
                    'image_url' => $report->image_url ? asset($report->image_url) : null, // Convert to full URL
                    'location_name' => $report->location_name ?? 'Unknown',
                    'timestamp' => $report->timestamp ? $report->timestamp->toIso8601String() : now()->toIso8601String(),
                ];
            });

        return response()->json($reports);
    }

}
