# Admin Pharmacy Status Filter Dropdown Fix Bugfix Design

## Overview

The latest Manage Pharmacies screenshot shows a filter-row proportion problem as well as the original Status indicator defect. The Search field uses unrestricted `flex-1`, so it consumes most of the available horizontal space. The top Status filter is consequently too narrow for `All Statuses`, reserved indicator space, and a visible downward chevron; the chevron can appear covered or absent. The Search button and Reset action must remain visible and aligned rather than being crowded out by the expanding Search field.

The fix is a local responsive-layout and native-select presentation change in `resources/views/admin/pharmacies.blade.php`. The Search group will be full-width below the `md` breakpoint and use a non-growing, non-shrinking `20rem` basis at `md` and above. The Status group will be full-width below the `sm` breakpoint and use a non-growing, non-shrinking `11rem` width at `sm` and above. Its select will use a relative wrapper, deterministic right-side spacing, suppressed browser/plugin arrow imagery, and one explicit decorative SVG chevron. Search and Reset will remain non-shrinking, non-wrapping controls in the existing wrapping, bottom-aligned form.

Only `design.md` is changed in this phase. The design does not alter application code, the Activity Log reference controls, controller behavior, or pharmacy status controls inside table rows.

## Glossary

- **Bug_Condition (C)**: A Manage Pharmacies filter-row render state in which unrestricted Search growth leaves the Status control too narrow, the selected Status label or chevron is obscured, or Search/Reset are crowded, hidden, or misaligned.
- **Property (P)**: The corrected responsive layout: bounded Search width, sufficient Status width, complete selected text, one visible far-right chevron, visible aligned actions, and clean wrapping.
- **Preservation**: Existing native filtering, reset navigation, keyboard behavior, option values, responsive control order, and unrelated controls that must remain unchanged.
- **Render_Input (X)**: The page, viewport width, selected status, search value, focus/input method, and filter query used to render or operate the filter row.
- **Original_Renderer (F)**: The current Blade markup, including the Search group's `flex-1` and the narrow native Status select.
- **Fixed_Renderer (F')**: The same Blade view after applying the bounded responsive Search basis, dedicated Status width, explicit chevron, and non-shrinking action layout.
- **Top_Status_Filter**: The `select[name="status"]` in the Manage Pharmacies Search & Filter form, not a row-level pharmacy status action.
- **Search_Group**: The label/input group for `input[name="search"]`.
- **Action_Controls**: The Search submit button and Reset link.
- **Explicit_Chevron**: The single decorative SVG placed over the select's reserved right region and marked `aria-hidden="true"` and `pointer-events-none`.
- **Small_Layout**: Viewports below Tailwind's `sm` breakpoint (`<640px`).
- **Intermediate_Layout**: Viewports from `640px` through `767px`.
- **Desktop_Layout**: Viewports at Tailwind's `md` breakpoint and above (`>=768px`).

## Bug Details

### Bug Condition

Let `D` be the set of Manage Pharmacies filter render inputs and `F(X)` the original rendered result. The bug condition is:

`C = { X ∈ D | isManagePharmaciesFilter(X) ∧ isSupportedViewport(X) ∧ (searchConsumesExcessWidth(F(X)) ∨ statusWidthIsInsufficient(F(X)) ∨ selectedTextCrowdsIndicator(F(X)) ∨ indicatorIsCoveredOrInvisible(F(X)) ∨ actionsAreCrowdedOrMisaligned(F(X))) }`

The condition applies only to the top Search & Filter form. It does not include row-level status controls.

**Formal Specification:**
```
FUNCTION isBugCondition(input)
  INPUT: input of type Render_Input
  OUTPUT: boolean

  originalResult := Original_Renderer(input)

  RETURN input.page = "admin.pharmacies"
         AND input.controlRegion = "top-search-and-filter-form"
         AND input.selectedStatus IN ["all", "approved", "pending", "rejected"]
         AND isSupportedViewport(input.viewportWidth)
         AND (
           searchGroupGrowsBeyondBound(originalResult, 20rem)
           OR statusControlWidthIsLessThan(originalResult, 11rem)
           OR selectedTextIsClipped(originalResult)
           OR selectedTextOverlapsOrCrowdsIndicator(originalResult)
           OR downwardChevronIsCoveredOrNotVisible(originalResult)
           OR searchButtonOrResetIsNotVisible(originalResult)
           OR actionControlsAreNotBottomAlignedWithinTheirFlexLine(originalResult)
         )
END FUNCTION
```

### Examples

- **Desktop proportion defect (`>=768px`)**: The original `flex-1` Search group expands across most of the row while Status stays intrinsically narrow. The corrected Search group is exactly `20rem` wide, and Status is exactly `11rem` wide.
- **Default Status option**: With `status=all`, the original control can crowd `All Statuses` against a browser/plugin arrow or obscure the indicator. The corrected `11rem` control shows the complete label, `2.5rem` of right padding, and one visible chevron.
- **Intermediate width (`640–767px`)**: Search occupies its own full-width line. Status is `11rem`, and Search/Reset remain visible beside it when space permits or move together onto a later flex line without clipping.
- **Narrow mobile width (`<640px`)**: Search and Status each occupy a full-width line. Search and Reset remain visible on a following line and align at their lower edge.
- **Boundary at `768px`**: Search switches from full width to a fixed `20rem` basis. Status remains `11rem`; the controls fit or wrap according to available inner form width without overflow or hidden actions.
- **Keyboard interaction**: Tabbing to Status and using native select keys continues to work. The explicit chevron is not focusable and cannot intercept pointer input.

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- The form remains `flex flex-wrap gap-3 items-end`; source order remains Search, Status, Search button, Reset.
- The Search input retains `name="search"`, its submitted value, placeholder, border, radius, height, and focus treatment.
- Status remains a native `<select name="status">` with the existing `all`, `approved`, `pending`, and `rejected` values and selected-value logic.
- GET submission continues to combine Search and Status criteria exactly as before.
- Reset continues to navigate to the unfiltered Manage Pharmacies route.
- Native tab, focus, arrow-key, opening, selection, and assistive-technology semantics remain intact; no custom dropdown script is introduced.
- Search and Reset remain normal in-flow controls. They are not hidden, absolutely positioned, or removed at any breakpoint.
- Search and Reset keep their existing visual styling and `py-2` vertical sizing, while `flex-none whitespace-nowrap` prevents shrinking or label wrapping.
- Activity Log controls and pharmacy row-level status controls remain unchanged.

**Scope:**
All behavior not needed to correct filter-row proportions, Status text/chevron visibility, action visibility/alignment, and responsive wrapping is outside this fix. This includes:
- Controller query construction and result selection
- Pagination and table rendering
- Pharmacy status updates from table rows
- Activity Log markup
- JavaScript or custom listbox behavior

## Hypothesized Root Cause

Based on the latest screenshot and current `resources/views/admin/pharmacies.blade.php` markup, the likely causes are:

1. **Unbounded Search growth**: The Search wrapper uses `flex-1`.
   - It absorbs all remaining width rather than stopping at a useful desktop size.
   - This creates an imbalanced row and leaves less predictable room for Status and actions.

2. **No dedicated Status width**: The Status group relies on the native select's intrinsic width.
   - There is no explicit `11rem` allocation for `All Statuses`, left padding, `2.5rem` right padding, and a chevron.
   - Browser/plugin styling can consume part of the already narrow content area.

3. **Non-deterministic generated indicator**: The select neither suppresses native/plugin arrow imagery nor renders its own icon.
   - The downward indicator can vary by environment and may appear covered, duplicated, or invisible.

4. **No independent chevron positioning context**: The select has no relative wrapper.
   - A chevron cannot be consistently placed at the far right and vertically centered.

5. **Actions are allowed to compete with flexible content**: Search and Reset do not explicitly declare non-shrinking, non-wrapping behavior.
   - Unrestricted Search growth can crowd them or force visually poor wrapping.

Exploratory checks must confirm these causes on the unfixed page. If the measured layout differs, implementation must pause and re-hypothesize before broadening the change.

## Correctness Properties

Property 1: Bug Condition - Balanced Filter Widths and Visible Status Chevron

_For any_ Manage Pharmacies render input where the bug condition holds (`isBugCondition` returns true), the fixed renderer SHALL bound Search to a `20rem` non-growing/non-shrinking basis at `>=768px` and full width below `768px`; render Status full width below `640px` and at a dedicated `11rem` non-growing/non-shrinking width at `>=640px`; display the complete selected label with one visible far-right chevron and no overlap; keep Search and Reset visible and bottom-aligned within their flex line; and wrap without horizontal clipping.

**Validates: Requirements 2.1, 2.2, 2.3, 2.4**

**Property Predicate:**
```
FUNCTION expectedBehavior(result, input)
  INPUT: result of type Rendered_Filter_Row
         input of type Render_Input
  OUTPUT: boolean

  searchWidthIsCorrect :=
    IF input.viewportWidth >= 768px
      THEN searchGroupWidth(result) = 20rem
           AND searchFlexGrow(result) = 0
           AND searchFlexShrink(result) = 0
    ELSE searchGroupWidth(result) = availableFormLineWidth(result)

  statusWidthIsCorrect :=
    IF input.viewportWidth >= 640px
      THEN statusGroupWidth(result) = 11rem
           AND statusFlexGrow(result) = 0
           AND statusFlexShrink(result) = 0
    ELSE statusGroupWidth(result) = availableFormLineWidth(result)

  RETURN searchWidthIsCorrect
         AND statusWidthIsCorrect
         AND statusWrapperPosition(result) = "relative"
         AND selectedText(result) = fullLabelFor(input.selectedStatus)
         AND selectedTextIsFullyVisible(result)
         AND selectRightPadding(result) = 2.5rem
         AND explicitChevronCount(result) = 1
         AND chevronIsVisibleAtFarRightAndVerticallyCentered(result)
         AND selectedTextBounds(result) DO NOT OVERLAP chevronReservedRegion(result)
         AND selectAppearance(result) = "none"
         AND selectBackgroundImage(result) = "none"
         AND decorativeChevronIsPointerTransparentAndHiddenFromAccessibilityTree(result)
         AND searchButtonIsVisible(result)
         AND resetIsVisible(result)
         AND actionLabelsDoNotWrap(result)
         AND controlsAreBottomAlignedWithinEachFlexLine(result)
         AND filterFormHasNoHorizontalOverflow(result)
END FUNCTION
```

Property 2: Preservation - Filtering, Native Interaction, Control Order, and Independent Controls

_For any_ input where the bug condition does not hold (`isBugCondition` returns false), the fixed renderer and interaction flow SHALL produce the same functional result as the original implementation, preserving Search and Status query values, status options, Reset navigation, native keyboard accessibility, source order, responsive flex wrapping, Activity Log controls, and pharmacy table-row status controls.

**Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5**

## Fix Implementation

### Changes Required

Assuming the root-cause analysis is confirmed, make one scoped application edit in the later implementation phase.

**File**: `resources/views/admin/pharmacies.blade.php`

**Elements**: The top Search group, top Status group/select, Search submit button, and Reset link

**Specific Changes**:

1. **Replace unrestricted Search growth with a bounded responsive basis**
   - Replace the Search wrapper's `flex-1 min-w-[200px]` sizing contract with `w-full md:flex-[0_0_20rem] md:max-w-[20rem]`.
   - Below `768px`, `w-full` makes Search consume one complete flex line.
   - At `768px` and above, `md:flex-[0_0_20rem]` fixes its basis at `20rem` with zero grow and zero shrink; `md:max-w-[20rem]` makes the upper bound explicit.
   - Keep the input itself `w-full` and retain its existing visual/input classes.

2. **Allocate a dedicated responsive Status width**
   - Give the outer Status label/control group `w-full sm:flex-[0_0_11rem] sm:max-w-[11rem]`.
   - Below `640px`, Status occupies a full line.
   - At `640px` and above, Status is exactly `11rem` with zero grow and zero shrink.
   - `11rem` is the testable width budget for `All Statuses`, `pl-3`, `pr-10`, and the right-aligned icon.

3. **Add a relative, width-bearing select wrapper**
   - Wrap the native select and decorative icon in `<div class="relative w-full min-w-[11rem]">`.
   - The wrapper provides the chevron positioning context and prevents the control from becoming narrower than `11rem`.

4. **Reserve the icon region and suppress generated arrows**
   - Add `w-full appearance-none bg-none` to the select.
   - Replace `px-3` with `pl-3 pr-10`; `pr-10` reserves exactly `2.5rem` on the right.
   - Preserve `py-2 text-sm border border-gray-300 rounded-lg` and the existing focus utilities.

5. **Render one explicit decorative chevron**
   - Place one SVG immediately after the select in the relative wrapper.
   - Use `pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500`.
   - Add `aria-hidden="true"`; do not add `tabindex`, a role, an accessible name, or an event handler.
   - `appearance-none bg-none` must remove browser/forms-plugin arrow presentation so the SVG is the only visible indicator.

6. **Keep Search and Reset visible and aligned**
   - Retain the form's `flex flex-wrap gap-3 items-end` classes.
   - Add `flex-none whitespace-nowrap` to the Search button and Reset link so neither shrinks nor wraps its label.
   - Keep both controls in normal document flow and preserve their existing `py-2`; `items-end` aligns their lower edges with the input/select controls on each flex line.
   - Do not hide either action at any breakpoint.

7. **Keep behavior and unrelated controls unchanged**
   - Do not change field names, options, selected-state expressions, form method/action, routes, or query handling.
   - Do not add JavaScript or Alpine state.
   - Do not edit Activity Log selects or row-level pharmacy status controls.

**Target Markup Shape:**
```html
<form class="... flex flex-wrap gap-3 items-end">
    <div class="w-full md:flex-[0_0_20rem] md:max-w-[20rem]">
        <label class="block text-xs font-semibold text-gray-600 mb-1">Search</label>
        <input name="search" class="w-full ..." />
    </div>

    <div class="w-full sm:flex-[0_0_11rem] sm:max-w-[11rem]">
        <label class="block text-xs font-semibold text-gray-600 mb-1">Status</label>
        <div class="relative w-full min-w-[11rem]">
            <select name="status"
                    class="w-full appearance-none bg-none border border-gray-300 rounded-lg pl-3 pr-10 py-2 text-sm focus:ring-2 focus:ring-purple-300 focus:border-purple-400">
                <!-- Existing options and selected-value logic remain unchanged. -->
            </select>
            <svg aria-hidden="true"
                 class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </div>
    </div>

    <button type="submit" class="... flex-none whitespace-nowrap">Search</button>
    <a href="..." class="... flex-none whitespace-nowrap">Reset</a>
</form>
```

The target markup is a design artifact only; no application code is changed in this phase.

## Testing Strategy

### Validation Approach

Validation has two phases. First, measure the unfixed row and capture counterexamples that confirm unrestricted Search growth, insufficient Status width, and missing/covered indicator behavior. Second, verify the concrete breakpoint geometry and corrected chevron while comparing functional behavior with the original implementation. Browser geometry and computed-style assertions are required because this is primarily a responsive layout defect.

### Exploratory Bug Condition Checking

**Goal**: Demonstrate the bug before implementation and confirm the root-cause hypothesis. If measurements refute it, revise the hypothesis before changing application code.

**Test Plan**: Render Manage Pharmacies with `status=all`, inspect bounding boxes and computed styles for Search, Status, Search button, and Reset, and capture screenshots at `375px`, `639px`, `640px`, `767px`, `768px`, `1024px`, and `1280px` viewport widths.

**Test Cases**:
1. **Desktop Search Growth**: At `1024px` and `1280px`, verify the unfixed Search group grows beyond `20rem` because of `flex-1`.
2. **Status Width and Indicator**: At each viewport, measure whether Status is below `11rem`, whether `All Statuses` enters the indicator region, and whether a downward indicator is visible.
3. **Breakpoint Wrapping**: At `639/640px` and `767/768px`, record flex lines, control order, overflow, and action visibility.
4. **Action Visibility**: Confirm whether unrestricted Search growth crowds Search or Reset or produces poor lower-edge alignment.
5. **Native Interaction Baseline**: Tab to Status, select with keyboard input, submit, and record behavior that must be preserved.

**Expected Counterexamples**:
- Search exceeds `20rem` on a sufficiently wide viewport.
- Status has no explicit `11rem` width or `2.5rem` reserved icon region.
- The original indicator is browser/plugin-dependent and can be covered, unclear, or absent.
- Action placement depends on unrestricted Search growth rather than a stable width budget.

### Fix Checking

**Goal**: Verify that all sampled inputs satisfying the bug condition meet the exact corrected layout and indicator contract.

**Pseudocode:**
```
FOR ALL input WHERE isBugCondition(input) DO
  result := Fixed_Renderer(input)
  ASSERT expectedBehavior(result, input)
END FOR
```

**Checks**:
- At `768px`, `1024px`, and `1280px`, Search has a `320px` (`20rem`) bounding width and computed flex grow/shrink of zero.
- At `375px`, `639px`, `640px`, and `767px`, Search equals the available inner form-line width.
- At `640px` and above, Status has a `176px` (`11rem`) bounding width and computed flex grow/shrink of zero.
- Below `640px`, Status equals the available inner form-line width while retaining a minimum width of `176px`.
- The select has `appearance: none`, no background image, `12px` left padding, and `40px` right padding.
- Exactly one SVG chevron exists; it is `16px` square, `12px` from the right edge, vertically centered, visible, pointer-transparent, and accessibility-hidden.
- `All Statuses`, `Approved`, `Pending`, and `Rejected` remain fully visible and do not intersect the final `40px` chevron region.
- Search and Reset are visible, non-shrinking, and non-wrapping at every test width.
- Controls preserve source order, align at the lower edge within each flex line, and produce no horizontal page or form overflow.

### Preservation Checking

**Goal**: Verify that inputs outside the bug condition preserve the original functional result.

**Pseudocode:**
```
FOR ALL input WHERE NOT isBugCondition(input) DO
  originalResult := observeFunctionalBehavior(Original_Renderer, input)
  fixedResult := observeFunctionalBehavior(Fixed_Renderer, input)
  ASSERT originalResult = fixedResult
END FOR
```

**Testing Approach**: Compare request URLs, selected values, result identifiers, reset destinations, native keyboard behavior, semantic control order, and unrelated controls. Pixel equality is not required because the intended width and indicator presentation deliberately change.

**Test Plan**: Record original behavior before implementation, then replay the same Search/Status, keyboard, pointer, reset, and row-control scenarios after the fix.

**Test Cases**:
1. **Combined Filter Preservation**: Search/Status combinations submit the same names and values and produce the same result set.
2. **Reset Preservation**: Reset still navigates to the unfiltered Manage Pharmacies route.
3. **Native Select Preservation**: Tab, arrow-key, opening, selection, and submission behavior remain native.
4. **Control-Order Preservation**: Flex wrapping never changes the source or keyboard order: Search, Status, Search button, Reset.
5. **Independent Row Control Preservation**: Row-level pharmacy status controls remain unchanged and operable.
6. **Reference Page Preservation**: Activity Log filters remain unchanged.

### Unit Tests

- Assert the Search wrapper uses `w-full md:flex-[0_0_20rem] md:max-w-[20rem]` and no longer uses unrestricted `flex-1`.
- Assert the Status group uses `w-full sm:flex-[0_0_11rem] sm:max-w-[11rem]` and its inner wrapper uses `relative w-full min-w-[11rem]`.
- Assert the select contains `w-full appearance-none bg-none pl-3 pr-10` and retains its existing border, radius, vertical padding, text size, focus utilities, name, options, and selected-value logic.
- Assert exactly one adjacent SVG has `aria-hidden="true"` and all required positioning, size, color, and `pointer-events-none` classes.
- Assert Search and Reset use `flex-none whitespace-nowrap` and remain in source order.
- Assert row-level status controls do not acquire top-filter classes.

### Property-Based Tests

- Generate all status options across viewport widths around `375`, `639/640`, `767/768`, `1024`, and `1280px`; verify `expectedBehavior` from DOM geometry and computed styles.
- Generate Search/Status query combinations; verify submitted parameters, selected values, and result identities match the original behavior.
- Generate keyboard and pointer sequences over the select and chevron region; verify the select is the only interactive/focusable control in its wrapper.
- Generate widths throughout `320–1440px`; verify there is no horizontal overflow, labels do not overlap the chevron region, actions remain visible, and source order is preserved.

The project need not add a PHP property-testing dependency. PHPUnit data providers/generative loops and Playwright viewport/state matrices can implement these properties.

### Integration Tests

- Use Playwright in single-run mode to verify exact Search and Status widths at the named breakpoint samples.
- Select each Status option, combine it with Search text, submit, and verify URL parameters, selected values, and displayed results.
- Verify `All Statuses` and the downward chevron are both visible at desktop, intermediate, and narrow layouts.
- Verify Search and Reset remain visible and lower-edge aligned on every flex line, including wrapped layouts at `375px`, `640px`, and `767px`.
- Navigate entirely by keyboard and verify native select operation and control order.
- Click over the visible chevron and verify `pointer-events-none` allows the native select to receive the interaction.
- Exercise a row-level status control after using the top filter and verify independence.
- In the later implementation phase, run `npm run build` and focused PHPUnit/Playwright single-run checks to confirm arbitrary Tailwind classes compile and behavior passes.