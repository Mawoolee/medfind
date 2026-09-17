# Requirements Document

## Introduction

This document specifies the requirements for migrating the MedFind pharmacy locator application from Leaflet 1.9.4 to Google Maps JavaScript API. The migration must maintain all existing functionality while enabling enhanced custom information windows with pharmacy branding, medicine pricing, and action buttons. The system must follow an incremental development approach to ensure stability at each stage.

## Glossary

- **Map_Component**: The Google Maps JavaScript API map display component that shows pharmacy locations
- **Pharmacy_Marker**: A visual indicator on the map representing a pharmacy location
- **Info_Window**: A popup display component that shows detailed pharmacy information when a marker is interacted with
- **User_Location_Marker**: A visual indicator showing the user's current geographic position on the map
- **Routing_Display**: Visual representation of directions from user location to a selected pharmacy
- **Geocoding_Service**: Service that converts addresses to geographic coordinates and vice versa
- **Location_Editor**: Interface component allowing pharmacy staff to set their business location
- **Legacy_Implementation**: The current Leaflet-based map system to be replaced
- **Migration_Stage**: A discrete, testable phase in the incremental migration process

## Requirements

### Requirement 1: Map Display and Initialization

**User Story:** As a consumer, I want to see an interactive map showing pharmacy locations, so that I can visually identify pharmacies near me.

#### Acceptance Criteria

1. WHEN the consumer dashboard page loads, THE Map_Component SHALL initialize and display within 2 seconds
2. THE Map_Component SHALL center on the user's current location when geolocation permission is granted
3. IF geolocation permission is denied, THEN THE Map_Component SHALL center on Legazpi City (13.1475, 123.7431)
4. THE Map_Component SHALL display street-level map tiles with zoom controls
5. THE Map_Component SHALL support zoom levels from 10 to 19
6. WHEN the map loads through tunnels or proxies, THE Map_Component SHALL render correctly without manual refresh

### Requirement 2: Pharmacy Marker Display

**User Story:** As a consumer, I want to see visual markers for each pharmacy, so that I can identify pharmacy locations at a glance.

#### Acceptance Criteria

1. WHEN pharmacies exist in the dataset, THE Map_Component SHALL display a Pharmacy_Marker for each pharmacy
2. WHERE a pharmacy has a logo image, THE Pharmacy_Marker SHALL display the logo in a circular frame with a colored border
3. WHERE a pharmacy has no logo image, THE Pharmacy_Marker SHALL display a generic pharmacy icon in a circular frame
4. THE Pharmacy_Marker SHALL be clearly distinguishable from the User_Location_Marker
5. WHEN a medicine search is active, THE Map_Component SHALL display only Pharmacy_Markers for pharmacies with matching inventory
6. WHEN no search is active, THE Map_Component SHALL display all Pharmacy_Markers

### Requirement 3: User Location Display

**User Story:** As a consumer, I want to see my current location on the map, so that I can understand my position relative to nearby pharmacies.

#### Acceptance Criteria

1. WHEN geolocation permission is granted, THE Map_Component SHALL display a User_Location_Marker at the user's coordinates
2. THE User_Location_Marker SHALL be visually distinct with a pulsing blue dot animation
3. THE User_Location_Marker SHALL remain visible at higher z-index than Pharmacy_Markers
4. WHEN the user's location updates, THE User_Location_Marker SHALL update its position automatically
5. THE User_Location_Marker SHALL display a tooltip reading "You are here"

### Requirement 4: Information Window Display

**User Story:** As a consumer, I want to see detailed pharmacy information when I click a marker, so that I can make informed decisions about which pharmacy to visit.

#### Acceptance Criteria

1. WHEN a Pharmacy_Marker is clicked, THE Map_Component SHALL display an Info_Window anchored to that marker
2. THE Info_Window SHALL display the pharmacy name, address, and distance from user location
3. THE Info_Window SHALL display contact information when available
4. THE Info_Window SHALL display operating hours when available
5. THE Info_Window SHALL display the top 3 in-stock medicines with prices
6. WHERE fewer than 3 medicines are in stock, THE Info_Window SHALL display all available medicines
7. WHERE no medicines are in stock, THE Info_Window SHALL display a "No stock available" message
8. THE Info_Window SHALL include a close button that dismisses the window when clicked
9. WHEN a different Pharmacy_Marker is clicked, THE Map_Component SHALL close any open Info_Window and open the new one
10. WHEN the user clicks the map background, THE Map_Component SHALL close any open Info_Window

### Requirement 5: Custom Information Window Styling

**User Story:** As a consumer, I want pharmacy information to be presented in an attractive, branded format, so that I can easily read and understand the details.

#### Acceptance Criteria

1. THE Info_Window SHALL display pharmacy logo or colored initial circle at 48x48 pixels
2. THE Info_Window SHALL use the MedFind color scheme (Navy #191970, Purple #9400D3, Lime #D9F855)
3. THE Info_Window SHALL display medicine names and prices in a structured list format
4. THE Info_Window SHALL use consistent typography with 11-15px font sizes for readability
5. THE Info_Window SHALL have rounded corners and subtle shadows matching the application design language
6. THE Info_Window SHALL display distance in kilometers with one decimal place
7. THE Info_Window SHALL maintain a fixed width of 320px for consistent layout

### Requirement 6: Information Window Action Buttons

**User Story:** As a consumer, I want quick access to common actions from the information window, so that I can efficiently interact with pharmacies.

#### Acceptance Criteria

1. THE Info_Window SHALL display a "View Products" button that navigates to the pharmacy detail page
2. THE Info_Window SHALL display a "Message" button that opens the pharmacy messaging interface
3. THE Info_Window SHALL display a "Directions" button that displays routing from user location to the pharmacy
4. THE Info_Window action buttons SHALL be clearly labeled and visually distinct
5. WHEN an action button is clicked, THE Info_Window SHALL execute the corresponding action without page reload
6. THE Info_Window buttons SHALL use consistent styling with rounded corners and brand colors

### Requirement 7: Medicine Search and Filtering

**User Story:** As a consumer, I want to search for specific medicines and see only pharmacies that have them in stock, so that I can find where my needed medications are available.

#### Acceptance Criteria

1. WHEN a user enters a medicine name and performs a search, THE Map_Component SHALL display only pharmacies with that medicine in stock
2. WHEN a search is active, THE Map_Component SHALL update the pharmacy count badge with the number of matching pharmacies
3. WHEN a search is active, THE Map_Component SHALL update the stock count badge with total matching inventory
4. WHEN search results are empty, THE Map_Component SHALL display a temporary "No results" popup
5. WHEN a user clears the search, THE Map_Component SHALL restore all pharmacy markers
6. THE Map_Component SHALL maintain search state while users interact with markers and info windows

### Requirement 8: Alternative Pharmacy Suggestions

**User Story:** As a consumer, I want to be notified when a pharmacy doesn't have my searched medicine but others do, so that I can explore alternative options.

#### Acceptance Criteria

1. WHEN a search is active AND a clicked pharmacy lacks the searched medicine, THE Info_Window SHALL display an "Also available at" section
2. THE Info_Window SHALL list up to 3 alternative pharmacies with the searched medicine in stock
3. THE Info_Window SHALL display alternative pharmacy names, prices, and distances
4. THE Info_Window SHALL sort alternative pharmacies by distance from user location
5. THE Info_Window alternative pharmacy names SHALL be clickable links to their detail pages

### Requirement 9: Routing and Directions

**User Story:** As a consumer, I want to see turn-by-turn directions to a pharmacy, so that I can navigate there easily.

#### Acceptance Criteria

1. WHEN the "Directions" button is clicked, THE Map_Component SHALL display a Routing_Display from user location to the pharmacy
2. THE Routing_Display SHALL show the route path as a colored line on the map
3. THE Routing_Display SHALL display route distance in kilometers
4. THE Routing_Display SHALL display estimated travel time in minutes
5. THE Routing_Display SHALL display origin and destination markers distinct from standard markers
6. THE Routing_Display SHALL fit the entire route within the visible map bounds
7. WHEN a route is active, THE Map_Component SHALL display a "Clear Route" button
8. WHEN "Clear Route" is clicked, THE Map_Component SHALL remove the route display and restore the default view
9. THE Routing_Display SHALL support displaying alternative routes when available
10. THE Routing_Display SHALL allow users to view detailed turn-by-turn steps via a toggle control

### Requirement 10: Nearest Pharmacy Suggestion Panel

**User Story:** As a consumer searching for a medicine, I want to see the nearest pharmacies with that medicine prominently displayed, so that I can quickly identify my best options.

#### Acceptance Criteria

1. WHEN a medicine search returns results, THE Map_Component SHALL display a suggestion panel showing the 3 nearest pharmacies
2. THE suggestion panel SHALL display pharmacy names, distances, prices, and stock quantities
3. THE suggestion panel SHALL include "Go" and "View" buttons for each pharmacy
4. THE suggestion panel SHALL be dismissible via a close button
5. THE suggestion panel SHALL be positioned to not obscure map controls
6. WHEN no search is active, THE suggestion panel SHALL be hidden

### Requirement 11: Location Editor Map

**User Story:** As a pharmacy owner, I want to set my pharmacy's location on an interactive map, so that consumers can find my business accurately.

#### Acceptance Criteria

1. THE Location_Editor SHALL display a Map_Component with a single draggable marker
2. WHEN the pharmacy has an existing location, THE Location_Editor SHALL center on that location at zoom 15
3. WHERE no location exists, THE Location_Editor SHALL center on Manila (14.5995, 120.9842) at zoom 12
4. WHEN the user drags the marker, THE Location_Editor SHALL update latitude and longitude form fields
5. WHEN the user clicks the map, THE Location_Editor SHALL move the marker to the clicked position
6. THE Location_Editor SHALL provide a "Use my current location" button that sets the marker to the user's geolocation
7. THE Location_Editor SHALL provide an address search input with autocomplete suggestions
8. WHEN a user selects an address suggestion, THE Location_Editor SHALL move the marker to that location
9. THE Location_Editor SHALL perform reverse geocoding when the marker is moved to populate the address field
10. THE Location_Editor SHALL display latitude and longitude with 6 decimal places of precision

### Requirement 12: Geocoding and Address Services

**User Story:** As a pharmacy owner or consumer, I want the system to convert between addresses and coordinates accurately, so that location data is meaningful and precise.

#### Acceptance Criteria

1. THE Geocoding_Service SHALL convert address strings to latitude/longitude coordinates
2. THE Geocoding_Service SHALL convert latitude/longitude coordinates to readable addresses
3. THE Geocoding_Service SHALL prioritize results within the Philippines
4. THE Geocoding_Service SHALL return up to 5 address suggestions for search queries
5. WHEN geocoding fails, THE Geocoding_Service SHALL handle the error gracefully without breaking the interface
6. THE Geocoding_Service SHALL complete requests within 3 seconds under normal network conditions

### Requirement 13: Incremental Migration Strategy

**User Story:** As a developer, I want to migrate the system incrementally, so that each stage can be tested independently and risks are minimized.

#### Acceptance Criteria

1. THE migration SHALL proceed through distinct Migration_Stages with defined completion criteria
2. THE first Migration_Stage SHALL implement basic map initialization and marker display only
3. THE second Migration_Stage SHALL add simple text-only info windows
4. THE third Migration_Stage SHALL implement custom HTML info windows with styling
5. THE fourth Migration_Stage SHALL add action buttons and interactive features
6. THE fifth Migration_Stage SHALL implement routing and directions
7. THE sixth Migration_Stage SHALL implement the location editor
8. WHEN a Migration_Stage is incomplete, THE Legacy_Implementation SHALL remain functional and accessible
9. WHEN all Migration_Stages are complete and tested, THE Legacy_Implementation SHALL be removed
10. THE migration SHALL create new implementation files without modifying Legacy_Implementation files until final replacement

### Requirement 14: Script Loading and Dependencies

**User Story:** As a developer, I want Google Maps scripts to load reliably, so that the map functions correctly across different network conditions.

#### Acceptance Criteria

1. THE Google Maps JavaScript API script SHALL load at the bottom of the HTML body
2. THE Google Maps API SHALL use a valid API key specific to the MedFind application
3. THE Map_Component initialization SHALL wait for the Google Maps API to be fully loaded
4. THE Map_Component initialization SHALL retry up to 5 times with 200ms delays if the API is not ready
5. IF the Google Maps API fails to load after retries, THE Map_Component SHALL display an error message instructing users to refresh
6. THE implementation SHALL NOT use async or defer attributes on script tags
7. THE implementation SHALL define the global callback function before loading the Google Maps script

### Requirement 15: Error Handling and Graceful Degradation

**User Story:** As a consumer, I want the map to handle errors gracefully, so that temporary issues don't prevent me from accessing pharmacy information.

#### Acceptance Criteria

1. WHEN the Google Maps API fails to load, THE Map_Component SHALL display a user-friendly error message
2. WHEN geolocation is denied, THE Map_Component SHALL continue functioning with a default center location
3. WHEN geocoding requests fail, THE Map_Component SHALL continue functioning without address data
4. WHEN routing requests fail, THE Map_Component SHALL display an error message and allow continued map interaction
5. WHEN no pharmacies are found, THE Map_Component SHALL display an appropriate "No results" state
6. THE Map_Component SHALL log errors to the browser console for debugging purposes
7. THE Map_Component SHALL NOT expose API keys or sensitive configuration in error messages

### Requirement 16: Performance and Responsiveness

**User Story:** As a consumer, I want the map to load quickly and respond smoothly to interactions, so that I can find pharmacies efficiently.

#### Acceptance Criteria

1. THE Map_Component SHALL initialize within 2 seconds on standard broadband connections
2. THE Map_Component SHALL render all markers within 1 second after data is available
3. THE Map_Component SHALL respond to clicks and interactions within 100 milliseconds
4. THE Map_Component SHALL invalidate and resize correctly when the viewport changes
5. THE Map_Component SHALL handle up to 100 pharmacy markers without performance degradation
6. THE Info_Window SHALL open within 200 milliseconds of marker click

### Requirement 17: Browser Compatibility and Accessibility

**User Story:** As a consumer using various devices and browsers, I want the map to function consistently, so that I can access pharmacy information regardless of my platform.

#### Acceptance Criteria

1. THE Map_Component SHALL function in Chrome, Firefox, Safari, and Edge browsers
2. THE Map_Component SHALL function on desktop, tablet, and mobile viewports
3. THE Map_Component SHALL respond to both mouse and touch interactions
4. THE Map_Component SHALL maintain functionality when browser extensions block some resources
5. THE Info_Window SHALL be readable with font sizes appropriate for mobile devices
6. THE Map_Component controls SHALL be accessible via keyboard navigation where feasible

### Requirement 18: Data Integration and State Management

**User Story:** As a developer, I want the map to integrate seamlessly with existing pharmacy data structures, so that no backend changes are required.

#### Acceptance Criteria

1. THE Map_Component SHALL consume the existing `pharmaciesData` global JavaScript array
2. THE Map_Component SHALL parse pharmacy objects with properties: id, name, address, lat, lng, logo, contactNumber, hours, medicines
3. THE Map_Component SHALL parse medicine objects with properties: name, price, stock
4. THE Map_Component SHALL maintain compatibility with existing search and filter functions
5. THE Map_Component SHALL maintain compatibility with existing autocomplete functionality
6. THE Map_Component SHALL maintain compatibility with existing distance calculation functions
7. THE Map_Component SHALL update displayed data when `pharmaciesData` is modified without requiring reinitialization
