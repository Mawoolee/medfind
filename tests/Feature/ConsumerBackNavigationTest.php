<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Spec: .kiro/specs/responsive-back-buttons
 *
 * Consumer page-level Back links are hidden on phone viewports because the navbar Menu
 * already reaches Map, My Messages, Search Medicines, and Profile Settings. Controls that
 * are the only means of backward navigation stay visible at every width.
 *
 * These are source-contract assertions, matching the approach already used by
 * PharmacyBackNavigationTest. Requirement 1.3 demands a CSS-only solution, so the emitted
 * utilities are the observable behaviour. Actual rendered widths are browser concerns.
 */
final class ConsumerBackNavigationTest extends TestCase
{
    /**
     * Consumer_Pages in scope and the element whose visibility toggles.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function consumerPageBackLinkProvider(): array
    {
        return [
            // The link sits in a dedicated flex container that contributes mb-3 spacing,
            // so requirement 2.2 hides the container together with the link.
            'pharmacy-details' => ['consumer/pharmacy-details.blade.php', 'hidden sm:flex justify-end mb-3'],
            'profile' => ['consumer/profile.blade.php', 'hidden sm:flex items-center gap-1'],
            'search' => ['consumer/search.blade.php', 'hidden sm:inline text-[#9400D3]'],
            'messages' => ['consumer/messages.blade.php', 'hidden sm:inline text-gray-400'],
        ];
    }

    #[DataProvider('consumerPageBackLinkProvider')]
    public function test_page_back_link_is_hidden_below_the_sm_breakpoint(string $view, string $expectedClasses): void
    {
        $source = $this->viewSource($view);

        // Requirements 1.1 and 1.2: hidden below sm, restored at sm and above.
        self::assertStringContainsString(
            $expectedClasses,
            $source,
            "{$view} must hide its page-level Back control below the sm breakpoint."
        );

        // Requirement 1.4: the link stays in the DOM at every width.
        self::assertStringContainsString("route('consumer.dashboard')", $source);

        // Requirement 1.3: no user-agent detection drives the visibility change.
        self::assertStringNotContainsString('navigator.userAgent', $source);

        // Requirement 1.3: the utilities are static markup, not toggled by a script.
        self::assertStringNotContainsString("classList.add('hidden sm:", $source);
        self::assertStringNotContainsString('classList.add("hidden sm:', $source);
    }

    public function test_messages_page_link_and_panel_control_have_opposite_responsive_behaviour(): void
    {
        $source = $this->viewSource('consumer/messages.blade.php');

        // The two controls are easy to conflate. The Page_Back_Link goes to the Map and is
        // desktop-only; the Panel_Back_Control closes an open conversation and is phone-only.
        // messages.blade.php also has pre-existing matchMedia panel-swap logic, which is a
        // separate concern from this requirement's visibility change.
        self::assertSame(
            1,
            preg_match_all('/hidden sm:inline text-gray-400/', $source),
            'Exactly one desktop-only Page_Back_Link is expected in the list header.'
        );
        self::assertSame(
            1,
            preg_match_all('/class="sm:hidden text-gray-300/', $source),
            'Exactly one phone-only Panel_Back_Control is expected in the chat header.'
        );
    }

    public function test_messages_panel_back_control_stays_visible_on_phone(): void
    {
        $source = $this->viewSource('consumer/messages.blade.php');

        // Requirement 3.1: the control that leaves an open conversation is phone-only and
        // must not be swept up by the page-link hiding.
        self::assertStringContainsString('onclick="closeConversation()"', $source);
        self::assertMatchesRegularExpression(
            '/onclick="closeConversation\(\)"[^>]*class="sm:hidden/',
            $source,
            'The conversation Panel_Back_Control must remain visible at phone widths.'
        );
    }

    public function test_chat_header_back_control_stays_visible_at_every_width(): void
    {
        $source = $this->viewSource('consumer/chat.blade.php');

        // Requirement 3.2: the chat header back control is the only exit from a conversation.
        self::assertStringContainsString(
            '<a href="{{ route(\'consumer.pharmacy.details\', $pharmacy->id) }}" class="text-white hover:text-[#D9F855] transition">',
            $source,
            'The chat header back control must not be hidden at any width.'
        );
        self::assertStringNotContainsString('hidden sm:', $source);
    }

    public function test_admin_and_pharmacy_back_links_keep_their_visibility_behaviour(): void
    {
        // Requirements 6.1 and 6.2: out of scope, so no responsive hiding is introduced.
        foreach (['admin', 'pharmacy'] as $area) {
            foreach (File::allFiles(resource_path("views/{$area}")) as $file) {
                if (! str_ends_with($file->getFilename(), '.blade.php')) {
                    continue;
                }

                $source = File::get($file->getPathname());

                foreach (preg_split('/\R/', $source) ?: [] as $number => $line) {
                    if (! str_contains($line, 'x-back-button')) {
                        continue;
                    }

                    self::assertStringNotContainsString(
                        'hidden sm:',
                        $line,
                        "{$area}/{$file->getRelativePathname()} line ".($number + 1)
                            .' must not gain responsive hiding on its Back control.'
                    );
                }
            }
        }
    }

    private function viewSource(string $relativePath): string
    {
        return File::get(resource_path('views/'.$relativePath));
    }
}
