<?php

namespace Tests\Feature;

use App\Models\Pharmacy;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Spec: .kiro/specs/admin-pharmacy-status-filter-dropdown-fix
 *
 * Property 1 (Bug Condition / Expected Behavior) and Property 2 (Preservation) for the
 * Manage Pharmacies top Search & Filter form.
 *
 * Scope note: the design expresses Full_Width and Fixed_Non_Growing_Width as Tailwind
 * utility terms and the requirements document restates them as "verifiable presentation
 * outcomes rather than as a required markup implementation". A server-rendered PHPUnit
 * suite can verify the emitted contract (utilities, structure, indicator count, ARIA
 * state, source order) and the full functional behavior. It cannot measure computed
 * geometry, so pixel-level overlap and horizontal-overflow checks across the viewport
 * matrix remain browser-only assertions and are documented as such in the spec.
 */
final class AdminPharmacyStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Status_Options from the requirements glossary.
     *
     * @return array<string, array{0: string}>
     */
    public static function statusOptionProvider(): array
    {
        return [
            'all' => ['all'],
            'approved' => ['approved'],
            'pending' => ['pending'],
            'rejected' => ['rejected'],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Property 1: Balanced Filter Widths and Visible Status Chevron
    // Requirements 1.1, 1.2, 2.1, 2.2, 3.1-3.7, 4.1-4.4, 6.1-6.3
    // ─────────────────────────────────────────────────────────────────────────

    #[DataProvider('statusOptionProvider')]
    public function test_property_1_search_group_is_bounded_to_a_non_growing_20rem_basis(string $status): void
    {
        $searchGroup = $this->searchGroup($this->renderFilterForm($status));
        $classes = $this->classList($searchGroup);

        // Requirement 1.1: Full_Width below the Medium_Breakpoint.
        self::assertContains('w-full', $classes);

        // Requirement 1.2: Fixed_Non_Growing_Width of 20rem at md and above.
        self::assertContains('md:flex-[0_0_20rem]', $classes);
        self::assertContains('md:max-w-[20rem]', $classes);

        // The unbounded growth that caused searchGroupGrowsBeyondBound() must be gone.
        self::assertNotContains('flex-1', $classes);
    }

    #[DataProvider('statusOptionProvider')]
    public function test_property_1_status_group_is_bounded_to_a_non_growing_11rem_basis(string $status): void
    {
        $classes = $this->classList($this->statusGroup($this->renderFilterForm($status)));

        // Requirement 2.1: Full_Width below the Small_Breakpoint.
        self::assertContains('w-full', $classes);

        // Requirement 2.2: Fixed_Non_Growing_Width of 11rem at sm and above.
        self::assertContains('sm:flex-[0_0_11rem]', $classes);
        self::assertContains('sm:max-w-[11rem]', $classes);
    }

    #[DataProvider('statusOptionProvider')]
    public function test_property_1_status_select_presentation_is_deterministic(string $status): void
    {
        $document = $this->renderFilterForm($status);
        $select = $this->statusSelect($document);
        $wrapper = $select->parentNode;

        self::assertInstanceOf(DOMElement::class, $wrapper);

        // Requirement 3.1: one Relative_Wrapper holds the select and the chevron.
        $wrapperClasses = $this->classList($wrapper);
        self::assertContains('relative', $wrapperClasses);
        self::assertContains('w-full', $wrapperClasses);
        self::assertContains('min-w-[11rem]', $wrapperClasses);

        $selectClasses = $this->classList($select);

        // Requirement 3.2: suppress Generated_Indicator_Imagery (the @tailwindcss/forms
        // plugin paints its own arrow as a select background image) and background imagery.
        self::assertContains('appearance-none', $selectClasses);
        self::assertContains('bg-none', $selectClasses);
        self::assertContains('w-full', $selectClasses);

        // Requirement 3.3: reserve 0.75rem left and 2.5rem right padding.
        self::assertContains('pl-3', $selectClasses);
        self::assertContains('pr-10', $selectClasses);
        self::assertNotContains(
            'px-3',
            $selectClasses,
            'Symmetric px-3 padding cannot coexist with the reserved 2.5rem indicator region.'
        );
    }

    #[DataProvider('statusOptionProvider')]
    public function test_property_1_exactly_one_pointer_transparent_far_right_chevron_is_rendered(string $status): void
    {
        $document = $this->renderFilterForm($status);
        $select = $this->statusSelect($document);
        $wrapper = $select->parentNode;

        self::assertInstanceOf(DOMElement::class, $wrapper);

        $chevrons = (new DOMXPath($document))->query('.//*[local-name()="svg"]', $wrapper);
        self::assertInstanceOf(DOMNodeList::class, $chevrons);

        // Requirement 3.4: exactly one SVG Explicit_Chevron.
        self::assertSame(
            1,
            $chevrons->length,
            'The Top_Status_Filter must display exactly one SVG Explicit_Chevron.'
        );

        $chevron = $chevrons->item(0);
        self::assertInstanceOf(DOMElement::class, $chevron);

        $chevronClasses = $this->classList($chevron);

        // Requirement 3.4: Absolute_Positioning at Far_Right_Position and Vertical_Center_Position.
        self::assertContains('absolute', $chevronClasses);
        self::assertContains('right-3', $chevronClasses);
        self::assertContains('top-1/2', $chevronClasses);
        self::assertContains('-translate-y-1/2', $chevronClasses);
        self::assertContains('h-4', $chevronClasses);
        self::assertContains('w-4', $chevronClasses);

        // Requirement 3.5: pointer input passes through to the Native_Status_Select.
        self::assertContains('pointer-events-none', $chevronClasses);

        // Requirement 3.6: excluded from keyboard focus and the accessibility tree.
        self::assertSame('true', $chevron->getAttribute('aria-hidden'));
        self::assertFalse($chevron->hasAttribute('tabindex'));
        self::assertFalse($chevron->hasAttribute('role'));

        // The select stays the only focusable control inside the wrapper.
        $focusable = (new DOMXPath($document))
            ->query('.//select|.//input|.//button|.//a|.//*[@tabindex]', $wrapper);
        self::assertInstanceOf(DOMNodeList::class, $focusable);
        self::assertSame(1, $focusable->length);
    }

    #[DataProvider('statusOptionProvider')]
    public function test_property_1_actions_stay_visible_and_unwrapped(string $status): void
    {
        $document = $this->renderFilterForm($status);

        // Requirements 4.1 and 4.2: zero grow, zero shrink, unwrapped labels.
        foreach (['submit button' => $this->searchAction($document), 'reset link' => $this->resetAction($document)] as $label => $action) {
            $classes = $this->classList($action);
            self::assertContains('flex-none', $classes, "The {$label} must not grow or shrink.");
            self::assertContains('whitespace-nowrap', $classes, "The {$label} label must not wrap.");
        }

        // Requirements 4.3 and 4.4: Control_Alignment on every flex line.
        $formClasses = $this->classList($this->filterForm($document));
        self::assertContains('flex', $formClasses);
        self::assertContains('flex-wrap', $formClasses);
        self::assertContains('gap-3', $formClasses);
        self::assertContains('items-end', $formClasses);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Property 2: Preservation
    // Requirements 5.1-5.6, 6.1-6.5
    // ─────────────────────────────────────────────────────────────────────────

    public function test_property_2_preserves_native_get_filtering_for_search_and_status_combinations(): void
    {
        $admin = $this->makeAdmin();
        $approved = Pharmacy::factory()->create(['pharmacy_name' => 'Northwind Approved Pharmacy', 'status' => 'approved']);
        $pending = Pharmacy::factory()->create(['pharmacy_name' => 'Northwind Pending Pharmacy', 'status' => 'pending']);
        $rejected = Pharmacy::factory()->create(['pharmacy_name' => 'Southgate Rejected Pharmacy', 'status' => 'rejected']);

        // Requirement 5.1: GET submission using the existing search and status names.
        $form = $this->filterForm($this->renderFilterForm('all'));
        self::assertSame('get', strtolower($form->getAttribute('method')));
        self::assertSame(route('admin.pharmacies'), $form->getAttribute('action'));

        // Status alone.
        $this->actingAs($admin)->get(route('admin.pharmacies', ['status' => 'pending']))
            ->assertOk()
            ->assertSee($pending->pharmacy_name)
            ->assertDontSee($approved->pharmacy_name)
            ->assertDontSee($rejected->pharmacy_name);

        // Search alone.
        $this->actingAs($admin)->get(route('admin.pharmacies', ['search' => 'Northwind']))
            ->assertOk()
            ->assertSee($approved->pharmacy_name)
            ->assertSee($pending->pharmacy_name)
            ->assertDontSee($rejected->pharmacy_name);

        // Requirement 5.6: both criteria combined.
        $this->actingAs($admin)->get(route('admin.pharmacies', ['search' => 'Northwind', 'status' => 'approved']))
            ->assertOk()
            ->assertSee($approved->pharmacy_name)
            ->assertDontSee($pending->pharmacy_name)
            ->assertDontSee($rejected->pharmacy_name);

        // status=all is not treated as a status criterion.
        $this->actingAs($admin)->get(route('admin.pharmacies', ['status' => 'all']))
            ->assertOk()
            ->assertSee($approved->pharmacy_name)
            ->assertSee($pending->pharmacy_name)
            ->assertSee($rejected->pharmacy_name);
    }

    public function test_property_2_preserves_status_options_labels_and_selected_value_restoration(): void
    {
        // Requirement 5.2: existing option values and displayed labels.
        $select = $this->statusSelect($this->renderFilterForm('all'));
        $options = [];
        foreach ($select->getElementsByTagName('option') as $option) {
            $options[$option->getAttribute('value')] = trim($option->textContent);
        }

        self::assertSame([
            'all' => 'All Statuses',
            'approved' => 'Approved',
            'pending' => 'Pending',
            'rejected' => 'Rejected',
        ], $options);

        self::assertSame('status', $select->getAttribute('name'));

        // Requirement 5.3: the option matching the status query value is selected.
        foreach (['approved', 'pending', 'rejected'] as $status) {
            $selected = $this->selectedStatusValues($this->renderFilterForm($status));
            self::assertSame([$status], $selected, "Rendering with status={$status} must restore that option.");
        }
    }

    public function test_property_2_preserves_reset_destination_and_source_order(): void
    {
        $document = $this->renderFilterForm('pending');

        // Requirement 5.5: Reset navigates to the unfiltered route.
        self::assertSame(route('admin.pharmacies'), $this->resetAction($document)->getAttribute('href'));

        // Requirement 6.2: source and keyboard order is Search, Status, Search button, Reset.
        $ordered = (new DOMXPath($document))->query(
            '//form[.//input[@name="search"]]//input[@name="search"]'
            .'|//form[.//input[@name="search"]]//select[@name="status"]'
            .'|//form[.//input[@name="search"]]//button[@type="submit"]'
            .'|//form[.//input[@name="search"]]//a'
        );

        self::assertInstanceOf(DOMNodeList::class, $ordered);

        $sequence = [];
        foreach ($ordered as $node) {
            self::assertInstanceOf(DOMElement::class, $node);
            $sequence[] = $node->nodeName;
        }

        self::assertSame(['input', 'select', 'button', 'a'], $sequence);
    }

    public function test_property_2_preserves_activity_log_controls(): void
    {
        // Requirement 6.4: Activity_Log_Controls are untouched by this fix.
        $document = $this->renderAdminPage(route('admin.activity'));
        $xpath = new DOMXPath($document);

        foreach (['action' => 'All Actions', 'entity' => 'All Entities'] as $name => $defaultLabel) {
            $selects = $xpath->query("//select[@name='{$name}']");
            self::assertInstanceOf(DOMNodeList::class, $selects);
            self::assertSame(1, $selects->length);

            $select = $selects->item(0);
            self::assertInstanceOf(DOMElement::class, $select);

            $classes = $this->classList($select);
            self::assertContains('w-full', $classes);
            self::assertContains('sm:w-auto', $classes);
            self::assertContains('px-3', $classes, 'Activity Log selects keep their symmetric padding.');
            self::assertNotContains('appearance-none', $classes, 'Activity Log selects keep native indicator imagery.');
            self::assertNotContains('pr-10', $classes);

            self::assertSame($defaultLabel, trim($select->getElementsByTagName('option')->item(0)->textContent));

            // No chevron is injected into the Activity Log controls.
            $wrapper = $select->parentNode;
            self::assertInstanceOf(DOMElement::class, $wrapper);
            $svgs = $xpath->query('.//*[local-name()="svg"]', $wrapper);
            self::assertInstanceOf(DOMNodeList::class, $svgs);
            self::assertSame(0, $svgs->length);
        }
    }

    public function test_property_2_preserves_row_level_status_controls(): void
    {
        // Requirement 6.5: row-level status controls operate independently of the top filter.
        $admin = $this->makeAdmin();
        $pharmacy = Pharmacy::factory()->create(['status' => 'pending']);

        $document = $this->renderAdminPage(route('admin.pharmacies'));
        $rowStatusInputs = (new DOMXPath($document))->query(
            '//table//input[@name="status"]'
        );

        self::assertInstanceOf(DOMNodeList::class, $rowStatusInputs);
        self::assertGreaterThan(
            0,
            $rowStatusInputs->length,
            'Row-level status controls must remain present in the table.'
        );

        // They are hidden inputs inside their own forms, not the top filter select.
        foreach ($rowStatusInputs as $input) {
            self::assertInstanceOf(DOMElement::class, $input);
            self::assertSame('hidden', $input->getAttribute('type'));
        }

        // The row-level update still works end to end.
        $this->actingAs($admin)
            ->put(route('admin.pharmacy.update', $pharmacy), [
                'pharmacy_name' => $pharmacy->pharmacy_name,
                'pharmacyAddress' => $pharmacy->pharmacyAddress,
                'contactNumber' => $pharmacy->contactNumber,
                'user_id' => $pharmacy->user_id,
                'status' => 'approved',
            ])
            ->assertRedirect();

        self::assertSame('approved', $pharmacy->fresh()->status);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function renderFilterForm(string $status): DOMDocument
    {
        return $this->renderAdminPage(route('admin.pharmacies', ['status' => $status]));
    }

    private function renderAdminPage(string $url): DOMDocument
    {
        $response = $this->actingAs($this->makeAdmin())->get($url);
        $response->assertOk();

        $useInternalErrors = libxml_use_internal_errors(true);

        $document = new DOMDocument;
        $document->loadHTML((string) $response->getContent());

        libxml_clear_errors();
        libxml_use_internal_errors($useInternalErrors);

        return $document;
    }

    private function filterForm(DOMDocument $document): DOMElement
    {
        return $this->single($document, '//form[.//input[@name="search"]]', 'top Search & Filter form');
    }

    private function searchGroup(DOMDocument $document): DOMElement
    {
        return $this->single(
            $document,
            '//form[.//input[@name="search"]]//input[@name="search"]/ancestor::div[1]',
            'Search_Group'
        );
    }

    private function statusGroup(DOMDocument $document): DOMElement
    {
        return $this->single(
            $document,
            '//form[.//input[@name="search"]]//select[@name="status"]/ancestor::div[2]',
            'Top_Status_Filter group'
        );
    }

    private function statusSelect(DOMDocument $document): DOMElement
    {
        return $this->single(
            $document,
            '//form[.//input[@name="search"]]//select[@name="status"]',
            'Native_Status_Select'
        );
    }

    private function searchAction(DOMDocument $document): DOMElement
    {
        return $this->single(
            $document,
            '//form[.//input[@name="search"]]//button[@type="submit"]',
            'Search_Action'
        );
    }

    private function resetAction(DOMDocument $document): DOMElement
    {
        return $this->single($document, '//form[.//input[@name="search"]]//a', 'Reset_Action');
    }

    /**
     * @return list<string>
     */
    private function selectedStatusValues(DOMDocument $document): array
    {
        $selected = [];
        foreach ($this->statusSelect($document)->getElementsByTagName('option') as $option) {
            if ($option->hasAttribute('selected')) {
                $selected[] = $option->getAttribute('value');
            }
        }

        return $selected;
    }

    private function single(DOMDocument $document, string $expression, string $description): DOMElement
    {
        $nodes = (new DOMXPath($document))->query($expression);

        self::assertInstanceOf(DOMNodeList::class, $nodes, "Unable to query the {$description}.");
        self::assertSame(1, $nodes->length, "Expected exactly one {$description}.");

        $node = $nodes->item(0);
        self::assertInstanceOf(DOMElement::class, $node);

        return $node;
    }

    /**
     * @return list<string>
     */
    private function classList(DOMElement $element): array
    {
        return preg_split('/\s+/', trim($element->getAttribute('class')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
