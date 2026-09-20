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

## Resolved Decisions

### D1: The breakpoint boundary is `sm` (640px) — RESOLVED, implemented

The original open question asked whether `sm` (640px) or `md` (768px) should separate Phone_Viewport from Desktop_Viewport. **`sm` is confirmed.** Three findings settled it:

**1. This document already commits to `sm`.** The Glossary defines Phone_Viewport as below 640px and Desktop_Viewport as 640px and above, and eleven acceptance criteria are written in those terms: 1.1, 1.2, 2.1, 3.1, 3.2, 3.4, 4.1, 4.2, 4.3, 4.4, and 5.5. Adopting `md` would require rewriting the Glossary and every criterion that references either term, not just changing a utility class.

**2. `md` would open a 128px band with no back control in the messages view.** The entire messages split view is anchored at 640px, in CSS and in JavaScript:

| `consumer/messages.blade.php` | Utility / condition |
|---|---|
| `#conversationListPanel` | `w-full sm:w-96` |
| `#chatPanel` | `hidden sm:flex` |
| Panel_Back_Control | `sm:hidden` |
| `isMobileView()` | `matchMedia('(max-width: 639px)')` |

At 640–767px the view is already in desktop split mode and the Panel_Back_Control is hidden by `sm:hidden`. Under `md` the Page_Back_Link would still be hidden too, leaving that band with no back control in the header at all. Under `sm` the two controls hand off at exactly the same 640px threshold, with no gap.

**3. The stated rationale does not extend past 640px.** The Introduction justifies hiding the links because they "crowd the header" and "duplicate navigation" on phone-sized screens. At 700–767px the header is not crowded, so hiding there applies the fix where no problem exists.

**Accepted trade-off:** on older small phones in landscape — iPhone SE and iPhone 8 are 667px wide — the Page_Back_Link remains visible, where `md` would hide it. This is a redundant but harmless control on a viewport with room for it. Note that current phones in landscape (iPhone 14 at 852px) exceed both breakpoints and show the link either way, so this is not a general "phones in landscape" concern.

**Implemented as:** `hidden sm:flex` on `consumer/pharmacy-details.blade.php` (applied to the wrapper, per Requirement 2.2) and `consumer/profile.blade.php`; `hidden sm:inline` on `consumer/search.blade.php` and `consumer/messages.blade.php`. Guarded by `tests/Feature/ConsumerBackNavigationTest.php`.

## Verification Status

- Requirement 5 (component label) and Requirements 1–4, 6 are implemented and covered by `tests/Feature/ConsumerBackNavigationTest.php` and `tests/Feature/PharmacyBackNavigationTest.php`.
- Requirement 6.4 confirmed: `npm run build` emits `.hidden{display:none}`, `sm:flex`, `sm:inline`, and `sm:hidden` into the compiled stylesheet.
- Requirement 6.5 confirmed: full suite at 337 passed, 1 skipped, 0 failed. The one skip is `PostgreSqlMigrationRehearsalTest`, gated behind `MEDFIND_REHEARSAL_DISPOSABLE=1` and unrelated to this change.
- Requirement 4.4 (44×44 CSS pixel touch targets on controls that stay visible) was verified only from the emitted utilities, not from rendered geometry. The shared component carries `min-h-11` (2.75rem = 44px); the chat and panel controls were not measured in a browser.
