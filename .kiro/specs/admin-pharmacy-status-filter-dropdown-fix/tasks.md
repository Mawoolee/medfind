# Implementation Plan

## Overview

This implementation plan follows the bug condition methodology for correcting the Manage Pharmacies top filter row. Execution begins by confirming the defect with a failing exploration property and capturing the unfixed behavior that must be preserved. The scoped Blade presentation fix is then implemented, the same properties are rerun against the fixed code, and focused validation confirms both correctness and preservation. All tasks retain traceability to the requirements and design specifications.

## Task Dependency Graph

```text
1. Bug condition exploration test
└── 2. Preservation property tests
    └── 3.1 Bound responsive Search and Status widths
        └── 3.2 Make Status select presentation deterministic
            └── 3.3 Keep Search and Reset visible and aligned
                ├── 3.4 Rerun Property 1: Expected Behavior
                └── 3.5 Rerun Property 2: Preservation
                    └── 4. Checkpoint validation (also depends on 3.4)
```

| Task | Depends on | Dependency rationale |
|---|---|---|
| 1 | None | The exploration property must be written and must fail on the unfixed code before any implementation begins. |
| 2 | 1 | Preservation behavior is observed and encoded on the same unfixed baseline after the bug counterexample is established. |
| 3.1 | 2 | Implementation cannot begin until both pre-fix test phases are complete. |
| 3.2 | 3.1 | The deterministic select wrapper and indicator build on the bounded Status group width. |
| 3.3 | 3.2 | Action sizing and alignment are finalized after the filter control geometry and select presentation are in place. |
| 3.4 | 3.1, 3.2, 3.3 | The original exploration property is rerun only after the complete scoped implementation is applied. |
| 3.5 | 3.1, 3.2, 3.3 | The original preservation properties are rerun only after the complete scoped implementation is applied; this task may run in parallel with 3.4. |
| 4 | 3.4, 3.5 | Checkpoint validation begins only after both post-fix property reruns pass. |

## Tasks

- [ ] 1. Write the bug condition exploration test before implementing the fix
  - **Property 1: Bug Condition** - Balanced Filter Widths and Visible Status Chevron
  - **CRITICAL**: Write and run this property-based test against the UNFIXED Manage Pharmacies page; it MUST FAIL, and that failure confirms the bug exists. Do not change the test or application code to make it pass during this task.
  - Encode `isBugCondition(input)` from the design for the top Search & Filter form across status values `all`, `approved`, `pending`, and `rejected` and representative viewport widths `375px`, `639px`, `640px`, `767px`, `768px`, `1024px`, and `1280px`.
  - Use a scoped PHPUnit data provider/generative loop and/or Playwright viewport matrix to cover deterministic counterexamples: Search growing beyond `20rem` at `md` and above; Status lacking a fixed `11rem` width at `sm` and above; selected text clipping or entering the indicator region; a covered, absent, or non-deterministic chevron; crowded or misaligned actions; and horizontal overflow.
  - Assert the design's `expectedBehavior(result, input)`: Search is full width below `md` and a non-growing/non-shrinking `20rem` at `md+`; Status is full width below `sm` and a non-growing/non-shrinking `11rem` at `sm+`; selected text is complete; exactly one far-right chevron is visible without overlap; Search and Reset remain visible and bottom-aligned within their flex line; and wrapping does not clip controls.
  - Run the focused test on the unfixed page and record the measured failing counterexamples and computed styles, including viewport, selected status, Search and Status widths, indicator state, action visibility/alignment, and overflow state.
  - **EXPECTED OUTCOME**: The test FAILS on at least one scoped bug-condition input. Mark this task complete only after the expected failure and counterexample are documented.
  - _Requirements: 1.1, 1.2, 2.1, 2.2, 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 4.1, 4.2, 4.3, 4.4, 6.1, 6.2, 6.3_

- [ ] 2. Write preservation property tests before implementing the fix
  - **Property 2: Preservation** - Filtering, Native Interaction, Responsive Wrapping, and Independent Controls
  - **IMPORTANT**: Follow the observation-first methodology on the UNFIXED application for inputs where `isBugCondition(input)` is false; record actual behavior first, then encode it as property-based tests.
  - Observe and test native GET submission for generated Search/Status combinations, preserving the `search` and `status` names and values, existing status options and labels, selected-value restoration, combined query criteria, result identities, and Reset navigation to the unfiltered Manage Pharmacies route.
  - Observe and test native keyboard semantics for the Status select, including source-order tab focus, visible focus, arrow-key navigation, opening, option selection, and submission; confirm no custom dropdown interaction is assumed.
  - Observe and test responsive wrapping over generated widths from `320px` through `1440px`: controls remain visible without horizontal clipping and preserve source and keyboard order of Search, Status, Search button, and Reset.
  - Observe and test that the Activity Log Search and Filter controls retain their existing markup, presentation, and behavior.
  - Observe and test that Manage Pharmacies row-level status controls retain their existing presentation and operation independently of the top Status filter.
  - Run these tests against the unfixed application and document the observed baseline behavior they preserve.
  - **EXPECTED OUTCOME**: All preservation tests PASS on the unfixed code. Mark this task complete only after the baseline observations and passing results are documented.
  - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 6.1, 6.2, 6.3, 6.4, 6.5_

- [ ] 3. Fix the Manage Pharmacies filter-row proportions and Status indicator

  - [ ] 3.1 Bound the responsive Search and Status group widths
    - In only the top Search & Filter form in `resources/views/admin/pharmacies.blade.php`, replace the Search group's unrestricted `flex-1` sizing with `w-full md:flex-[0_0_20rem] md:max-w-[20rem]` while retaining the Search input's existing name, value, appearance, and focus classes.
    - Set the top Status group to `w-full sm:flex-[0_0_11rem] sm:max-w-[11rem]` so it is full width below `sm` and fixed at a non-growing/non-shrinking `11rem` at `sm+`.
    - Retain the form's `flex flex-wrap gap-3 items-end` contract and existing source order.
    - _Bug_Condition: `isBugCondition(input)` is true when unrestricted Search growth or insufficient Status width crowds text, the indicator, or actions in the top Manage Pharmacies filter form._
    - _Expected_Behavior: `expectedBehavior(result, input)` requires Search to be full width below `768px` and `20rem` with zero grow/shrink at `768px+`, and Status to be full width below `640px` and `11rem` with zero grow/shrink at `640px+`._
    - _Preservation: Preserve field names, values, styling not required by the fix, source order, and responsive flex wrapping from the design's Preservation Requirements._
    - _Requirements: 1.1, 1.2, 2.1, 2.2, 5.1, 5.6, 6.1, 6.2, 6.3_

  - [ ] 3.2 Make the native Status select presentation deterministic
    - Wrap only the top native `select[name="status"]` and its decorative indicator in one `relative w-full min-w-[11rem]` wrapper.
    - Keep the native select and existing options/selected-value logic; add `w-full appearance-none bg-none`, replace horizontal `px-3` with `pl-3 pr-10`, and retain its border, radius, height/vertical padding, text, and focus treatment.
    - Render exactly one SVG chevron immediately after the select with `pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500` and `aria-hidden="true"`; do not add focusability, a role, an accessible name, JavaScript, or an event handler.
    - Confirm the select's suppressed generated imagery and reserved `2.5rem` right region leave every complete Status label visible without clipping, truncation, or overlap.
    - _Bug_Condition: `isBugCondition(input)` is true when selected text is clipped or crowds the indicator, generated indicator imagery is covered or invisible, or the top Status control lacks sufficient width._
    - _Expected_Behavior: `expectedBehavior(result, input)` requires a relative wrapper, `appearance: none`, no background image, `0.75rem` left padding, `2.5rem` right padding, and exactly one visible, far-right, vertically centered, pointer-transparent, accessibility-hidden chevron._
    - _Preservation: Keep the Status control as the existing native select with unchanged name, options, labels, selected-state expressions, focus behavior, and assistive-technology semantics._
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 5.2, 5.3, 5.4_

  - [ ] 3.3 Keep Search and Reset visible and aligned
    - Add `flex-none whitespace-nowrap` to the Search submit button and Reset link while preserving their existing labels, styling, destinations, and normal document flow.
    - Verify `items-end` keeps the lower edges of controls aligned within each flex line at unwrapped and wrapped widths; do not hide, absolutely position, or reorder either action.
    - _Bug_Condition: `isBugCondition(input)` is true when Search or Reset is crowded, hidden, label-wrapped, or not bottom-aligned within its flex line._
    - _Expected_Behavior: `expectedBehavior(result, input)` requires both actions to remain visible, non-growing, non-shrinking, non-wrapping, and bottom-aligned without horizontal overflow._
    - _Preservation: Keep Search submission, Reset navigation, action labels, visual treatment, source order, and responsive wrapping unchanged._
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 5.1, 5.5, 5.6, 6.1, 6.2, 6.3_

  - [ ] 3.4 Verify the bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Balanced Filter Widths and Visible Status Chevron
    - **IMPORTANT**: Re-run the SAME exploration test and viewport/status matrix from task 1; do not replace it with a new test.
    - Verify exact Search widths and zero grow/shrink at `md+`, exact Status widths and zero grow/shrink at `sm+`, full-width behavior below each breakpoint, complete labels, reserved padding, exactly one chevron, action visibility/alignment, source order, and no horizontal overflow.
    - **EXPECTED OUTCOME**: The previously failing exploration test PASSES for every scoped bug-condition input, confirming the fix satisfies `expectedBehavior(result, input)`.
    - _Requirements: 1.1, 1.2, 2.1, 2.2, 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 4.1, 4.2, 4.3, 4.4, 6.1, 6.2, 6.3_

  - [ ] 3.5 Verify the preservation property tests still pass
    - **Property 2: Preservation** - Filtering, Native Interaction, Responsive Wrapping, and Independent Controls
    - **IMPORTANT**: Re-run the SAME observation-based preservation tests from task 2; do not write substitute tests after implementation.
    - Compare post-fix GET parameters, selected values, result identities, Reset destination, native keyboard operation, source/keyboard order, responsive visibility and wrapping, Activity Log controls, and row-level status controls with the recorded unfixed baseline.
    - **EXPECTED OUTCOME**: All preservation tests PASS after the fix, confirming no functional or independent-control regressions.
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 6.1, 6.2, 6.3, 6.4, 6.5_

- [ ] 4. Checkpoint - Run focused validation and ensure all tests pass
  - Run the focused PHPUnit tests/data providers for markup, GET filtering, selected-state restoration, Reset behavior, Activity Log preservation, and row-level status-control preservation.
  - Run the focused Playwright tests in single-run mode for the viewport/status matrix, computed widths and padding, one-chevron contract, click-through behavior over the chevron, keyboard semantics, control visibility/alignment, responsive wrapping, and overflow.
  - Run `npm run build` to confirm Tailwind compiles `md:flex-[0_0_20rem]`, `md:max-w-[20rem]`, `sm:flex-[0_0_11rem]`, `sm:max-w-[11rem]`, `appearance-none`, `bg-none`, and the positioning utilities used by the fix.
  - Ensure the expected-failing test evidence from the unfixed baseline is retained, then confirm the full focused suite and build pass after implementation; ask the user if any question or unexpected failure arises.

## Notes

- Tasks 1 and 2 are mandatory standalone pre-fix tasks. Do not implement application changes until the exploration failure and preservation baseline are documented.
- Task 1 must fail on the unfixed code and must be rerun unchanged in task 3.4; task 2 must pass on the unfixed code and must be rerun unchanged in task 3.5.
- Tasks 3.1 through 3.3 are the complete scoped implementation sequence. Tasks 3.4 and 3.5 may run in parallel only after all three implementation subtasks are complete.
- Task 4 is the final gate and depends on successful completion of both post-fix reruns. Retain the pre-fix counterexample evidence when recording final validation.
- The plan changes only the top Search & Filter form in `resources/views/admin/pharmacies.blade.php`; Activity Log controls, row-level status controls, controller behavior, routes, query semantics, and other application code remain outside scope.