<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DiskUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiskUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_indikator_disk_memakai_kuota_bukan_partisi(): void
    {
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)->get('/system')
            ->assertOk()
            ->assertSee('Pemakaian Kuota Hosting')
            ->assertSee('2.048,00 MB'); // kuota default 2048 MB
    }

    public function test_disk_usage_service_menghitung_pemakaian_aplikasi(): void
    {
        $report = app(DiskUsageService::class)->compute();

        $this->assertTrue($report['available']);
        $this->assertSame(2048 * 1048576, $report['quota']);
        $this->assertGreaterThan(0, $report['used']);
        $this->assertGreaterThan(0, $report['code']);
        $this->assertGreaterThanOrEqual(0, $report['percent']);
        $this->assertLessThan(100, $report['percent']); // bukan 86% (itu disk laptop)
    }
}
