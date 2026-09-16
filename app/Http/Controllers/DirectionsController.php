<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DirectionsController extends Controller
{
    public function getDirections(Request $request)
    {
        $request->validate([
            'origin' => 'required|string',
            'destination' => 'required|string',
            'mode' => 'sometimes|string|in:driving,walking,bicycling,transit',
        ]);

        $apiKey = config('services.google_maps.api_key');
        
        if (!$apiKey) {
            return response()->json([
                'error' => 'Google Maps API key not configured'
            ], 500);
        }

        $params = [
            'origin' => $request->origin,
            'destination' => $request->destination,
            'mode' => $request->input('mode', 'driving'),
            'alternatives' => 'true',
            'key' => $apiKey,
        ];

        if ($request->has('waypoints')) {
            $params['waypoints'] = $request->waypoints;
        }

        try {
            $response = Http::get('https://maps.googleapis.com/maps/api/directions/json', $params);
            
            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch directions',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
