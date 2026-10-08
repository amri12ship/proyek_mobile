<?php

namespace App\Support;

class Geo
{
    public const EARTH_RADIUS_METERS = 6371000.0;

    /**
     * Great-circle distance between two coordinates in meters.
     */
    public static function distance(
        float $latitudeA,
        float $longitudeA,
        float $latitudeB,
        float $longitudeB,
    ): float {
        $deltaLatitude = deg2rad($latitudeB - $latitudeA);
        $deltaLongitude = deg2rad($longitudeB - $longitudeA);

        $a = sin($deltaLatitude / 2) ** 2
            + cos(deg2rad($latitudeA)) * cos(deg2rad($latitudeB)) * sin($deltaLongitude / 2) ** 2;

        return round(self::EARTH_RADIUS_METERS * 2 * asin(min(1.0, sqrt($a))), 2);
    }

    /**
     * Whether a coordinate pair is inside the allowed radius.
     */
    public static function withinRadius(
        float $latitude,
        float $longitude,
        float $centerLatitude,
        float $centerLongitude,
        float $radiusMeters,
    ): bool {
        return self::distance($latitude, $longitude, $centerLatitude, $centerLongitude) <= $radiusMeters;
    }

    /**
     * Offset a coordinate by a distance/bearing, used for building test fixtures.
     */
    public static function offset(
        float $latitude,
        float $longitude,
        float $northMeters,
        float $eastMeters,
    ): array {
        $latitudeOffset = $northMeters / 111320.0;
        $longitudeOffset = $eastMeters / (111320.0 * cos(deg2rad($latitude)));

        return [
            round($latitude + $latitudeOffset, 7),
            round($longitude + $longitudeOffset, 7),
        ];
    }
}
