# Requirements Document

## Introduction

This document defines the requirements derived from the approved design for the Manage Pharmacies Search and Status filter row. The feature corrects the filter-row proportions and Status indicator presentation while preserving native filtering, accessibility, responsive wrapping, and unrelated controls.

The design's Tailwind utility terms are expressed below as verifiable presentation outcomes rather than as a required markup implementation: `appearance-none bg-none` means suppressed native or plugin-provided select indicator imagery and no select background image; `pl-3 pr-10` means `0.75rem` left padding and `2.5rem` right padding.

## Glossary

- **Manage_Pharmacies_Filter_System**: The top Search and Filter form on the Manage Pharmacies page.
- **Search_Group**: The Search label and the input whose submitted name is `search`.
- **Top_Status_Filter**: The Status label and native select whose submitted name is `status` in the Manage_Pharmacies_Filter_System.
- **Native_Status_Select**: The browser-native select element within the Top_Status_Filter.
- **Search_Action**: The submit button labeled `Search` in the Manage_Pharmacies_Filter_System.
- **Reset_Action**: The link labeled `Reset` in the Manage_Pharmacies_Filter_System.
- **Action_Controls**: The Search_Action and Reset_Action collectively.
- **Relative_Wrapper**: The positioning context that contains the Native_Status_Select and Explicit_Chevron without replacing native select behavior.
- **Explicit_Chevron**: The single decorative downward SVG indicator displayed by the Top_Status_Filter.
- **Generated_Indicator_Imagery**: Browser-native or plugin-provided arrow or background imagery associated with the Native_Status_Select.
- **Absolute_Positioning**: Placement relative to the Relative_Wrapper without consuming layout space.
- **Far_Right_Position**: A position `0.75rem` from the right edge of the Relative_Wrapper.
- **Vertical_Center_Position**: A position centered on the vertical midpoint of the Native_Status_Select.
- **Full_Width**: The available inner width of the current filter-form flex line.
- **Fixed_Non_Growing_Width**: A width whose flex basis and maximum width equal the specified value and whose flex-grow and flex-shrink values are zero.
- **Small_Breakpoint**: A viewport width of `640px`, corresponding to the `sm` breakpoint.
- **Medium_Breakpoint**: A viewport width of `768px`, corresponding to the `md` breakpoint.
- **Control_Alignment**: Alignment in which the lower edges of controls on the same flex line share the form's bottom alignment.
- **Responsive_Wrapping**: Placement of controls onto additional flex lines when the available width cannot contain the controls on one line, without horizontal clipping or source-order changes.
- **Native_GET_Filtering**: Submission by the HTTP GET method using the existing `search` and `status` query parameter names and their selected values.
- **Native_Keyboard_Semantics**: Browser-provided tab focus, focus indication, arrow-key navigation, opening, option selection, and assistive-technology semantics of a native select.
- **Status_Options**: The existing `all`, `approved`, `pending`, and `rejected` option values and their displayed labels.
- **Activity_Log_Controls**: The Search and Filter controls on the Activity Log page.
- **Row_Level_Status_Controls**: The pharmacy status controls rendered within Manage Pharmacies table rows.

## Requirements

### Requirement 1: Responsive Search Width

**User Story:** As an administrator, I want the Search field to use a predictable responsive width, so that the filter row remains balanced at every supported viewport width.

#### Acceptance Criteria

1. WHILE the viewport width is below the Medium_Breakpoint, THE Manage_Pharmacies_Filter_System SHALL size the Search_Group to Full_Width.
2. WHILE the viewport width is at or above the Medium_Breakpoint, THE Manage_Pharmacies_Filter_System SHALL size the Search_Group to a Fixed_Non_Growing_Width of `20rem`.

### Requirement 2: Responsive Status Width

**User Story:** As an administrator, I want the Status filter to have sufficient responsive width, so that every Status option remains readable.

#### Acceptance Criteria

1. WHILE the viewport width is below the Small_Breakpoint, THE Manage_Pharmacies_Filter_System SHALL size the Top_Status_Filter to Full_Width.
2. WHILE the viewport width is at or above the Small_Breakpoint, THE Manage_Pharmacies_Filter_System SHALL size the Top_Status_Filter to a Fixed_Non_Growing_Width of `11rem`.

### Requirement 3: Deterministic Status Select Presentation

**User Story:** As an administrator, I want the Status filter text and indicator to remain distinct and visible, so that the selected Status and dropdown affordance are clear.

#### Acceptance Criteria

1. THE Top_Status_Filter SHALL contain the Native_Status_Select and Explicit_Chevron within one Relative_Wrapper.
2. THE Native_Status_Select SHALL suppress Generated_Indicator_Imagery and select background imagery, producing the presentation defined by `appearance-none bg-none`.
3. THE Native_Status_Select SHALL reserve `0.75rem` of left padding and `2.5rem` of right padding, producing the spacing defined by `pl-3 pr-10`.
4. WHEN the Top_Status_Filter is rendered, THE Top_Status_Filter SHALL display exactly one SVG Explicit_Chevron using Absolute_Positioning at the Far_Right_Position and Vertical_Center_Position.
5. THE Explicit_Chevron SHALL allow pointer input to pass through to the Native_Status_Select by using pointer-transparent behavior equivalent to `pointer-events-none`.
6. THE Explicit_Chevron SHALL be excluded from keyboard focus and the accessibility tree.
7. WHEN any Status_Options value is selected, THE Top_Status_Filter SHALL display the complete selected label without clipping, truncation, overlap, or entry into the `2.5rem` right-side indicator region.

### Requirement 4: Visible and Aligned Actions

**User Story:** As an administrator, I want the filter actions to remain visible and aligned, so that Search and Reset are available in every responsive layout.

#### Acceptance Criteria

1. WHILE the Manage_Pharmacies_Filter_System is displayed, THE Search_Action SHALL remain visible with zero flex grow, zero flex shrink, and an unwrapped label.
2. WHILE the Manage_Pharmacies_Filter_System is displayed, THE Reset_Action SHALL remain visible with zero flex grow, zero flex shrink, and an unwrapped label.
3. WHILE Action_Controls share a flex line with filter controls, THE Manage_Pharmacies_Filter_System SHALL maintain Control_Alignment for every control on that flex line.
4. WHEN Responsive_Wrapping places Action_Controls on a later flex line, THE Manage_Pharmacies_Filter_System SHALL maintain Control_Alignment for the controls on that flex line.

### Requirement 5: Filtering and Native Interaction Preservation

**User Story:** As an administrator, I want the corrected layout to preserve existing filter behavior, so that the presentation fix does not change Manage Pharmacies results or interaction semantics.

#### Acceptance Criteria

1. WHEN an administrator submits the Manage_Pharmacies_Filter_System, THE Manage_Pharmacies_Filter_System SHALL perform Native_GET_Filtering with the current Search_Group value and Top_Status_Filter value.
2. THE Native_Status_Select SHALL provide the Status_Options with the existing option values and displayed labels.
3. WHEN the Manage Pharmacies page is rendered with a valid `status` query value, THE Native_Status_Select SHALL select the Status_Options value that matches the `status` query value.
4. WHEN an administrator operates the Native_Status_Select by keyboard, THE Native_Status_Select SHALL preserve Native_Keyboard_Semantics.
5. WHEN an administrator activates the Reset_Action, THE Reset_Action SHALL navigate to the existing unfiltered Manage Pharmacies route.
6. WHEN an administrator combines a Search_Group value with a Top_Status_Filter value, THE Manage_Pharmacies_Filter_System SHALL preserve both values in the submitted GET query.

### Requirement 6: Responsive and Independent Control Preservation

**User Story:** As an administrator, I want responsive layout and unrelated controls to remain unchanged, so that the scoped filter presentation fix causes no regressions elsewhere.

#### Acceptance Criteria

1. WHEN available width cannot contain the Manage_Pharmacies_Filter_System controls on one flex line, THE Manage_Pharmacies_Filter_System SHALL apply Responsive_Wrapping.
2. WHILE Responsive_Wrapping is active, THE Manage_Pharmacies_Filter_System SHALL preserve the source and keyboard order of Search_Group, Top_Status_Filter, Search_Action, and Reset_Action.
3. WHILE Responsive_Wrapping is active, THE Manage_Pharmacies_Filter_System SHALL keep every filter control visible without horizontal clipping.
4. WHEN the Activity Log page is rendered, THE Activity_Log_Controls SHALL retain the behavior and presentation that existed before the Manage Pharmacies filter fix.
5. WHEN Row_Level_Status_Controls are rendered or operated, THE Row_Level_Status_Controls SHALL retain behavior and presentation independent of the Top_Status_Filter.
