# Implementation Plan: Leaflet to Google Maps Migration

## Overview

This implementation plan converts the approved design into actionable coding tasks following the six-stage incremental migration strategy. Each stage builds upon the previous one, with checkpoint tasks ensuring stability before proceeding. The plan follows the minimal-change approach: create `medfind-google.js` and `pharmacy-info-window.css`, then update `app.blade.php` to switch from Leaflet to Google Maps.

## Tasks

- [x] 1. Project Setup and Google Maps API Configuration
  - Set up Google Maps API key in `.env` file
  - Configure API restrictions in Google Cloud Console (HTTP referrers, required APIs only)
  - Verify API key has Maps JavaScript API, Places API, Directions API, and Geocoding API enabled
  - Test API key by loading a basic Google Maps page
  - _Requirements: 14.2, 14.3_

- [x] 2. Stage 1 - Basic Map and Markers Implementation
  - [x] 2.1 Create medfind-google.js file structure
    - Create `/public/js/medfind-google.js` with global variable declarations (map, markers, userLat, userLng, etc.)
    - Implement `calculateDistance()` function (reuse from medfind.js logic)
    - Define main initialization function `initializeMap()` with retry logic (5 attempts, 200ms delay)
    - _Requirements: 1.1, 1.6, 14.4_
  
  - [x] 2.2 Implement GoogleMapManager for map initialization
    - Create `GoogleMapManager` class with constructor accepting containerId and options
    - Implement `initialize()` method to create Google Maps instance with specified options (center, zoom, controls)
    - Handle geolocation: attempt to get user location, fall back to Legazpi City (13.1475, 123.7431) if denied
    - Set map options: minZoom 10, maxZoom 19, gestureHandling 'cooperative'
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_
  
  - [x] 2.3 Implement UserLocationMarker for user position display
    - Create `UserLocationMarker` class with methods: `setPosition()`, `show()`, `hide()`
    - Create custom marker using `google.maps.Marker` with custom SVG icon (blue pulsing dot)
    - Implement pulsing animation using CSS keyframes
    - Add tooltip "You are here" using marker title property
    - Set z-index higher than pharmacy markers (zIndex: 1000)
    - _Requirements: 3.1, 3.2, 3.3, 3.5_
  
  - [x] 2.4 Implement PharmacyMarkerManager for pharmacy markers
    - Create `PharmacyMarkerManager` class with constructor accepting map and pharmaciesData
    - Implement `createMarkers()` method to iterate pharmaciesData and create markers
    - Implement marker icon logic: if pharmacy has logo, use logo image; else use purple circle SVG icon
    - Calculate distance from user location for each pharmacy using `calculateDistance()`
    - Store markers in array for later filtering
    - _Requirements: 2.1, 2.2, 2.3, 2.4_
  
  - [ ]* 2.5 Write unit tests for Stage 1 components
    - Test `calculateDistance()` returns correct km for known coordinate pairs
    - Test `GoogleMapManager.initialize()` creates map with correct center and zoom
    - Test `UserLocationMarker` creates marker with correct position
    - Test `PharmacyMarkerManager.createMarkers()` creates correct number of markers
    - _Requirements: 1.1, 2.1, 3.1_

- [x] 3. Checkpoint - Stage 1 Verification
  - Load consumer dashboard page and verify map displays within 2 seconds
  - Verify all pharmacy markers appear at correct locations
  - Verify user location marker displays blue pulsing dot (if geolocation granted)
  - Verify zoom controls are functional
  - Check browser console for errors (should be none)
  - _Ensure all tests pass, ask the user if questions arise._

- [ ] 4. Stage 2 - Simple InfoWindow Implementation
  - [~] 4.1 Implement basic PharmacyInfoWindowManager
    - Create `PharmacyInfoWindowManager` class with constructor accepting map and pharmaciesData
    - Implement `open(pharmacy, marker)` method to display text-only InfoWindow
    - Create InfoWindow with pharmacy name and address only (plain text, no HTML)
    - Implement `close()` method to close current InfoWindow
    - Store reference to currently open InfoWindow to prevent multiple open windows
    - _Requirements: 4.1, 4.8, 4.9_
  
  - [~] 4.2 Add marker click event listeners
    - In `PharmacyMarkerManager.createMarkers()`, add click event listener to each marker
    - On marker click, call `PharmacyInfoWindowManager.open()` with pharmacy data and marker
    - Add map click listener to close InfoWindow when user clicks map background
    - _Requirements: 4.1, 4.9, 4.10_
  
  - [ ]* 4.3 Write integration tests for InfoWindow interaction
    - Test clicking marker opens InfoWindow
    - Test InfoWindow contains pharmacy name and address
    - Test clicking another marker closes previous InfoWindow and opens new one
    - Test clicking map background closes InfoWindow
    - _Requirements: 4.1, 4.9, 4.10_

- [~] 5. Checkpoint - Stage 2 Verification
  - Click any pharmacy marker and verify InfoWindow opens
  - Verify InfoWindow displays pharmacy name and address
  - Click another marker and verify previous InfoWindow closes
  - Click map background and verify InfoWindow closes
  - _Ensure all tests pass, ask the user if questions arise._

- [ ] 6. Stage 3 - Custom HTML InfoWindow with Styling
  - [~] 6.1 Create pharmacy-info-window.css file
    - Create `/public/css/pharmacy-info-window.css`
    - Define styles for `.pharmacy-info-window` container (width: 320px, border-radius: 12px, shadow)
    - Style `.piw-header` with logo/icon and pharmacy name section
    - Style `.piw-logo` (48x48px circular) and initial circle fallback
    - Style `.piw-details` section for address, contact, hours (icons + text)
    - Use MedFind color scheme: Navy #191970, Purple #9400D3, Lime #D9F855
    - Define typography: 11-15px font sizes, Inter font family
    - _Requirements: 5.1, 5.2, 5.4, 5.5_
  
  - [~] 6.2 Implement full HTML InfoWindow template
    - Update `PharmacyInfoWindowManager.open()` to generate custom HTML instead of plain text
    - Create HTML structure: header (logo + name + distance), details section (address, contact, hours)
    - Implement logo rendering: if pharmacy.logo exists, use <img>, else create colored initial circle
    - Display distance in km with one decimal place using `calculateDistance()`
    - Display contact number and hours if available, hide if null
    - _Requirements: 4.2, 4.3, 4.4, 5.1, 5.6, 5.7_
  
  - [~] 6.3 Implement HTML escaping for XSS prevention
    - Create `escapeHTML()` utility function to sanitize user-generated content
    - Escape pharmacy name, address, contact, hours before injecting into InfoWindow HTML
    - _Requirements: Security considerations from design_
  
  - [ ]* 6.4 Write unit tests for InfoWindow HTML generation
    - Test InfoWindow HTML contains escaped pharmacy name and address
    - Test logo <img> tag generated when pharmacy.logo is present
    - Test initial circle generated when pharmacy.logo is null
    - Test distance displayed correctly (X.X km format)
    - Test contact and hours hidden when data is null
    - _Requirements: 4.2, 4.3, 4.4, 5.1_

- [ ] 7. Stage 3 - Link CSS in app.blade.php
  - [~] 7.1 Add pharmacy-info-window.css link to app.blade.php
    - Open `/resources/views/layouts/app.blade.php`
    - Add `<link rel="stylesheet" href="{{ asset('css/pharmacy-info-window.css') }}">` after medfind.css link
    - _Requirements: 5.5_

- [~] 8. Checkpoint - Stage 3 Verification
  - Click pharmacy marker and verify InfoWindow displays custom styled HTML
  - Verify logo displays correctly (or colored initial circle if no logo)
  - Verify address, contact, hours display correctly with icons
  - Verify distance shows correct km value with one decimal place
  - Verify styling matches MedFind branding (Navy, Purple, Lime colors)
  - _Ensure all tests pass, ask the user if questions arise._

- [ ] 9. Stage 4 - Medicines and Action Buttons
  - [~] 9.1 Add medicine list rendering to InfoWindow
    - Update `PharmacyInfoWindowManager.open()` HTML template to include medicines section
    - Filter pharmacy.medicines array to only in-stock items (stock > 0)
    - Sort filtered medicines by price ascending, select top 3
    - Render medicine items with name and price formatted as ₱XXX.XX
    - Display "No stock available" message if medicines array is empty
    - _Requirements: 4.5, 4.6, 4.7_
  
  - [~] 9.2 Implement action buttons in InfoWindow
    - Add action buttons section to InfoWindow HTML: "View Products", "Message", "Directions"
    - Style buttons with rounded corners, brand colors, icons
    - Implement `viewPharmacy(id)` function to navigate to pharmacy detail page
    - Implement `openChat(id)` function to open existing chat interface
    - Add placeholder for `getDirections(lat, lng)` function (implemented in Stage 5)
    - _Requirements: 6.1, 6.2, 6.4, 6.5_
  
  - [~] 9.3 Implement alternative pharmacy suggestions for search
    - Update `PharmacyInfoWindowManager.open()` to check if search is active and current pharmacy lacks searched medicine
    - If true, find up to 3 alternative pharmacies with the searched medicine in stock
    - Sort alternatives by distance from user location
    - Render alternatives section in InfoWindow with pharmacy names (clickable links), prices, distances
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_
  
  - [ ]* 9.4 Write unit tests for medicine display and alternative suggestions
    - Test top 3 medicines displayed with correct names and prices
    - Test medicines sorted by price ascending
    - Test "No stock available" message when medicines array is empty
    - Test alternative pharmacies shown only when search active and current pharmacy lacks medicine
    - Test alternatives sorted by distance
    - _Requirements: 4.5, 4.6, 4.7, 8.1, 8.3_

- [~] 10. Checkpoint - Stage 4 Verification
  - Click pharmacy marker and verify top 3 medicines display with prices formatted as ₱XXX.XX
  - Click "View Products" button and verify navigation to pharmacy detail page
  - Click "Message" button and verify chat interface opens
  - Perform medicine search, click pharmacy without that medicine, verify alternative pharmacies section appears
  - Verify alternatives are sorted by distance
  - _Ensure all tests pass, ask the user if questions arise._

- [ ] 11. Stage 5 - Routing and Directions Implementation
  - [~] 11.1 Implement DirectionsService class for route calculation
    - Create `DirectionsService` class with constructor accepting map and userLocation
    - Implement `calculateRoute(destination)` method using `google.maps.DirectionsService`
    - Set route options: travelMode DRIVING, provideRouteAlternatives true
    - Store `google.maps.DirectionsRenderer` instance for displaying routes
    - _Requirements: 9.1, 9.9_
  
  - [~] 11.2 Implement route display and visualization
    - Implement `displayRoute(result)` method in DirectionsService
    - Configure DirectionsRenderer: primary route purple (#9400D3), 5px width
    - Display origin marker (user location) and destination marker (pharmacy)
    - Fit map bounds to show entire route using `map.fitBounds()`
    - _Requirements: 9.2, 9.5, 9.6_
  
  - [~] 11.3 Implement route info bar display
    - Create function to generate route summary HTML (distance, estimated time)
    - Update existing `#routeInfoBar` element with route summary
    - Display "Clear Route" button in route info bar
    - Style route info bar to match existing design (fixed bottom position, white background)
    - _Requirements: 9.3, 9.4, 9.7_
  
  - [~] 11.4 Implement getDirections() function and route clearing
    - Create global `window.getDirections(lat, lng)` function
    - Call `DirectionsService.calculateRoute()` with destination coordinates
    - On success, display route and show route info bar
    - Implement `window.clearRoute()` function to remove route display and hide route info bar
    - Update "Directions" button onclick handler to call `getDirections()`
    - _Requirements: 6.3, 9.1, 9.7, 9.8_
  
  - [~] 11.5 Implement turn-by-turn directions toggle
    - Implement `window.toggleDirections()` function to show/hide detailed steps
    - Use DirectionsRenderer's built-in panel option to display steps
    - Add toggle button to route info bar ("View steps" / "Hide steps")
    - Animate chevron icon rotation on toggle
    - _Requirements: 9.10_
  
  - [ ]* 11.6 Write integration tests for routing functionality
    - Test clicking "Directions" button displays route on map
    - Test route info bar appears with distance and time
    - Test route fits within visible map bounds
    - Test "Clear Route" button removes route and hides info bar
    - Test turn-by-turn steps display when toggled
    - _Requirements: 9.1, 9.2, 9.6, 9.8, 9.10_

- [~] 12. Checkpoint - Stage 5 Verification
  - Click "Directions" button in InfoWindow and verify route displays on map
  - Verify route info bar appears at bottom with distance and estimated time
  - Verify route is visible within map bounds (auto-fitted)
  - Click "Clear Route" button and verify route removes and map restores default view
  - Click "View steps" button and verify turn-by-turn directions appear
  - _Ensure all tests pass, ask the user if questions arise._

- [ ] 13. Stage 6 - Location Editor Implementation
  - [~] 13.1 Implement PharmacyLocationEditor class
    - Create `PharmacyLocationEditor` class with constructor accepting containerId, initialLat, initialLng
    - Implement `initialize()` method to create map instance for location editing
    - Set center to initialLat/initialLng if provided, else Manila (14.5995, 120.9842)
    - Set zoom to 15 if existing location, else 12 for new location
    - _Requirements: 11.1, 11.2, 11.3_
  
  - [~] 13.2 Implement draggable marker for location editor
    - Create draggable marker on map at initial position
    - Implement `setMarkerPosition(lat, lng)` method
    - Add drag event listener to marker
    - On drag end, update latitude/longitude form fields with 6 decimal precision
    - Add map click listener to move marker to clicked position
    - _Requirements: 11.4, 11.5, 11.10_
  
  - [~] 13.3 Implement geocoding and reverse geocoding
    - Implement `geocodeAddress(address)` method using `google.maps.Geocoder`
    - On successful geocoding, move marker to resulting coordinates
    - Implement `reverseGeocode(lat, lng)` method
    - On successful reverse geocoding, populate address form field
    - Prioritize results within Philippines (componentRestrictions: { country: 'ph' })
    - _Requirements: 11.9, 12.1, 12.2, 12.3_
  
  - [~] 13.4 Implement address autocomplete search
    - Create address search input with `google.maps.places.Autocomplete`
    - Configure autocomplete: restrict to Philippines, return geometry and formatted_address
    - Add place_changed event listener
    - When place selected, move marker to place.geometry.location and update form fields
    - Display up to 5 autocomplete suggestions
    - _Requirements: 11.7, 11.8, 12.4_
  
  - [~] 13.5 Implement "Use my current location" button
    - Create button to trigger geolocation
    - On button click, request user's current position using `navigator.geolocation`
    - On success, move marker to user's coordinates and update form fields
    - On error, display alert "Location not available"
    - _Requirements: 11.6_
  
  - [ ]* 13.6 Write integration tests for location editor
    - Test location editor map initializes with draggable marker
    - Test dragging marker updates latitude/longitude form fields
    - Test clicking map moves marker to clicked position
    - Test address search autocomplete provides suggestions
    - Test selecting autocomplete suggestion moves marker and updates fields
    - Test "Use my location" button sets marker to user coordinates
    - _Requirements: 11.1, 11.4, 11.5, 11.7, 11.8_

- [~] 14. Checkpoint - Stage 6 Verification
  - Load pharmacy location editor page and verify map displays with draggable marker
  - Drag marker and verify latitude/longitude fields update with 6 decimal places
  - Click map and verify marker moves to clicked position
  - Type address in search input and verify autocomplete suggestions appear
  - Select autocomplete suggestion and verify marker moves to that location
  - Click "Use my location" button and verify marker moves to user coordinates
  - _Ensure all tests pass, ask the user if questions arise._

- [ ] 15. Search and Filter Implementation
  - [~] 15.1 Implement SearchFilterService class
    - Create `SearchFilterService` class with constructor accepting pharmaciesData and markerManager
    - Implement `performSearch(query)` method to filter pharmaciesData by medicine name and stock
    - Implement `clearSearch()` method to reset filters and show all markers
    - Update pharmacy count and medicine stock count badges during search
    - _Requirements: 7.1, 7.2, 7.3, 7.5_
  
  - [~] 15.2 Connect search UI to SearchFilterService
    - Add event listener to search button (#searchBtn) to call `performSearch()`
    - Add event listener to medicine search input (#medicineSearch) for Enter key
    - Update `PharmacyMarkerManager.filterMarkers(filteredPharmacies)` method to show/hide markers
    - Update badges: "#searchResultBadge", "#pharmacyCount", "#medicineStockCount"
    - _Requirements: 7.1, 7.2, 7.3_
  
  - [~] 15.3 Implement "No results" handling
    - When search returns empty array, display temporary "No results" popup on map
    - Update badge to "No results for [Query]"
    - Hide all markers
    - Auto-dismiss popup after 3 seconds
    - _Requirements: 7.4_
  
  - [ ]* 15.4 Write unit tests for search filtering
    - Test `performSearch()` filters pharmacies correctly by medicine name
    - Test `performSearch()` only includes pharmacies with stock > 0
    - Test `clearSearch()` resets to show all pharmacies
    - Test badge updates reflect correct counts
    - _Requirements: 7.1, 7.2, 7.3_

- [ ] 16. Nearest Pharmacy Suggestion Panel Implementation
  - [~] 16.1 Implement SuggestionPanelManager class
    - Create `SuggestionPanelManager` class with constructor accepting map and pharmaciesData
    - Implement `show(filteredPharmacies, query)` method to display top 3 nearest pharmacies
    - Sort filtered pharmacies by distance from user location (ascending)
    - Generate HTML for suggestion panel with pharmacy names, distances, prices, stock
    - Implement `hide()` method to dismiss panel
    - _Requirements: 10.1, 10.2, 10.4, 10.6_
  
  - [~] 16.2 Add action buttons to suggestion panel items
    - Add "Go" button for each pharmacy (calls `getDirections(lat, lng)`)
    - Add "View" button for each pharmacy (navigates to pharmacy detail page)
    - Style buttons to match MedFind design
    - _Requirements: 10.3_
  
  - [~] 16.3 Position suggestion panel to avoid obstructing map controls
    - Position panel at top-right corner below search bar
    - Ensure panel doesn't overlap zoom controls or search input
    - Make panel dismissible via close button (×)
    - _Requirements: 10.5_
  
  - [ ]* 16.4 Write integration tests for suggestion panel
    - Test suggestion panel displays after search with results
    - Test panel shows top 3 nearest pharmacies sorted by distance
    - Test "Go" button triggers directions
    - Test "View" button navigates to pharmacy page
    - Test close button hides panel
    - _Requirements: 10.1, 10.2, 10.3, 10.4_

- [ ] 17. Autocomplete Integration
  - [~] 17.1 Implement medicine name autocomplete
    - Reuse existing `allMedicineNames` global array from blade template
    - Implement autocomplete dropdown display when user types in #medicineSearch
    - Filter `allMedicineNames` by query string (case-insensitive substring match)
    - Display filtered suggestions in #autocompleteList element
    - Handle up/down arrow key navigation and Enter key selection
    - _Requirements: 18.5_
  
  - [~] 17.2 Connect autocomplete selection to search
    - When user clicks autocomplete suggestion, populate #medicineSearch input
    - Automatically trigger `performSearch()` on autocomplete selection
    - Hide autocomplete dropdown after selection
    - _Requirements: 7.1, 18.5_

- [ ] 18. Error Handling Implementation
  - [~] 18.1 Implement Google Maps API loading error handling
    - Add error message display function for when Google Maps fails to load after 5 retries
    - Display user-friendly modal/banner: "Map failed to load. Please refresh the page."
    - Log technical error details to console for debugging
    - Do NOT expose API key in error messages
    - _Requirements: 15.1, 15.6, 14.5_
  
  - [~] 18.2 Implement geolocation error handling
    - Handle geolocation permission denied gracefully
    - Fall back to Legazpi City default center when geolocation fails
    - Do NOT display user location marker if permission denied
    - Log geolocation error to console
    - _Requirements: 15.2_
  
  - [~] 18.3 Implement geocoding error handling
    - Handle "ZERO_RESULTS" status from geocoding API
    - Display alert "Address not found. Please try a different search."
    - Handle generic geocoding errors with alert "Could not find location. Please try again."
    - Log error status to console
    - _Requirements: 15.3, 12.5_
  
  - [~] 18.4 Implement routing error handling
    - Handle "ZERO_RESULTS" status from directions API
    - Display alert "No route found to this pharmacy."
    - Handle generic routing errors with alert "Could not calculate route. Please try again."
    - Log error status to console
    - _Requirements: 15.4_

- [ ] 19. Final Integration and Script Replacement
  - [~] 19.1 Add Google Maps script tag to app.blade.php
    - Open `/resources/views/layouts/app.blade.php`
    - Add global callback function definition before closing </body> tag
    - Add Google Maps API script tag with callback parameter
    - Use `{{ env('GOOGLE_MAPS_API_KEY') }}` for API key
    - Include `&libraries=places` parameter for Places API
    - _Requirements: 14.1, 14.6, 14.7_
  
  - [~] 19.2 Replace medfind.js with medfind-google.js in app.blade.php
    - Change script src from `{{ asset('js/medfind.js') }}` to `{{ asset('js/medfind-google.js') }}`
    - Verify script loads after Alpine.js but before Google Maps API script
    - _Requirements: 13.10_
  
  - [ ]* 19.3 Write end-to-end integration tests
    - Test full user flow: page load → map displays → search medicine → view InfoWindow → get directions
    - Test mobile viewport interactions (touch events, responsive layout)
    - Test geolocation allowed vs denied scenarios
    - Test network error recovery (offline → online)
    - _Requirements: 1.1, 7.1, 4.1, 9.1_

- [~] 20. Final Checkpoint - Full System Verification
  - Load consumer dashboard and verify map initializes within 2 seconds
  - Verify all pharmacy markers display at correct locations
  - Search for a medicine and verify filtering works correctly
  - Click marker, verify custom InfoWindow with medicines and action buttons
  - Click "Directions" button and verify route displays
  - Clear route and verify map restores to default state
  - Test location editor on pharmacy profile page
  - Verify all console errors are resolved
  - Test across Chrome, Firefox, Safari, Edge browsers
  - Test on desktop, tablet, and mobile viewports
  - _Ensure all tests pass, ask the user if questions arise._

- [ ]* 21. Performance Testing and Optimization
  - Measure map initialization time (target: < 2 seconds)
  - Measure marker rendering time for 100 pharmacies (target: < 1 second)
  - Measure InfoWindow open time (target: < 200ms)
  - Test with throttled network (Fast 3G) to verify acceptable performance
  - Test with 100+ pharmacies to identify performance bottlenecks
  - _Requirements: 16.1, 16.2, 16.3, 16.5_

- [ ]* 22. Cross-Browser and Accessibility Testing
  - Test in Chrome (desktop and mobile)
  - Test in Firefox (desktop)
  - Test in Safari (desktop and iOS)
  - Test in Edge (desktop)
  - Test keyboard navigation where feasible (tab through controls)
  - Test with screen reader (announce marker labels)
  - Verify touch targets are minimum 44x44px on mobile
  - _Requirements: 17.1, 17.2, 17.3, 17.5_

## Notes

- Tasks marked with `*` are optional testing tasks and can be skipped for faster MVP deployment
- Each checkpoint task includes verification criteria to ensure stage completion before proceeding
- All tasks reference specific requirements from requirements.md for traceability
- The migration follows a strict stage-gating approach: do not proceed to next stage until checkpoint passes
- Rollback strategy: revert app.blade.php script tag to `medfind.js` if critical issues arise
- Legacy Leaflet files (`medfind.js`, Leaflet CSS) remain intact throughout migration for easy rollback
- All new code goes into `medfind-google.js` and `pharmacy-info-window.css` - no modifications to existing JS files

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1"] },
    { "id": 1, "tasks": ["2.1", "2.2"] },
    { "id": 2, "tasks": ["2.3", "2.4"] },
    { "id": 3, "tasks": ["2.5"] },
    { "id": 4, "tasks": ["4.1"] },
    { "id": 5, "tasks": ["4.2", "4.3"] },
    { "id": 6, "tasks": ["6.1", "6.2", "6.3"] },
    { "id": 7, "tasks": ["6.4", "7.1"] },
    { "id": 8, "tasks": ["9.1"] },
    { "id": 9, "tasks": ["9.2", "9.3"] },
    { "id": 10, "tasks": ["9.4"] },
    { "id": 11, "tasks": ["11.1"] },
    { "id": 12, "tasks": ["11.2", "11.3"] },
    { "id": 13, "tasks": ["11.4", "11.5"] },
    { "id": 14, "tasks": ["11.6"] },
    { "id": 15, "tasks": ["13.1"] },
    { "id": 16, "tasks": ["13.2", "13.3"] },
    { "id": 17, "tasks": ["13.4", "13.5"] },
    { "id": 18, "tasks": ["13.6"] },
    { "id": 19, "tasks": ["15.1"] },
    { "id": 20, "tasks": ["15.2", "15.3"] },
    { "id": 21, "tasks": ["15.4"] },
    { "id": 22, "tasks": ["16.1"] },
    { "id": 23, "tasks": ["16.2", "16.3"] },
    { "id": 24, "tasks": ["16.4"] },
    { "id": 25, "tasks": ["17.1"] },
    { "id": 26, "tasks": ["17.2"] },
    { "id": 27, "tasks": ["18.1", "18.2", "18.3", "18.4"] },
    { "id": 28, "tasks": ["19.1"] },
    { "id": 29, "tasks": ["19.2"] },
    { "id": 30, "tasks": ["19.3"] },
    { "id": 31, "tasks": ["21", "22"] }
  ]
}
```
