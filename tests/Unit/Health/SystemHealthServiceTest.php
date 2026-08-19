<?php

namespace Tests\Unit\Health;

use App\Services\Health\SystemHealthService;
use Tests\TestCase;

class SystemHealthServiceTest extends TestCase
{
    public function test_get_health_report_returns_expected_structure(): void
    {
        $service = app(SystemHealthService::class);
        $report = $service->getHealthReport();

        $this->assertArrayHasKey('is_worker_running', $report);
        $this->assertArrayHasKey('worker_status_text', $report);
        $this->assertArrayHasKey('db_version', $report);
        $this->assertArrayHasKey('db_latency', $report);
        $this->assertArrayHasKey('martin_online', $report);
        $this->assertArrayHasKey('php_memory', $report);
        $this->assertArrayHasKey('bottlenecks', $report);
        $this->assertIsArray($report['bottlenecks']);
    }
}
