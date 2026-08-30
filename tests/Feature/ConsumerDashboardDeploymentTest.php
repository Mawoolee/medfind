<?php

namespace Tests\Feature;

use DOMDocument;
use DOMElement;
use DOMXPath;
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
        $this->assertStringContainsString('id="medfindMap"', $html);
        $this->assertStringNotContainsString('unpkg.com/leaflet', $html);
        $this->assertStringNotContainsString('unpkg.com/leaflet-routing-machine', $html);

        $this->assertHeaderLogoIsServedOverForwardedHttps($html);
        $this->assertHeaderLogoCannotRenderUnbounded($html);
    }

    /**
     * The Railway deployment sits behind a TLS-terminating proxy, so the logo must
     * be emitted with the forwarded scheme/host instead of the internal origin.
     */
    private function assertHeaderLogoIsServedOverForwardedHttps(string $html): void
    {
        [$logo] = $this->headerLogoNodes($html);

        $this->assertSame(
            'https://medfind.example/images/Final Logo MedFind.png',
            $logo->getAttribute('src'),
            'Header logo must be served from the forwarded HTTPS asset URL.'
        );
    }

    /**
     * The logo source file is 2000x2000. Whatever the visual design is, the header
     * must keep it explicitly size-constrained: a fixed header-height wrapper that
     * clips overflow, bounded responsive widths, and an image sized relative to
     * that wrapper rather than to its own intrinsic dimensions.
     */
    private function assertHeaderLogoCannotRenderUnbounded(string $html): void
    {
        [$logo, $wrapper] = $this->headerLogoNodes($html);

        $wrapperClasses = $this->classList($wrapper);
        $logoClasses = $this->classList($logo);

        $this->assertContains(
            'h-16',
            $wrapperClasses,
            'Logo wrapper must be pinned to the fixed h-16 header height so the logo cannot grow the header.'
        );

        $this->assertContains(
            'overflow-hidden',
            $wrapperClasses,
            'Logo wrapper must clip overflow so the oversized source image cannot spill out of the header.'
        );

        $this->assertContains(
            'shrink-0',
            $wrapperClasses,
            'Logo wrapper must not be flex-shrunk out of its reserved width.'
        );

        $wrapperWidths = $this->utilityClasses($wrapperClasses, 'w-');

        $this->assertNotEmpty(
            $this->unprefixed($wrapperWidths),
            'Logo wrapper must declare a base width so the logo is horizontally bounded on small screens.'
        );

        $this->assertNotEmpty(
            $this->breakpointPrefixed($wrapperWidths),
            'Logo wrapper must declare at least one responsive width override.'
        );

        foreach ($wrapperWidths as $width) {
            $this->assertNotContains(
                $this->utilityValue($width),
                ['w-auto', 'w-screen', 'w-max', 'w-fit'],
                "Logo wrapper width [{$width}] is intrinsic/viewport sized and does not bound the 2000x2000 source."
            );
        }

        $this->assertContains(
            'w-full',
            $logoClasses,
            'Logo image must be sized relative to its bounded wrapper, not to its intrinsic 2000px width.'
        );

        $this->assertContains(
            'h-auto',
            $logoClasses,
            'Logo image must scale its height from the constrained width to preserve aspect ratio.'
        );

        $this->assertEmpty(
            array_intersect($logoClasses, ['w-screen', 'w-max', 'w-fit', 'h-screen', 'h-max', 'h-fit']),
            'Logo image must not opt back into viewport or intrinsic sizing.'
        );

        if (in_array('absolute', $logoClasses, true)) {
            $this->assertContains(
                'relative',
                $wrapperClasses,
                'An absolutely positioned logo requires a relatively positioned wrapper as its containing block, otherwise it escapes the header.'
            );
        }
    }

    /**
     * @return array{0: DOMElement, 1: DOMElement}
     */
    private function headerLogoNodes(string $html): array
    {
        $useInternalErrors = libxml_use_internal_errors(true);

        $document = new DOMDocument;
        $document->loadHTML($html);

        libxml_clear_errors();
        libxml_use_internal_errors($useInternalErrors);

        $matches = (new DOMXPath($document))
            ->query('//nav//img[contains(@src, "Final Logo MedFind.png")]');

        $this->assertNotFalse($matches, 'Unable to inspect the rendered header markup.');
        $this->assertSame(1, $matches->length, 'Expected exactly one header logo image in the navigation bar.');

        $logo = $matches->item(0);
        $this->assertInstanceOf(DOMElement::class, $logo);

        $wrapper = $logo->parentNode;
        $this->assertInstanceOf(DOMElement::class, $wrapper, 'Header logo must be wrapped by a size-constraining element.');

        return [$logo, $wrapper];
    }

    /**
     * @return list<string>
     */
    private function classList(DOMElement $element): array
    {
        return preg_split('/\s+/', trim($element->getAttribute('class')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Utility classes matching a prefix, including responsive variants (e.g. sm:w-32).
     *
     * @param  list<string>  $classes
     * @return list<string>
     */
    private function utilityClasses(array $classes, string $prefix): array
    {
        return array_values(array_filter(
            $classes,
            fn (string $class): bool => str_starts_with($this->utilityValue($class), $prefix)
        ));
    }

    /**
     * @param  list<string>  $classes
     * @return list<string>
     */
    private function unprefixed(array $classes): array
    {
        return array_values(array_filter($classes, fn (string $class): bool => ! str_contains($class, ':')));
    }

    /**
     * @param  list<string>  $classes
     * @return list<string>
     */
    private function breakpointPrefixed(array $classes): array
    {
        return array_values(array_filter($classes, fn (string $class): bool => str_contains($class, ':')));
    }

    /**
     * Strips any variant prefix (sm:, lg:, hover:) from a utility class.
     */
    private function utilityValue(string $class): string
    {
        $position = strrpos($class, ':');

        return $position === false ? $class : substr($class, $position + 1);
    }
}
