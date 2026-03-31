<?php

namespace Tests\Unit;

use App\Support\DeliveryMetrics;
use PHPUnit\Framework\TestCase;

class DeliveryMetricsTest extends TestCase
{
    public function test_it_calculates_distance_between_two_points(): void
    {
        $distance = DeliveryMetrics::distanceKm(19.0760, 72.8777, 19.1136, 72.8697);

        $this->assertNotNull($distance);
        $this->assertGreaterThan(4, $distance);
        $this->assertLessThan(5, $distance);
    }

    public function test_eta_is_shorter_once_the_agent_is_on_the_way(): void
    {
        $acceptedEta = DeliveryMetrics::estimateMinutes(5, 'accepted');
        $enRouteEta = DeliveryMetrics::estimateMinutes(5, 'on_the_way');

        $this->assertNotNull($acceptedEta);
        $this->assertNotNull($enRouteEta);
        $this->assertGreaterThan($enRouteEta, $acceptedEta);
    }
}
