<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    public function test_workforce_kpis_endpoint_returns_metrics(): void
    {
        $response = $this->getJson('/api/reports/workforce-kpis');

        $response->assertOk()->assertJsonStructure(['headcount', 'attrition_rate', 'time_to_hire_days']);
    }
}
