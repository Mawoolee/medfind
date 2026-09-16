# Bugfix Requirements Document

## Introduction

This document details the requirements for fixing 6 critical bugs affecting user experience across consumer and pharmacy interfaces in the MedFind application. The bugs impact:

1. **Dark Mode Routing Panel** - Unreadable routing directions in dark mode
2. **Popup Chat Message Visibility** - White-on-white text making messages invisible
3. **Popup Chat File Attachments** - Missing attachment UI and file display
4. **Chat Page Scroll Behavior** - Improper full-page scroll instead of contained scroll
5. **Pharmacy Real-time Notifications** - Messages require manual refresh
6. **Consumer Notification Coverage** - Missing notifications for pharmacy replies

These bugs severely impact usability, communication between consumers and pharmacies, and the overall user experience. The fixes will ensure proper styling across themes, restore missing functionality, and enable real-time communication as designed.

## Bug Analysis

### Current Behavior (Defect)

#### Bug 1: Leaflet Routing Panel Dark Mode

1.1 WHEN dark mode is enabled AND a user requests directions on the consumer dashboard map THEN the top half of the routing panel (.leaflet-routing-alt) displays with WHITE background and light gray text making it completely unreadable (white-on-white)

1.2 WHEN dark mode is enabled AND routing directions are shown THEN the bottom half of the panel has proper dark blue background but the top routing alternatives section has white background creating contrast failure

1.3 WHEN dark mode is enabled AND the routing panel is visible THEN the .leaflet-routing-alt section shows white background with white or very light gray text instead of blue background with white text

#### Bug 2: Popup Chat Message Visibility and Sizing

1.3 WHEN users open the popup chat window (#activeChatWindow) on consumer dashboard THEN messages display as tiny blue pill bubbles (showing single characters like "h", "d", "afaskf", "A")

1.4 WHEN messages are sent in the popup chat window THEN the message bubbles are too small with barely visible text content instead of full-sized readable bubbles

1.5 WHEN the popup chat modal (.chat-modal-fixed) is open THEN message bubbles should be blue with white text but are rendered at minimal size making content unreadable

1.6 WHEN comparing popup chat bubbles to full chat page bubbles THEN popup chat bubbles are significantly smaller and lack proper text sizing and padding

#### Bug 3: Popup Chat File Attachment Display

1.7 WHEN users open the popup chat window message input area THEN no paperclip/attachment icon is visible in the input area

1.8 WHEN users send IMAGE files through the popup chat THEN the conversation shows only generic text "Sent 1 file(s)" instead of displaying the actual image thumbnail

1.9 WHEN users send document files through the popup chat THEN the conversation shows only generic text "Sent 1 file(s)" instead of displaying the file name with icon

1.10 WHEN comparing file attachments in full chat page versus popup chat THEN the full chat page displays actual image previews and file names but popup chat only shows generic "Sent X file(s)" text

1.11 WHEN image files are sent successfully THEN the actual picture/image thumbnail is not visible in the active chat window conversation view

#### Bug 4: Chat Page Scroll Behavior

1.9 WHEN users access the full chat page (consumer/chat.blade.php or pharmacy/messages.blade.php) THEN the entire page including header/navbar scrolls vertically

1.12 WHEN the chat conversation grows beyond viewport height THEN users must scroll the entire page body instead of having a fixed layout with scrollable messages area

1.13 WHEN scrolling on the chat page THEN the header navigation disappears from view creating disorientation

#### Bug 5: Pharmacy Real-time Notifications

1.14 WHEN a consumer sends a message to a pharmacy THEN pharmacy users do not receive real-time notifications or updates

1.15 WHEN a pharmacy user is on any pharmacy page (dashboard, inventory, messages, etc.) THEN new consumer messages only appear after manual page refresh

1.16 WHEN window.Echo is checked on pharmacy pages THEN it exists but listeners are only active on the pharmacy messages page, not globally

1.17 WHEN MessageSent event broadcasts to channel 'pharmacy.{id}' THEN pharmacy users on other pages do not receive the broadcast

#### Bug 6: Consumer Notification Coverage

1.18 WHEN a pharmacy replies to a consumer's message THEN the consumer does not receive a notification

1.19 WHEN a consumer is away from the chat page THEN they have no indication that a pharmacy has responded

1.20 WHEN pharmacy status changes or other consumer-relevant events occur THEN consumers do not receive notifications for these events

### Expected Behavior (Correct)

#### Bug 1: Leaflet Routing Panel Dark Mode (Fixed)

2.1 WHEN dark mode is enabled AND a user requests directions on the consumer dashboard map THEN the top half of the routing panel (.leaflet-routing-alt) SHALL display with BLUE background (rgba(15, 15, 35, 0.97) or similar dark blue) and WHITE text providing readable contrast

2.2 WHEN dark mode is enabled AND routing directions are shown THEN the entire routing panel including alternatives section SHALL have consistent dark blue background with white/light text throughout

2.3 WHEN dark mode is enabled THEN the .leaflet-routing-alt section SHALL use blue background instead of white background ensuring all text is readable

2.4 WHEN dark mode is enabled THEN the routing panel border and UI elements SHALL use purple-tinted dark theme colors matching the application's design system

#### Bug 2: Popup Chat Message Visibility and Sizing (Fixed)

2.5 WHEN users open the popup chat window (#activeChatWindow) THEN consumer-sent messages SHALL display as full-sized blue bubbles with WHITE text clearly readable (not tiny pill bubbles)

2.6 WHEN users open the popup chat window THEN pharmacy-sent messages SHALL display as full-sized bubbles with proper text color and background ensuring readability

2.7 WHEN messages are sent in the popup chat THEN message bubbles SHALL have proper sizing, padding, and text size matching the full chat page implementation

2.8 WHEN the popup chat modal (.chat-modal-fixed) is open THEN all message bubbles SHALL be large enough to display full message content with proper typography (not single character pills)

2.9 WHEN dark mode is enabled in popup chat THEN message bubbles SHALL adjust colors appropriately while maintaining full readable size (consumer: white text on blue, pharmacy: appropriate contrast)

#### Bug 3: Popup Chat File Attachment Display (Fixed)

2.10 WHEN users open the popup chat window message input area THEN a paperclip icon SHALL be visible and clickable in the input area to attach files

2.11 WHEN users click the paperclip icon in popup chat THEN a file picker dialog SHALL open allowing selection of images and documents

2.12 WHEN users send IMAGE files through popup chat THEN the actual image SHALL display as a thumbnail in the conversation view (not just "Sent 1 file(s)" text)

2.13 WHEN users send document files through popup chat THEN the file name with appropriate icon SHALL display in the conversation view (not just "Sent 1 file(s)" text)

2.14 WHEN files are attached in popup chat THEN the attachment display SHALL work identically to the full chat page implementation showing actual previews and file names

2.15 WHEN image attachments are sent THEN the picture SHALL be visible in the active chat window conversation as a clickable thumbnail, not generic text

#### Bug 4: Chat Page Scroll Behavior (Fixed)

2.16 WHEN users access the full chat page THEN the page layout SHALL be fixed with the header/navbar remaining visible at the top

2.17 WHEN the chat conversation grows beyond viewport height THEN ONLY the messages container SHALL scroll vertically while header and input remain fixed

2.18 WHEN scrolling on the chat page THEN the header navigation SHALL remain visible and fixed at the top of the viewport

2.19 WHEN on mobile devices THEN the chat page SHALL use full viewport height with fixed header and input, scrollable messages area

#### Bug 5: Pharmacy Real-time Notifications (Fixed)

2.20 WHEN a consumer sends a message to a pharmacy THEN pharmacy users SHALL receive real-time notification updates immediately without refresh

2.21 WHEN a pharmacy user is on any pharmacy page THEN the notification bell icon SHALL update its unread count automatically when new messages arrive

2.22 WHEN window.Echo is initialized in app.blade.php layout THEN pharmacy users on ALL pages SHALL have active listeners for their pharmacy channel 'pharmacy.{id}'

2.23 WHEN MessageSent event broadcasts to channel 'pharmacy.{id}' THEN pharmacy users on any page SHALL receive the broadcast and update notification UI accordingly

2.24 WHEN a pharmacy user is logged in THEN a global Echo listener SHALL be registered in the app layout to handle notification updates across all pages

#### Bug 6: Consumer Notification Coverage (Fixed)

2.25 WHEN a pharmacy replies to a consumer's message THEN the consumer SHALL receive a notification through Laravel's notification system

2.26 WHEN a consumer is away from the chat page AND receives a pharmacy reply THEN the notification bell icon SHALL display an updated unread count

2.27 WHEN pharmacy status changes or inventory updates affect consumer interests THEN consumers SHALL receive appropriate notifications

2.28 WHEN a consumer clicks on a message notification THEN they SHALL be directed to the relevant chat conversation or page

### Unchanged Behavior (Regression Prevention)

#### General UI & Styling

3.1 WHEN light mode is active THEN all existing light mode styling SHALL CONTINUE TO work correctly without any visual regressions

3.2 WHEN dark mode is toggled on/off THEN all existing dark mode styling for other components SHALL CONTINUE TO function as designed

3.3 WHEN users interact with map markers, pharmacy popups, and search functionality THEN these features SHALL CONTINUE TO work without any changes

#### Full Chat Page Functionality

3.4 WHEN users send messages on the full chat page THEN message sending, delivery, and real-time updates SHALL CONTINUE TO work as currently implemented

3.5 WHEN users attach files on the full chat page THEN file upload, preview, and sending SHALL CONTINUE TO work without modification

3.6 WHEN users view chat history on the full chat page THEN message rendering, timestamps, and sender identification SHALL CONTINUE TO display correctly

#### Real-time Updates (Working Features)

3.7 WHEN inventory updates occur THEN consumer dashboard map markers SHALL CONTINUE TO update in real-time as currently implemented

3.8 WHEN consumers receive messages on the consumer messages page THEN real-time chat updates SHALL CONTINUE TO work as currently implemented

3.9 WHEN pharmacy users are on the pharmacy messages page THEN real-time consumer message updates SHALL CONTINUE TO work as currently implemented

#### Notification System (Existing)

3.10 WHEN pharmacy status changes THEN pharmacy owner notifications SHALL CONTINUE TO be sent as currently implemented

3.11 WHEN users click the notification bell THEN they SHALL CONTINUE TO be directed to the notifications index page

3.12 WHEN notifications are marked as read THEN the notification system SHALL CONTINUE TO function as designed

#### Chat Heads & Messenger Widget

3.13 WHEN consumers have active conversations THEN chat head bubbles SHALL CONTINUE TO appear and function on the consumer dashboard

3.14 WHEN users dismiss chat heads THEN the dismissal persistence SHALL CONTINUE TO work using localStorage

3.15 WHEN chat window is minimized/closed THEN the chat heads SHALL CONTINUE TO reappear as designed

#### Routing & Directions

3.16 WHEN users request directions on the map THEN route calculation, display, and step-by-step instructions SHALL CONTINUE TO function correctly

3.17 WHEN users toggle direction steps or clear routes THEN these controls SHALL CONTINUE TO work without changes

3.18 WHEN routing panel displays in light mode THEN it SHALL CONTINUE TO show with proper styling and readability
