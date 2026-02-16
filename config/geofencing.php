<?php

return [
    /**
     * Fallback radius (km) used by GeofencingService when the progressive
     * geofencing returns no eligible drivers. Increase to cover larger
     * city/region areas. Default: 20 km
     */
    'fallback_radius_km' => env('GEOFENCING_FALLBACK_RADIUS_KM', 20),
];
