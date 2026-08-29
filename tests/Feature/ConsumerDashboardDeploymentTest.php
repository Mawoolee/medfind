<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite;
use Tests\TestCase;

class ConsumerDashboardDeploymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_uses_forwarded_https_and_bundled_map_assets(): void
    {
        app(Vite::class)
            ->useHotFile(storage_path('framework/testing-vite.hot'));

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.10'])
            ->withHeaders([
                'X-Forwarded-Host' => 'medfind.example',
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Port' => '443',
            ])
            ->get('/');

        $response->assertOk();

        $html = $response->getContent();

        $this->assertStringContainsString('https://medfind.example/build/assets/', $html);
        $this->assertStringContainsString('https://medfind.example/js/medfind.js', $html);
        $this->assertStringContainsString('https://medfind.example/images/Final Logo MedFind.png', $html);
        $this->assertStringContainsString('class="h-12 w-12 object-contain"', $html);
        $this->assertStringContainsString('id="medfindMap"', $html);
        $this->assertStringNotContainsString('unpkg.com/leaflet', $html);
        $this->assertStringNotContainsString('unpkg.com/leaflet-routing-machine', $html);
    }
}
