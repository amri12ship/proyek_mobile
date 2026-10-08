<?php

namespace Tests\Unit;

use App\Support\Geo;
use PHPUnit\Framework\TestCase;

class GeoTest extends TestCase
{
    public function test_distance_between_identical_points_is_zero(): void
    {
        $this->assertSame(0.0, Geo::distance(-6.1751111, 106.8271667, -6.1751111, 106.8271667));
    }

    public function test_distance_known_pair_of_coordinates(): void
    {
        // Monas to the Jakarta Cathedral west entrance is roughly 270 m.
        $distance = Geo::distance(-6.175392, 106.827153, -6.175200, 106.824700);

        $this->assertGreaterThan(250, $distance);
        $this->assertLessThan(300, $distance);
    }

    public function test_within_radius_boundary(): void
    {
        [$latitude, $longitude] = Geo::offset(-6.1751111, 106.8271667, 0, 100);

        $this->assertTrue(Geo::withinRadius($latitude, $longitude, -6.1751111, 106.8271667, 200));
        $this->assertFalse(Geo::withinRadius($latitude, $longitude, -6.1751111, 106.8271667, 50));
    }

    public function test_offset_moves_the_coordinate_in_the_expected_direction(): void
    {
        [$latitude, $longitude] = Geo::offset(-6.1751111, 106.8271667, 1000, 0);

        $this->assertGreaterThan(-6.1751111, $latitude);
        $this->assertEqualsWithDelta(106.8271667, $longitude, 0.0001);
        $this->assertEqualsWithDelta(1000, Geo::distance(-6.1751111, 106.8271667, $latitude, $longitude), 2);
    }
}
