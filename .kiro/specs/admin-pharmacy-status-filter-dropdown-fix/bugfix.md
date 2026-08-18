# Bugfix Requirements Document

## Introduction

The Manage Pharmacies page's top `All Statuses` dropdown used to search and filter pharmacies does not consistently present its selected text and dropdown chevron with the clarity shown by the Activity Log's `All Actions` and `All Entities` dropdowns. This bugfix is limited to the appearance and alignment of that top Status filter while preserving its native filtering behavior, responsive wrapping, and the separate pharmacy status controls within the table.

## Bug Analysis

### Current Behavior (Defect)

The top Status filter can appear crowded and visually inconsistent with the other filter controls.

1.1 WHEN the top Status filter displays `All Statuses` THEN the system can render the selected text incompletely or too close to the dropdown chevron.
1.2 WHEN the top Status filter displays `All Statuses` THEN the system can render the dropdown chevron without clear visibility at the far-right side of the control or without enough reserved space to prevent overlap with the selected text.
1.3 WHEN the Manage Pharmacies filter bar is displayed THEN the system can render the top Status filter with a control height, border, radius, or baseline alignment inconsistent with the Search input, Search button, and Reset action.
1.4 WHEN the Manage Pharmacies filter controls wrap at a supported viewport size THEN the system can render the top Status filter with clipped or crowded selected text, overlapping chevron spacing, or misalignment with the wrapped controls.

### Expected Behavior (Correct)

The top Status filter must match the clear, readable dropdown appearance represented by the `All Actions` and `All Entities` reference controls.

2.1 WHEN the top Status filter displays `All Statuses` THEN the system SHALL display the complete selected text without clipping, truncation, overlap, or crowding.
2.2 WHEN the top Status filter displays `All Statuses` THEN the system SHALL display a clearly visible dropdown chevron at the far-right side of the control with adequate right-side padding that prevents the chevron from overlapping the selected text.
2.3 WHEN the Manage Pharmacies filter bar is displayed THEN the system SHALL render the top Status filter with a control height, border, and radius consistent with the Search input and with baseline alignment across the Search input, Search button, and Reset action.
2.4 WHEN the Manage Pharmacies filter controls wrap at a supported viewport size THEN the system SHALL keep the complete selected text and chevron visible with adequate separation and preserve alignment within the wrapped filter layout.

### Unchanged Behavior (Regression Prevention)

Existing filter functionality, responsive behavior, and unrelated table controls must remain unchanged.

3.1 WHEN the top Status filter displays any available status option other than `All Statuses` THEN the system SHALL CONTINUE TO display the complete selected option with a visible chevron, adequate text-to-chevron spacing, and consistent filter-control alignment.
3.2 WHEN an administrator selects a Status option and submits the Manage Pharmacies filters THEN the system SHALL CONTINUE TO use the native dropdown interaction and apply the selected Status criterion together with any Search criterion as before.
3.3 WHEN an administrator resets the Manage Pharmacies filters THEN the system SHALL CONTINUE TO clear the Search and Status criteria and return to the unfiltered pharmacy listing as before.
3.4 WHEN the available width causes the Manage Pharmacies filter controls to wrap THEN the system SHALL CONTINUE TO wrap the Search input, top Status filter, Search button, and Reset action responsively.
3.5 WHEN an administrator views or uses a pharmacy's status control within a table row THEN the system SHALL CONTINUE TO display and operate that table control independently of the top Status filter.
