# Requirements Document

## Introduction

MedFind consumer pages currently render a page-level "Back" link in the page header on every viewport size. On phone-sized screens these links crowd the header, duplicate navigation that is already reachable from the always-visible navbar "Menu" dropdown, and add visual noise next to the page title. This document specifies hiding page-level back links on phone viewports while retaining them on tablet and desktop, without removing the in-page back controls that are the only means of backward navigation on a phone.

A secondary, related defect is included in scope: the shared `x-back-button` component accepts a `label` prop but renders a hardcoded visible caption, so 22 call sites that pass `label="Back to Dashboard"` display only "Back".

## Glossary

- **Page_Back_Link**: A back navigation control placed in a page header whose destination is a different page (for example, "Back" on the pharmacy details page returning to the consumer dashboard)
- **Panel_Back_Control**: A back control that dismisses an in-page panel or view rather than navigating to another page (for example, the control that returns from an open conversation to the conversation list in the messages split view)
- **Phone_Viewport**: A viewport narrower than the Tailwind `sm` breakpoint (below 640px)
- **Desktop_Viewport**: A viewport at or wider than the Tailwind `sm` breakpoint (640px and above)
- **Navbar_Menu**: The always-visible authenticated navigation dropdown in `resources/views/layouts/navigation.blade.php` that links to Map, My Messages, Search Medicines, and Profile Settings
- **Back_Button_Component**: The shared Blade component at `resources/views/components/back-button.blade.php`
- **Consumer_Pages**: The four consumer views in scope — `consumer/pharmacy-details.blade.php`, `consumer/profile.blade.php`, `consumer/search.blade.php`, and `consumer/messages.blade.php`

## Requirements

### Requirement 1: Hide Page Back Links on Phone Viewports

**User Story:** As a consumer browsing on my phone, I want the page header to show only the page title without a redundant Back link, so that the header is cleaner and easier to read on a narrow screen.

#### Acceptance Criteria

1. WHEN a Consumer_Page is rendered at a Phone_Viewport, THE Page_Back_Link SHALL NOT be visible
2. WHEN a Consumer_Page is rendered at a Desktop_Viewport, THE Page_Back_Link SHALL be visible and retain its current appearance, position, and destination
3. THE visibility change SHALL be achieved with CSS responsive utilities only, with no JavaScript and no user-agent detection
4. THE Page_Back_Link SHALL remain present in the rendered DOM at all viewport sizes so that no route or markup change is required

### Requirement 2: Preserve Layout When the Back Link Is Hidden

**User Story:** As a consumer on my phone, I want the page title area to look intentional when the Back link is hidden, so that the page does not show a gap or misaligned heading.

#### Acceptance Criteria

1. WHEN the Page_Back_Link is hidden at a Phone_Viewport, THE page header SHALL NOT leave residual empty space where the link would have been
2. WHERE the Page_Back_Link is wrapped in a dedicated container element that contributes its own spacing, THE container SHALL be hidden together with the link
3. WHEN the Page_Back_Link is hidden, THE page title SHALL remain left-aligned and SHALL be permitted to use the reclaimed horizontal space
4. THE vertical rhythm of content below the page header SHALL NOT shift by more than the height of the removed control

### Requirement 3: Retain Essential Backward Navigation on Phone

**User Story:** As a consumer reading a conversation on my phone, I want a way to return to my conversation list and to leave a chat, so that I am never stranded in a view with no exit.

#### Acceptance Criteria

1. THE Panel_Back_Control in the messages split view SHALL remain visible at a Phone_Viewport
2. THE back control in the chat page header SHALL remain visible at a Phone_Viewport
3. WHERE a view has no alternative backward navigation reachable from the Navbar_Menu, ITS back control SHALL NOT be hidden
4. WHEN all in-scope Page_Back_Links are hidden at a Phone_Viewport, THE Navbar_Menu SHALL still provide navigation to Map, My Messages, Search Medicines, and Profile Settings

### Requirement 4: Accessibility of the Hidden Control

**User Story:** As a consumer using a screen reader, I want navigation controls to be consistently announced, so that hidden decorative duplicates do not confuse me.

#### Acceptance Criteria

1. WHEN the Page_Back_Link is hidden at a Phone_Viewport, IT SHALL be hidden from assistive technology as well as visually, so that it is not announced without being reachable
2. THE Page_Back_Link SHALL retain a keyboard-reachable focus state and a visible focus indicator at a Desktop_Viewport
3. THE Page_Back_Link SHALL NOT be reachable by keyboard tab order at a Phone_Viewport
4. Controls that remain visible at a Phone_Viewport SHALL retain a touch target of at least 44 by 44 CSS pixels

### Requirement 5: Back Button Component Renders Its Label Prop

**User Story:** As a pharmacy user, I want the back button to say where it goes, so that I can tell "Back to Dashboard" apart from a generic back action.

#### Acceptance Criteria

1. WHERE a `label` prop is supplied to the Back_Button_Component, THE component SHALL render that value as its visible caption
2. WHERE no `label` prop is supplied, THE component SHALL render the default caption "Back"
3. THE component SHALL continue to expose the label value as its accessible name
4. THE component SHALL NOT duplicate the accessible name, so the visible caption and the accessible name SHALL resolve to the same text
5. WHEN the component caption grows longer than the previous hardcoded caption, THE component SHALL NOT wrap or overflow its header container at a Desktop_Viewport

### Requirement 6: No Regression Outside the Declared Scope

**User Story:** As a developer, I want this change confined to the declared files, so that admin and pharmacy pages are unaffected except for the intended component label fix.

#### Acceptance Criteria

1. THE change SHALL NOT alter the visibility behaviour of back links in any `admin/*` view
2. THE change SHALL NOT alter the visibility behaviour of back links in any `pharmacy/*` view
3. THE change SHALL NOT alter dark mode colours, background colours, or typography of any affected page
4. WHEN the project assets are rebuilt, THE responsive utility classes used SHALL be present in the emitted stylesheet
5. THE existing automated test suite SHALL show no newly failing tests attributable to this change

## Out of Scope

- Hiding back links on `admin/*` views (14 views, hardcoded markup)
- Migrating the hardcoded consumer and admin back links to the shared Back_Button_Component
- Adding a mobile bottom navigation bar or a browser-history-based back control
- Dark mode remediation of the hardcoded `bg-[#f0f0ff]` page background on `consumer/pharmacy-details.blade.php` and `consumer/profile.blade.php`

## Open Questions

1. The `sm` breakpoint (640px) is proposed because the codebase already uses `sm:` for its existing mobile/desktop split in the messages views. `md` (768px) would also hide the link on small tablets and on phones held in landscape. Confirm `sm` is the intended boundary.
