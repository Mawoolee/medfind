<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class PharmacyBackNavigationTest extends TestCase
{
    public function test_every_standalone_pharmacy_page_uses_the_shared_back_component_with_its_workflow_parent(): void
    {
        $expectedDestinations = [
            'analysis.blade.php' => 'pharmacy.dashboard',
            'audit_log.blade.php' => 'pharmacy.dashboard',
            'controlled_substance_log.blade.php' => 'pharmacy.controlled-substances.index',
            'controlled_substances_index.blade.php' => 'pharmacy.dashboard',
            'cycle_counts_create.blade.php' => 'pharmacy.cycle-counts.index',
            'cycle_counts_index.blade.php' => 'pharmacy.dashboard',
            'cycle_counts_show.blade.php' => 'pharmacy.cycle-counts.index',
            'inventory.blade.php' => 'pharmacy.dashboard',
            'inventory_batches.blade.php' => 'pharmacy.inventory',
            'inventory_create.blade.php' => 'pharmacy.inventory',
            'inventory_edit.blade.php' => 'pharmacy.inventory',
            'location_edit.blade.php' => 'pharmacy.profile.edit',
            'messages.blade.php' => 'pharmacy.dashboard',
            'profile_edit.blade.php' => 'pharmacy.dashboard',
            'receiving_create.blade.php' => 'pharmacy.inventory',
            'requirements.blade.php' => 'pharmacy.profile.edit',
            'returns_create.blade.php' => 'pharmacy.returns.index',
            'returns_index.blade.php' => 'pharmacy.dashboard',
            'sales/create.blade.php' => 'pharmacy.dashboard',
            'suppliers_create.blade.php' => 'pharmacy.suppliers.index',
            'suppliers_edit.blade.php' => 'pharmacy.suppliers.index',
            'suppliers_index.blade.php' => 'pharmacy.dashboard',
        ];

        $pharmacyViewsPath = resource_path('views/pharmacy');
        $actualPages = collect(File::allFiles($pharmacyViewsPath))
            ->filter(static fn ($file): bool => str_ends_with($file->getFilename(), '.blade.php'))
            ->map(static fn ($file): string => str_replace('\\', '/', $file->getRelativePathname()))
            ->sort()
            ->values()
            ->all();
        $expectedPages = array_keys($expectedDestinations);
        $expectedPages[] = 'dashboard.blade.php';
        sort($expectedPages);

        self::assertSame($expectedPages, $actualPages, 'Update the Back-navigation inventory when a standalone pharmacy page is added or removed.');

        foreach ($expectedDestinations as $page => $routeName) {
            $source = File::get($pharmacyViewsPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $page));

            self::assertSame(1, substr_count($source, '<x-back-button'), "{$page} must render exactly one shared Back component.");
            self::assertStringContainsString(":href=\"route('{$routeName}')\"", $source, "{$page} must return to {$routeName}.");
        }

        $dashboard = File::get($pharmacyViewsPath.DIRECTORY_SEPARATOR.'dashboard.blade.php');
        self::assertStringNotContainsString('<x-back-button', $dashboard, 'The Pharmacy Dashboard is the navigation root and must not render Back.');
    }

    public function test_messages_back_button_is_dark_at_rest_and_white_only_on_hover(): void
    {
        $source = File::get(resource_path('views/pharmacy/messages.blade.php'));

        self::assertStringContainsString(
            'class="shrink-0 bg-[#191970] hover:!bg-white"',
            $source,
            'The Messages Back button must match its dark header at rest and override the shared hover fill with white.',
        );
        self::assertSame(
            1,
            preg_match('/<x-back-button\\b[^>]*\\bclass="([^"]*)"/', $source, $matches),
            'The Messages page must expose the Back component class contract.',
        );
        self::assertNotContains(
            'bg-white',
            preg_split('/\\s+/', trim($matches[1])),
            'The Messages Back button must not have a white resting background.',
        );
    }

    public function test_shared_back_component_renders_the_visual_accessibility_and_attribute_contract(): void
    {
        $html = Blade::render('<x-back-button href="/deterministic-parent" label="Back to Inventory" data-testid="back-link" />');

        self::assertStringContainsString('href="/deterministic-parent"', $html);
        self::assertStringContainsString('aria-label="Back to Inventory"', $html);
        self::assertStringContainsString('data-testid="back-link"', $html);
        self::assertStringContainsString('aria-hidden="true"', $html);
        self::assertStringContainsString('text-[#9400D3]', $html);
        self::assertStringContainsString('hover:text-[#7a00b0]', $html);
        self::assertStringContainsString('gap-2', $html);
        self::assertStringContainsString('min-h-11', $html);
        self::assertStringContainsString('rounded-lg', $html);
        self::assertStringContainsString('focus-visible:ring-[#9400D3]', $html);
        self::assertMatchesRegularExpression('/<span>\s*Back\s*<\/span>/', $html);
        self::assertStringNotContainsString('history.back', $html);
    }
}
