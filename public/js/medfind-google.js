// ============================================
// MEDFIND - GOOGLE MAPS IMPLEMENTATION
// ============================================

// Global variables
let map;
let markers = [];
let userLat = 13.1475;  // Default: Legazpi City
let userLng = 123.7431;
let userMarker = null;
let currentSearchQuery = "";
let currentFilteredPharmacies = [];

// Ensure globals exist when pages don't provide them to avoid ReferenceErrors
if (typeof window !== 'undefined') {
    if (typeof window.allMedicineNames === 'undefined') window.allMedicineNames = [];
    if (typeof window.inventoryMedicineNames === 'undefined') window.inventoryMedicineNames = [];
}

/**
 * Calculate distance between two geographic coordinates using Haversine formula
 * @param {number} lat1 - First point latitude
 * @param {number} lon1 - First point longitude
 * @param {number} lat2 - Second point latitude
 * @param {number} lon2 - Second point longitude
 * @returns {number} Distance in kilometers
 */
function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // Earth's radius in kilometers
    const dLat = ((lat2 - lat1) * Math.PI) / 180;
    const dLon = ((lon2 - lon1) * Math.PI) / 180;
    const a =
        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos((lat1 * Math.PI) / 180) *
        Math.cos((lat2 * Math.PI) / 180) *
        Math.sin(dLon / 2) *
        Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
}

/**
 * Map container IDs that host the shared pharmacy location picker.
 * Every supported page reuses the same control and hidden field IDs, so the only
 * per-page difference is the map container. Resolution order is stable and a page
 * is expected to contain at most one of these containers.
 */
const LOCATION_PICKER_CONTAINER_IDS = [
    'profileLocationMap',
    'adminLocationMap',
    'registerLocationMap'
];

/**
 * Find the location picker container rendered on the current page, if any.
 * @returns {HTMLElement|null}
 */
function findLocationPickerContainer() {
    for (let index = 0; index < LOCATION_PICKER_CONTAINER_IDS.length; index++) {
        const container = document.getElementById(LOCATION_PICKER_CONTAINER_IDS[index]);
        if (container) return container;
    }
    return null;
}

/**
 * Main initialization function with retry logic for Google Maps API
 * Called by the global callback when Google Maps API loads
 * Implements 5 retry attempts with 200ms delay for reliability in tunneled/proxied environments
 */
function initializeMap(attemptCount = 0) {
    const maxAttempts = 5;

    if (document.readyState === 'loading') {
        if (!window.medfindMapsDomReadyQueued) {
            window.medfindMapsDomReadyQueued = true;
            document.addEventListener('DOMContentLoaded', () => initializeMap(), { once: true });
        }
        return;
    }

    const consumerContainer = document.getElementById('medfindMap');
    const locationEditorContainer = findLocationPickerContainer();

    // Most authenticated pages do not contain a map. The shared callback should be a no-op there.
    if (!consumerContainer && !locationEditorContainer) {
        return;
    }

    if (typeof google === 'undefined' || !google.maps) {
        if (attemptCount < maxAttempts) {
            console.warn(`Google Maps API not ready, retrying... (${attemptCount + 1}/${maxAttempts})`);
            setTimeout(() => initializeMap(attemptCount + 1), 200);
        } else {
            displayErrorMessage('Map failed to load. Please refresh the page.');
            console.error('Google Maps API failed to load after 5 attempts');
        }
        return;
    }

    if (consumerContainer && consumerContainer.dataset.googleMapInitialized !== 'true') {
        try {
            consumerContainer.dataset.googleMapInitialized = 'true';
            const mapManager = new GoogleMapManager('medfindMap');
            mapManager.initialize();
            console.log('Google Maps initialized successfully');
        } catch (error) {
            delete consumerContainer.dataset.googleMapInitialized;
            console.error('Error initializing consumer map:', error);
            displayErrorMessage('Map initialization failed. Please refresh the page.');
        }
    }

    if (locationEditorContainer && locationEditorContainer.dataset.googleMapInitialized !== 'true') {
        try {
            initializeLocationEditor(locationEditorContainer.id);
        } catch (error) {
            delete locationEditorContainer.dataset.googleMapInitialized;
            console.error('Error initializing pharmacy location editor:', error);
            displayErrorMessage('Map initialization failed. Please refresh the page.');
        }
    }
}

/**
 * Display error message to user
 * @param {string} message - Error message to display
 */
function displayErrorMessage(message) {
    // Create error message element if it doesn't exist
    let errorDiv = document.getElementById('mapErrorMessage');
    if (!errorDiv) {
        errorDiv = document.createElement('div');
        errorDiv.id = 'mapErrorMessage';
        errorDiv.style.cssText = `
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #ef4444;
            color: white;
            padding: 16px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 10000;
            font-family: system-ui, -apple-system, sans-serif;
            font-size: 14px;
            font-weight: 500;
        `;
        document.body.appendChild(errorDiv);
    }
    errorDiv.textContent = message;
    errorDiv.style.display = 'block';
}

// ============================================
// GoogleMapManager Class
// ============================================

/**
 * Manages Google Maps initialization, configuration, and geolocation
 * Handles map setup with retry logic and geolocation fallback
 */
class GoogleMapManager {
    /**
     * @param {string} containerId - ID of the HTML element to contain the map
     * @param {Object} options - Map configuration options (optional)
     */
    constructor(containerId, options = {}) {
        this.containerId = containerId;
        this.options = {
            center: options.center || { lat: 13.1475, lng: 123.7431 }, // Legazpi City default
            zoom: options.zoom || 13,
            minZoom: options.minZoom || 10,
            maxZoom: options.maxZoom || 19,
            mapTypeId: options.mapTypeId || 'roadmap',
            gestureHandling: options.gestureHandling || 'greedy',
            zoomControl: options.zoomControl !== false,
            mapTypeControl: options.mapTypeControl !== false,
            streetViewControl: options.streetViewControl !== false,
            fullscreenControl: options.fullscreenControl !== false
        };
        this.map = null;
    }
    
    /**
     * Initialize the Google Maps instance with geolocation handling
     * Attempts to get user location, falls back to default center if denied
     */
    initialize() {
        const container = document.getElementById(this.containerId);
        if (!container) {
            throw new Error(`Map container #${this.containerId} not found`);
        }
        
        // Create map instance
        this.map = new google.maps.Map(container, {
            center: this.options.center,
            zoom: this.options.zoom,
            minZoom: this.options.minZoom,
            maxZoom: this.options.maxZoom,
            mapTypeId: this.options.mapTypeId,
            gestureHandling: this.options.gestureHandling,
            zoomControl: this.options.zoomControl,
            mapTypeControl: this.options.mapTypeControl,
            streetViewControl: this.options.streetViewControl,
            fullscreenControl: this.options.fullscreenControl
        });
        
        // Store map reference globally
        window.map = this.map;
        map = this.map;
        
        // Handle geolocation
        this.handleGeolocation();
        
        // Create pharmacy markers if pharmaciesData is available
        if (typeof pharmaciesData !== 'undefined' && pharmaciesData.length > 0) {
            // Create InfoWindow manager
            const infoWindowManager = new PharmacyInfoWindowManager(this.map, pharmaciesData);
            
            // Create markers with InfoWindow support
            const pharmacyMarkerManager = new PharmacyMarkerManager(this.map, pharmaciesData, infoWindowManager);
            pharmacyMarkerManager.createMarkers();
            
            // Store references globally
            window.pharmacyMarkerManager = pharmacyMarkerManager;
            window.infoWindowManager = infoWindowManager;
        } else {
            console.warn('pharmaciesData not available or empty - no markers created');
        }
        
        return this.map;
    }
    
    /**
     * Attempt to get user's current location via browser geolocation API
     * Falls back to default center (Legazpi City) if permission denied or unavailable
     */
    handleGeolocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    // Success: use user's actual location
                    userLat = position.coords.latitude;
                    userLng = position.coords.longitude;
                    
                    const userLocation = { lat: userLat, lng: userLng };
                    this.map.setCenter(userLocation);
                    
                    console.log(`User location: ${userLat}, ${userLng}`);
                    
                    // Create and show user location marker
                    const userLocationMarker = new UserLocationMarker(this.map);
                    userLocationMarker.setPosition(userLat, userLng);
                    userLocationMarker.show();
                    
                    // Store reference globally for future updates
                    window.userMarker = userLocationMarker;
                },
                (error) => {
                    // Error: fall back to default location
                    console.warn('Geolocation error:', error.message);
                    userLat = 13.1475;  // Legazpi City
                    userLng = 123.7431;
                    
                    const defaultLocation = { lat: userLat, lng: userLng };
                    this.map.setCenter(defaultLocation);
                    
                    console.log('Using default location: Legazpi City');
                    // Do NOT show user location marker if permission denied
                }
            );
        } else {
            // Geolocation not supported
            console.warn('Geolocation is not supported by this browser');
            userLat = 13.1475;
            userLng = 123.7431;
            
            const defaultLocation = { lat: userLat, lng: userLng };
            this.map.setCenter(defaultLocation);
        }
    }
    
    /**
     * Set map center to specific coordinates
     * @param {number} lat - Latitude
     * @param {number} lng - Longitude
     */
    setCenter(lat, lng) {
        if (this.map) {
            this.map.setCenter({ lat, lng });
        }
    }
    
    /**
     * Fit map to specified bounds
     * @param {google.maps.LatLngBounds} bounds - Bounds to fit
     */
    fitBounds(bounds) {
        if (this.map) {
            this.map.fitBounds(bounds);
        }
    }
    
    /**
     * Get the Google Maps instance
     * @returns {google.maps.Map} The map instance
     */
    getMap() {
        return this.map;
    }
}

// ============================================
// PharmacyLocationEditor Class
// ============================================

/**
 * Manages the authenticated pharmacy profile location picker.
 */
class PharmacyLocationEditor {
    /**
     * @param {string} containerId - Map container element ID
     * @param {*} initialLat - Existing latitude, when available
     * @param {*} initialLng - Existing longitude, when available
     * @param {Object} options - Related form element IDs and optional callbacks
     */
    constructor(containerId, initialLat, initialLng, options = {}) {
        this.containerId = containerId;
        this.initialLat = initialLat;
        this.initialLng = initialLng;
        this.options = {
            latitudeInputId: options.latitudeInputId || 'latitude',
            longitudeInputId: options.longitudeInputId || 'longitude',
            addressInputId: options.addressInputId || 'address',
            searchInputId: options.searchInputId || 'addressSearch',
            searchButtonId: options.searchButtonId || 'addressSearchBtn',
            locationButtonId: options.locationButtonId || 'useMyLocationBtn',
            noticeId: options.noticeId || 'geoNotice',
            onCoordinatesChange: options.onCoordinatesChange || null,
            onAddressChange: options.onAddressChange || null
        };
        this.defaultPosition = { lat: 14.5995, lng: 120.9842 };
        this.map = null;
        this.marker = null;
        this.geocoder = null;
        this.autocomplete = null;
        this.hasExistingLocation = this.isValidCoordinate(initialLat, initialLng);
        this.currentPosition = null;
    }

    /** Initialize the map, draggable marker, geocoder, autocomplete, and controls. */
    initialize() {
        const container = document.getElementById(this.containerId);
        if (!container) {
            throw new Error(`Map container #${this.containerId} not found`);
        }
        if (this.map) return this.map;

        const startPosition = this.hasExistingLocation
            ? { lat: Number(this.initialLat), lng: Number(this.initialLng) }
            : this.defaultPosition;

        this.map = new google.maps.Map(container, {
            center: startPosition,
            zoom: this.hasExistingLocation ? 15 : 12,
            mapTypeId: 'roadmap',
            gestureHandling: 'greedy',
            zoomControl: true,
            mapTypeControl: true,
            streetViewControl: true,
            fullscreenControl: false
        });
        this.geocoder = new google.maps.Geocoder();
        this.marker = new google.maps.Marker({
            position: startPosition,
            map: this.map,
            draggable: true,
            title: 'Pharmacy location'
        });
        this.currentPosition = startPosition;

        this.marker.addListener('dragend', () => {
            const position = this.marker.getPosition();
            if (!position) return;
            this.setMarkerPosition(position.lat(), position.lng(), { recenter: false });
            this.reverseGeocode(position.lat(), position.lng());
        });

        this.map.addListener('click', (event) => {
            if (!event.latLng) return;
            const lat = event.latLng.lat();
            const lng = event.latLng.lng();
            this.setMarkerPosition(lat, lng, { recenter: false });
            this.reverseGeocode(lat, lng);
        });

        this.initializeAutocomplete();
        this.bindControls();
        this.populateInitialAddress();

        // Always mirror the marker's initial position into the hidden inputs so the
        // visible pin matches what gets submitted, including the default position
        // used when the pharmacy has no saved coordinates yet.
        this.updateCoordinateFields(startPosition.lat, startPosition.lng);

        container.dataset.googleMapInitialized = 'true';
        return this.map;
    }

    /**
     * Move the marker and synchronize coordinate fields using exactly six decimals.
     * @param {*} lat
     * @param {*} lng
     * @param {{recenter?: boolean, address?: string}} options
     * @returns {boolean}
     */
    setMarkerPosition(lat, lng, options = {}) {
        if (!this.isValidCoordinate(lat, lng)) return false;

        const position = { lat: Number(lat), lng: Number(lng) };
        this.currentPosition = position;
        if (this.marker) this.marker.setPosition(position);
        this.updateCoordinateFields(position.lat, position.lng);

        if (options.address) this.updateAddress(options.address);
        if (options.recenter !== false && this.map) {
            this.map.setCenter(position);
            if (this.map.getZoom() < 15) this.map.setZoom(15);
        }
        return true;
    }

    /** Convert a typed Philippine address to coordinates. */
    geocodeAddress(address) {
        const query = String(address || '').trim();
        if (!query) {
            this.showNotice('Enter an address to search.', true);
            return;
        }

        this.showNotice('Searching for that address…', false);
        this.geocoder.geocode({
            address: query,
            componentRestrictions: { country: 'ph' },
            region: 'PH'
        }, (results, status) => {
            if (status === 'OK' && results && results[0] && results[0].geometry) {
                const result = results[0];
                const location = result.geometry.location;
                this.setMarkerPosition(location.lat(), location.lng(), {
                    recenter: true,
                    address: result.formatted_address || query
                });
                this.showNotice('Location set from the address search.', false);
                return;
            }

            if (status === 'ZERO_RESULTS') {
                this.showNotice('Address not found. Try a more specific Philippine address.', true);
            } else {
                console.error('Google geocoding failed with status:', status);
                this.showNotice('Could not find that location. Please try again or place the pin manually.', true);
            }
        });
    }

    /** Reverse geocode coordinates; failures never undo a usable marker position. */
    reverseGeocode(lat, lng) {
        if (!this.isValidCoordinate(lat, lng) || !this.geocoder) return;

        this.geocoder.geocode({
            location: { lat: Number(lat), lng: Number(lng) },
            region: 'PH'
        }, (results, status) => {
            if (status === 'OK' && results && results[0] && results[0].formatted_address) {
                this.updateAddress(results[0].formatted_address);
                return;
            }

            if (status !== 'ZERO_RESULTS') {
                console.warn('Google reverse geocoding failed with status:', status);
            }
        });
    }

    /** Return the latest selected coordinates. */
    getCurrentLocation() {
        return this.currentPosition ? { ...this.currentPosition } : null;
    }

    /** Configure Google-rendered address suggestions for Philippine places. */
    initializeAutocomplete() {
        const searchInput = document.getElementById(this.options.searchInputId);
        if (!searchInput || !google.maps.places || typeof google.maps.places.Autocomplete !== 'function') {
            return;
        }

        this.autocomplete = new google.maps.places.Autocomplete(searchInput, {
            componentRestrictions: { country: 'ph' },
            fields: ['formatted_address', 'geometry'],
            types: ['geocode']
        });
        this.autocomplete.bindTo('bounds', this.map);
        this.autocomplete.addListener('place_changed', () => {
            const place = this.autocomplete.getPlace();
            if (!place.geometry || !place.geometry.location) {
                this.showNotice('Select an address from the suggestions or use the search button.', true);
                return;
            }

            const lat = place.geometry.location.lat();
            const lng = place.geometry.location.lng();
            this.setMarkerPosition(lat, lng, {
                recenter: true,
                address: place.formatted_address || searchInput.value
            });
            this.reverseGeocode(lat, lng);
            this.showNotice('Location set from the selected address.', false);
        });
    }

    /** Bind manual address search and browser geolocation controls. */
    bindControls() {
        const searchInput = document.getElementById(this.options.searchInputId);
        const searchButton = document.getElementById(this.options.searchButtonId);
        const locationButton = document.getElementById(this.options.locationButtonId);

        if (searchButton && searchInput) {
            searchButton.addEventListener('click', () => this.geocodeAddress(searchInput.value));
            searchInput.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    this.geocodeAddress(searchInput.value);
                }
            });
        }

        if (locationButton) {
            locationButton.addEventListener('click', () => this.useCurrentLocation());
        }
    }

    /** Use browser geolocation to place the marker. */
    useCurrentLocation() {
        if (window.isSecureContext === false) {
            this.showNotice('Location not available. Please use a secure HTTPS connection and try again.', true);
            return;
        }
        if (!navigator.geolocation) {
            this.showNotice('Location not available. Geolocation is not supported by this browser.', true);
            return;
        }

        this.showNotice('Locating you…', false);
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                this.setMarkerPosition(lat, lng, { recenter: true });
                this.reverseGeocode(lat, lng);
                this.showNotice('Location set from your device.', false);
            },
            (error) => {
                const denied = error && error.code === error.PERMISSION_DENIED;
                const detail = denied
                    ? ' Location permission was denied.'
                    : ' Please check your device settings and try again.';
                this.showNotice(`Location not available.${detail}`, true);
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    }

    updateCoordinateFields(lat, lng) {
        const latitudeInput = document.getElementById(this.options.latitudeInputId);
        const longitudeInput = document.getElementById(this.options.longitudeInputId);
        const latitude = Number(lat).toFixed(6);
        const longitude = Number(lng).toFixed(6);

        if (latitudeInput) latitudeInput.value = latitude;
        if (longitudeInput) longitudeInput.value = longitude;
        if (typeof this.options.onCoordinatesChange === 'function') {
            this.options.onCoordinatesChange({ lat: latitude, lng: longitude });
        }
    }

    updateAddress(address) {
        const normalizedAddress = String(address || '').trim();
        if (!normalizedAddress) return;

        const addressInput = document.getElementById(this.options.addressInputId);
        const searchInput = document.getElementById(this.options.searchInputId);
        if (addressInput) addressInput.value = normalizedAddress;
        if (searchInput) searchInput.value = normalizedAddress;
        if (typeof this.options.onAddressChange === 'function') {
            this.options.onAddressChange(normalizedAddress);
        }
    }

    populateInitialAddress() {
        const addressInput = document.getElementById(this.options.addressInputId);
        const searchInput = document.getElementById(this.options.searchInputId);
        if (addressInput && searchInput && addressInput.value && !searchInput.value) {
            searchInput.value = addressInput.value;
        }
    }

    showNotice(message, isError) {
        const notice = document.getElementById(this.options.noticeId);
        if (!notice) return;

        notice.textContent = message;
        notice.classList.remove('hidden', 'text-red-500', 'text-green-600');
        notice.classList.add(isError ? 'text-red-500' : 'text-green-600');
    }

    isValidCoordinate(lat, lng) {
        const normalizedLat = Number(lat);
        const normalizedLng = Number(lng);
        return lat !== '' && lat !== null && lat !== undefined &&
            lng !== '' && lng !== null && lng !== undefined &&
            Number.isFinite(normalizedLat) && Number.isFinite(normalizedLng) &&
            normalizedLat >= -90 && normalizedLat <= 90 &&
            normalizedLng >= -180 && normalizedLng <= 180;
    }
}

/**
 * Initialize the shared location picker for whichever supported container is on the page,
 * using the existing hidden latitude/longitude/address fields. Idempotent per container.
 * @param {string} [containerId] - Optional explicit container ID
 * @returns {PharmacyLocationEditor|null}
 */
function initializeLocationEditor(containerId) {
    const container = containerId
        ? document.getElementById(containerId)
        : findLocationPickerContainer();
    if (!container || container.dataset.googleMapInitialized === 'true') return null;

    const latitudeInput = document.getElementById('latitude');
    const longitudeInput = document.getElementById('longitude');
    const editor = new PharmacyLocationEditor(
        container.id,
        latitudeInput ? latitudeInput.value : null,
        longitudeInput ? longitudeInput.value : null
    );
    editor.initialize();
    window.pharmacyLocationEditor = editor;
    return editor;
}

// ============================================
// UserLocationMarker Class
// ============================================

/**
 * Manages the user's location marker with pulsing blue dot animation
 * Displays "You are here" tooltip and maintains higher z-index than pharmacy markers
 */
class UserLocationMarker {
    /**
     * @param {google.maps.Map} map - The Google Maps instance
     */
    constructor(map) {
        this.map = map;
        this.marker = null;
        this.position = null;
    }
    
    /**
     * Set or update the marker position
     * @param {number} lat - Latitude
     * @param {number} lng - Longitude
     */
    setPosition(lat, lng) {
        this.position = { lat, lng };
        
        if (this.marker) {
            // Update existing marker position
            this.marker.setPosition(this.position);
        } else {
            // Create new marker with custom icon
            this.createMarker();
        }
    }
    
    /**
     * Create the user location marker with simple blue dot
     * FIXED: Use inline SymbolPath.CIRCLE instead of SVG data URL
     */
    createMarker() {
        if (!this.position) {
            console.warn('Cannot create user marker: position not set');
            return;
        }
        
        // Create marker with inline icon (WORKING APPROACH)
        this.marker = new google.maps.Marker({
            position: this.position,
            map: this.map,
            icon: {
                path: google.maps.SymbolPath.CIRCLE,
                scale: 10,
                fillColor: '#4285F4',  // Google blue
                fillOpacity: 1.0,
                strokeColor: '#FFFFFF',  // White border
                strokeWeight: 3
            },
            title: 'You are here',
            zIndex: 1000 // Higher than pharmacy markers
        });
    }
    
    
    /**
     * Show the marker on the map
     */
    show() {
        if (this.marker) {
            this.marker.setMap(this.map);
        }
    }
    
    /**
     * Hide the marker from the map
     */
    hide() {
        if (this.marker) {
            this.marker.setMap(null);
        }
    }
    
    /**
     * Get the current marker position
     * @returns {Object|null} Position object {lat, lng} or null
     */
    getPosition() {
        return this.position;
    }
}

// ============================================
// PharmacyMarkerManager Class
// ============================================

/**
 * Manages pharmacy markers on the map
 * Handles marker creation, filtering, and click events
 */
class PharmacyMarkerManager {
    /**
     * @param {google.maps.Map} map - The Google Maps instance
     * @param {Array} pharmaciesData - Array of pharmacy objects
     * @param {PharmacyInfoWindowManager} infoWindowManager - InfoWindow manager instance
     */
    constructor(map, pharmaciesData, infoWindowManager = null) {
        this.map = map;
        this.pharmaciesData = pharmaciesData || [];
        this.markers = [];
        this.infoWindowManager = infoWindowManager;
    }
    
    /**
     * Create markers for all pharmacies
     * Calculates distance from user location for each pharmacy
     */
    async createMarkers() {
        // Clear existing markers
        this.clearMarkers();
        
        // Create marker for each pharmacy
        for (const pharmacy of this.pharmaciesData) {
            // Calculate distance from user location
            pharmacy.distance = calculateDistance(
                userLat, 
                userLng, 
                pharmacy.lat, 
                pharmacy.lng
            );
            
            // Check if pharmacy has logo
            const hasLogo = pharmacy.logo && pharmacy.logo.trim() !== '';
            
            console.log(`Creating marker for ${pharmacy.name} - Logo: ${pharmacy.logo || 'null'}`);
            
            let marker;
            
            if (hasLogo) {
                // Pharmacy has logo - create circular marker using canvas
                try {
                    const circularIconUrl = await this.createCircularLogoIcon(pharmacy.logo, pharmacy.name);
                    
                    marker = new google.maps.Marker({
                        position: { lat: pharmacy.lat, lng: pharmacy.lng },
                        map: this.map,
                        icon: {
                            url: circularIconUrl,
                            scaledSize: new google.maps.Size(48, 48),
                            anchor: new google.maps.Point(24, 48)
                        },
                        title: pharmacy.name,
                        zIndex: 100
                    });
                } catch (error) {
                    console.warn(`Failed to create circular logo for ${pharmacy.name}, using initial instead`);
                    // Fallback to initial if logo fails to load
                    const firstLetter = pharmacy.name.charAt(0).toUpperCase();
                    const svg = `
                        <svg width="48" height="48" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="24" cy="24" r="22" fill="#9400D3" stroke="#191970" stroke-width="2"/>
                            <text x="24" y="24" text-anchor="middle" dominant-baseline="central" 
                                  font-family="Arial, sans-serif" font-size="24" font-weight="bold" fill="white">
                                ${firstLetter}
                            </text>
                        </svg>
                    `;
                    
                    marker = new google.maps.Marker({
                        position: { lat: pharmacy.lat, lng: pharmacy.lng },
                        map: this.map,
                        icon: {
                            url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
                            scaledSize: new google.maps.Size(48, 48),
                            anchor: new google.maps.Point(24, 48)
                        },
                        title: pharmacy.name,
                        zIndex: 100
                    });
                }
            } else {
                // No logo - create circular marker with first letter initial
                const firstLetter = pharmacy.name.charAt(0).toUpperCase();
                
                // Create SVG circular marker with initial
                const svg = `
                    <svg width="48" height="48" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="24" cy="24" r="22" fill="#9400D3" stroke="#191970" stroke-width="2"/>
                        <text x="24" y="24" text-anchor="middle" dominant-baseline="central" 
                              font-family="Arial, sans-serif" font-size="24" font-weight="bold" fill="white">
                            ${firstLetter}
                        </text>
                    </svg>
                `;
                
                marker = new google.maps.Marker({
                    position: { lat: pharmacy.lat, lng: pharmacy.lng },
                    map: this.map,
                    icon: {
                        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
                        scaledSize: new google.maps.Size(48, 48),
                        anchor: new google.maps.Point(24, 48)
                    },
                    title: pharmacy.name,
                    zIndex: 100
                });
            }
            
            // Store pharmacy data reference on marker
            marker.pharmacyData = pharmacy;
            
            // Add click event listener to open InfoWindow
            marker.addListener('click', () => {
                console.log('Pharmacy marker clicked:', pharmacy.name);
                
                // Open InfoWindow if manager is available
                if (this.infoWindowManager) {
                    this.infoWindowManager.open(pharmacy, marker);
                }
            });
            
            // Store marker
            this.markers.push(marker);
        }
        
        console.log(`Created ${this.markers.length} pharmacy markers`);
    }
    
    /**
     * Create a circular marker icon from an image URL using canvas
     * @param {string} imageUrl - URL of the image to be made circular
     * @param {string} pharmacyName - Name of the pharmacy (for error handling)
     * @returns {Promise<string>} - Promise that resolves to a data URL of the circular marker
     */
    createCircularLogoIcon(imageUrl, pharmacyName) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.crossOrigin = 'Anonymous';  // Enable CORS
            
            img.onload = () => {
                // Create canvas
                const canvas = document.createElement('canvas');
                const size = 48;
                canvas.width = size;
                canvas.height = size;
                const ctx = canvas.getContext('2d');
                
                // Draw white circle background
                ctx.fillStyle = 'white';
                ctx.beginPath();
                ctx.arc(size/2, size/2, size/2 - 2, 0, Math.PI * 2);
                ctx.fill();
                
                // Clip to circle for the image
                ctx.save();
                ctx.beginPath();
                ctx.arc(size/2, size/2, size/2 - 4, 0, Math.PI * 2);
                ctx.clip();
                
                // Draw image (centered and scaled to cover circle)
                const scale = Math.max(size / img.width, size / img.height);
                const x = (size / 2) - (img.width / 2) * scale;
                const y = (size / 2) - (img.height / 2) * scale;
                ctx.drawImage(img, x, y, img.width * scale, img.height * scale);
                
                ctx.restore();
                
                // Draw navy border
                ctx.strokeStyle = '#191970';
                ctx.lineWidth = 2;
                ctx.beginPath();
                ctx.arc(size/2, size/2, size/2 - 2, 0, Math.PI * 2);
                ctx.stroke();
                
                // Convert to data URL
                resolve(canvas.toDataURL('image/png'));
            };
            
            img.onerror = () => {
                console.warn(`Failed to load logo for ${pharmacyName}, using fallback`);
                reject(new Error(`Failed to load image: ${imageUrl}`));
            };
            
            img.src = imageUrl;
        });
    }
    
    
    /**
     * Filter markers based on search query
     * @param {Array} filteredPharmacies - Array of pharmacy objects to display
     */
    filterMarkers(filteredPharmacies) {
        const filteredIds = new Set(filteredPharmacies.map(p => p.id));
        
        this.markers.forEach(marker => {
            const pharmacyId = marker.pharmacyData.id;
            if (filteredIds.has(pharmacyId)) {
                marker.setMap(this.map); // Show marker
            } else {
                marker.setMap(null); // Hide marker
            }
        });
    }
    
    /**
     * Clear all markers from the map
     */
    clearMarkers() {
        this.markers.forEach(marker => {
            marker.setMap(null);
        });
        this.markers = [];
    }
    
    /**
     * Get marker for a specific pharmacy ID
     * @param {number} pharmacyId - Pharmacy ID
     * @returns {google.maps.Marker|null} Marker or null if not found
     */
    getMarkerByPharmacyId(pharmacyId) {
        return this.markers.find(marker => marker.pharmacyData.id === pharmacyId) || null;
    }
}

// ============================================
// PharmacyInfoWindowManager Class
// ============================================

/**
 * Manages InfoWindow display for pharmacy markers
 * Handles opening, closing, and content generation for InfoWindows
 */
class PharmacyInfoWindowManager {
    /**
     * @param {google.maps.Map} map - The Google Maps instance
     * @param {Array} pharmaciesData - Array of pharmacy objects
     */
    constructor(map, pharmaciesData) {
        this.map = map;
        this.pharmaciesData = pharmaciesData || [];
        this.currentInfoWindow = null;
        this.contentSequence = 0;

        // Keep one InfoWindow open at a time and close it on map background clicks.
        this.map.addListener('click', () => {
            this.close();
        });
    }

    /**
     * Open InfoWindow for a pharmacy marker
     * @param {Object} pharmacy - Pharmacy data object
     * @param {google.maps.Marker} marker - The marker to attach InfoWindow to
     */
    open(pharmacy, marker) {
        this.close();

        const contentId = `medfind-pharmacy-info-${++this.contentSequence}`;
        const content = this.generateInfoWindowHTML(pharmacy, contentId);
        const infoWindow = new google.maps.InfoWindow({
            content,
            disableAutoPan: false
        });

        this.currentInfoWindow = infoWindow;
        infoWindow.open(this.map, marker);

        google.maps.event.addListenerOnce(infoWindow, 'domready', () => {
            const contentRoot = document.getElementById(contentId);
            if (!contentRoot) return;

            this.scopeGoogleChrome(contentRoot);
            this.bindContentActions(contentRoot, pharmacy);
        });

        infoWindow.addListener('closeclick', () => {
            if (this.currentInfoWindow === infoWindow) {
                this.currentInfoWindow = null;
            }
        });

        console.log('InfoWindow opened for:', pharmacy.name);
    }

    /**
     * Generate the legacy MedFind popup layout with escaped dynamic content.
     * @param {Object} pharmacy - Pharmacy data object
     * @param {string} contentId - Unique DOM id for scoped chrome handling
     * @returns {string} HTML content for InfoWindow
     */
    generateInfoWindowHTML(pharmacy, contentId) {
        const name = this.toText(pharmacy.name, 'Pharmacy');
        const address = this.toText(pharmacy.address, 'No address available');
        const contact = this.toText(pharmacy.contactNumber);
        const hours = this.toText(pharmacy.hours, 'Hours not available').replace(/\s+/g, ' ');
        const pharmacyId = this.toSafeInteger(pharmacy.id);
        const lat = this.toFiniteNumber(pharmacy.lat);
        const lng = this.toFiniteNumber(pharmacy.lng);
        const detailsUrl = pharmacyId === null
            ? '#'
            : `/consumer/pharmacy/${encodeURIComponent(String(pharmacyId))}`;
        const logoUrl = this.getSafeLogoUrl(pharmacy.logo);
        const initial = Array.from(name)[0] || 'P';
        const distance = lat === null || lng === null
            ? null
            : calculateDistance(userLat, userLng, lat, lng);
        const topMedicines = this.getTopMedicines(pharmacy);

        const logoHTML = logoUrl
            ? `<img class="piw-logo" src="${this.escapeHTML(logoUrl)}" alt="${this.escapeHTML(name)} logo">`
            : `<div class="piw-logo-fallback" aria-hidden="true">${this.escapeHTML(initial.toUpperCase())}</div>`;

        const medicinesHTML = topMedicines.length > 0
            ? topMedicines.map((medicine) => `
                <div class="piw-medicine-item">
                    <span class="piw-medicine-name">${this.escapeHTML(this.toText(medicine.name, 'Unknown medicine'))}</span>
                    <span class="piw-medicine-price">&#8369;${this.escapeHTML(this.formatPrice(medicine.price))}</span>
                </div>
            `).join('')
            : '<div class="piw-no-stock">No stock available</div>';

        return `
            <article id="${this.escapeHTML(contentId)}" class="pharmacy-info-window" aria-label="${this.escapeHTML(name)} details">
                <button type="button" class="piw-close" data-piw-action="close" aria-label="Close pharmacy details">&times;</button>

                <header class="piw-header">
                    ${logoHTML}
                    <div class="piw-title-section">
                        <a class="piw-name" href="${this.escapeHTML(detailsUrl)}"${pharmacyId === null ? ' aria-disabled="true"' : ''}>${this.escapeHTML(name)}</a>
                        <div class="piw-address">${this.escapeHTML(address)}</div>
                        <div class="piw-meta">
                            ${Number.isFinite(distance) ? `<span class="piw-distance">${distance.toFixed(1)} km away</span>` : ''}
                            ${contact ? `<span class="piw-contact"><i class="fas fa-phone" aria-hidden="true"></i> ${this.escapeHTML(contact)}</span>` : ''}
                        </div>
                        <div class="piw-hours"><i class="fas fa-clock" aria-hidden="true"></i> ${this.escapeHTML(hours)}</div>
                    </div>
                </header>

                <section class="piw-medicines" aria-label="Top medicines">
                    <div class="piw-medicines-title">Top Medicines</div>
                    ${medicinesHTML}
                </section>

                <div class="piw-actions">
                    <a class="piw-btn piw-btn-view" href="${this.escapeHTML(detailsUrl)}"${pharmacyId === null ? ' aria-disabled="true"' : ''}>View Products</a>
                    <button type="button" class="piw-btn piw-btn-message" data-piw-action="message"${pharmacyId === null ? ' disabled' : ''}>Message</button>
                    <button type="button" class="piw-btn piw-btn-directions" data-piw-action="directions"${lat === null || lng === null ? ' disabled' : ''}>Directions</button>
                </div>
            </article>
        `;
    }

    /**
     * Apply classes only to the Google InfoWindow chrome containing this popup.
     * @param {HTMLElement} contentRoot - This popup's root element
     */
    scopeGoogleChrome(contentRoot) {
        const contentContainer = contentRoot.closest('.gm-style-iw-d');
        if (!contentContainer) return;

        contentContainer.classList.add('medfind-pharmacy-iw-content');
        const chromeContainer = contentContainer.closest('.gm-style-iw-c');
        if (!chromeContainer) return;

        chromeContainer.classList.add('medfind-pharmacy-iw');
        const chromeWrapper = chromeContainer.closest('.gm-style-iw-t');
        if (chromeWrapper) {
            chromeWrapper.classList.add('medfind-pharmacy-iw-wrapper');
        }
    }

    /**
     * Bind popup controls without injecting dynamic values into inline JavaScript.
     * @param {HTMLElement} contentRoot - This popup's root element
     * @param {Object} pharmacy - Pharmacy data object
     */
    bindContentActions(contentRoot, pharmacy) {
        const closeButton = contentRoot.querySelector('[data-piw-action="close"]');
        const messageButton = contentRoot.querySelector('[data-piw-action="message"]');
        const directionsButton = contentRoot.querySelector('[data-piw-action="directions"]');
        const pharmacyId = this.toSafeInteger(pharmacy.id);
        const lat = this.toFiniteNumber(pharmacy.lat);
        const lng = this.toFiniteNumber(pharmacy.lng);

        if (closeButton) {
            closeButton.addEventListener('click', () => this.close());
        }
        if (messageButton && pharmacyId !== null) {
            messageButton.addEventListener('click', () => window.openContactPharmacy(pharmacyId));
        }
        if (directionsButton && lat !== null && lng !== null) {
            directionsButton.addEventListener('click', () => window.getDirections(lat, lng));
        }
    }

    /**
     * Get the first 3 in-stock medicines without mutating source data.
     * @param {Object} pharmacy - Pharmacy data object
     * @returns {Array} Array of medicine objects
     */
    getTopMedicines(pharmacy) {
        if (!Array.isArray(pharmacy.medicines)) {
            return [];
        }

        return pharmacy.medicines
            .filter((medicine) => this.toFiniteNumber(medicine.stock) > 0)
            .slice(0, 3);
    }

    /**
     * Close the currently open InfoWindow.
     */
    close() {
        if (this.currentInfoWindow) {
            this.currentInfoWindow.close();
            this.currentInfoWindow = null;
        }
    }

    /**
     * Escape text for safe insertion into HTML and attributes.
     * @param {*} value - Value to escape
     * @returns {string} Escaped text
     */
    escapeHTML(value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);
        return div.innerHTML;
    }

    /**
     * Normalize display text and optional fallback.
     * @param {*} value - Candidate text
     * @param {string} fallback - Fallback when blank
     * @returns {string}
     */
    toText(value, fallback = '') {
        if (value === null || value === undefined) return fallback;
        const text = String(value).trim();
        return text || fallback;
    }

    /**
     * Normalize a finite number used by map actions.
     * @param {*} value - Candidate number
     * @returns {number|null}
     */
    toFiniteNumber(value) {
        if (value === null || value === undefined ||
            (typeof value === 'string' && value.trim() === '')) {
            return null;
        }

        const number = Number(value);
        return Number.isFinite(number) ? number : null;
    }

    /**
     * Normalize an integer identifier used in routes and actions.
     * @param {*} value - Candidate id
     * @returns {number|null}
     */
    toSafeInteger(value) {
        const number = Number(value);
        return Number.isSafeInteger(number) && number >= 0 ? number : null;
    }

    /**
     * Permit only HTTP(S) and same-page-relative logo URLs.
     * @param {*} value - Candidate logo URL
     * @returns {string|null}
     */
    getSafeLogoUrl(value) {
        const source = this.toText(value);
        if (!source) return null;

        try {
            const url = new URL(source, window.location.origin);
            return url.protocol === 'http:' || url.protocol === 'https:' ? url.href : null;
        } catch (error) {
            return null;
        }
    }

    /**
     * Format a medicine price as a non-negative decimal value.
     * @param {*} value - Candidate price
     * @returns {string}
     */
    formatPrice(value) {
        const price = this.toFiniteNumber(value);
        return price !== null && price >= 0 ? price.toFixed(2) : '0.00';
    }
}

// ============================================
// Global Action Functions for InfoWindow Buttons
// ============================================

/**
 * Normalize a pharmacy id before using it in a route or callback.
 * @param {*} value - Candidate pharmacy id
 * @returns {number|null}
 */
function normalizePharmacyId(value) {
    const pharmacyId = Number(value);
    return Number.isSafeInteger(pharmacyId) && pharmacyId >= 0 ? pharmacyId : null;
}

/**
 * Close the active pharmacy InfoWindow from external UI callbacks.
 */
window.closePharmacyInfoWindow = function() {
    if (window.infoWindowManager) {
        window.infoWindowManager.close();
    }
};

/**
 * Navigate to a pharmacy detail page.
 * @param {*} pharmacyId - Pharmacy ID
 */
window.viewPharmacy = function(pharmacyId) {
    const safeId = normalizePharmacyId(pharmacyId);
    if (safeId === null) return;
    window.location.href = `/consumer/pharmacy/${encodeURIComponent(String(safeId))}`;
};

/**
 * Open the existing dashboard chat, falling back to the pharmacy contact section.
 * @param {*} pharmacyId - Pharmacy ID
 */
window.openContactPharmacy = function(pharmacyId) {
    const safeId = normalizePharmacyId(pharmacyId);
    if (safeId === null) return;

    window.closePharmacyInfoWindow();

    if (typeof openChatWindow === 'function' &&
        typeof conversationsData !== 'undefined' &&
        Array.isArray(conversationsData)) {
        const existingConversation = conversationsData.find((conversation) => conversation.pharmacy_id == safeId);
        if (existingConversation) {
            openChatWindow(safeId);
            return;
        }

        const pharmacy = typeof pharmaciesData !== 'undefined' && Array.isArray(pharmaciesData)
            ? pharmaciesData.find((item) => item.id == safeId)
            : null;
        const pharmacyName = pharmacy
            ? (pharmacy.pharmacy_name || pharmacy.name || 'Pharmacy')
            : 'Pharmacy';

        conversationsData.push({
            pharmacy_id: safeId,
            pharmacy_name: pharmacyName,
            unread: 0,
            messages: []
        });
        openChatWindow(safeId);
        return;
    }

    window.location.href = `/consumer/pharmacy/${encodeURIComponent(String(safeId))}#contact`;
};

// Preserve the existing public callback while using the application's actual contact flow.
window.openChat = window.openContactPharmacy;

// ============================================
// DirectionsService Class
// ============================================

/**
 * Calculates and renders driving directions while owning all route-specific UI
 * and Google Maps resources.
 */
class DirectionsService {
    /**
     * @param {google.maps.Map} googleMap - Active consumer map
     * @param {{lat: number, lng: number}} userLocation - Best known origin
     */
    constructor(googleMap, userLocation) {
        this.map = googleMap;
        this.userLocation = this.normalizeCoordinate(userLocation);
        this.directionsService = new google.maps.DirectionsService();
        this.directionsRenderer = new google.maps.DirectionsRenderer({
            map: null,
            preserveViewport: true,
            suppressMarkers: true,
            draggable: false,
            polylineOptions: {
                strokeColor: '#9400D3',
                strokeOpacity: 0.9,
                strokeWeight: 5
            }
        });
        this.activeResult = null;
        this.activeRouteIndex = 0;
        this.originMarker = null;
        this.activeStepMarker = null;
        this.activeStepPolyline = null;
        this.selectedStepKey = null;
        this.rendererListener = null;
        this.previousViewport = null;
        this.requestSequence = 0;
    }

    /**
     * Request driving route alternatives from the freshest available origin.
     * @param {{lat: number, lng: number}} destination
     * @returns {Promise<{result: google.maps.DirectionsResult, routeIndex: number, origin: Object}>}
     */
    async calculateRoute(destination) {
        const safeDestination = this.normalizeCoordinate(destination);
        if (!safeDestination) {
            const error = new Error('Invalid destination coordinates');
            error.directionsStatus = 'INVALID_DESTINATION';
            throw error;
        }

        const requestId = ++this.requestSequence;
        const origin = await this.resolveOrigin();
        if (requestId !== this.requestSequence) {
            const error = new Error('Directions request superseded');
            error.directionsStatus = 'STALE_REQUEST';
            throw error;
        }

        return new Promise((resolve, reject) => {
            this.directionsService.route({
                origin,
                destination: safeDestination,
                travelMode: google.maps.TravelMode.DRIVING,
                provideRouteAlternatives: true
            }, (result, status) => {
                if (requestId !== this.requestSequence) {
                    const error = new Error('Directions request superseded');
                    error.directionsStatus = 'STALE_REQUEST';
                    reject(error);
                    return;
                }

                if (status !== google.maps.DirectionsStatus.OK || !result || !Array.isArray(result.routes)) {
                    const error = new Error(`Directions request failed: ${status}`);
                    error.directionsStatus = status || 'UNKNOWN_ERROR';
                    reject(error);
                    return;
                }

                const routeIndex = this.findShortestRouteIndex(result.routes);
                if (routeIndex === -1) {
                    const error = new Error('Directions response contained no valid route');
                    error.directionsStatus = 'ZERO_RESULTS';
                    reject(error);
                    return;
                }

                resolve({ result, routeIndex, origin });
            });
        });
    }

    /**
     * Render the selected shortest route and populate its summary/steps UI.
     * @param {google.maps.DirectionsResult} result
     * @param {number} routeIndex
     * @param {{lat: number, lng: number}} origin
     */
    displayRoute(result, routeIndex, origin) {
        const route = result.routes[routeIndex];
        if (!route) return;

        if (!this.previousViewport) {
            this.previousViewport = {
                center: this.map.getCenter(),
                zoom: this.map.getZoom()
            };
        }

        const panel = document.getElementById('googleDirectionsPanel');
        this.clearActiveStepFocus();
        this.activeResult = result;
        this.activeRouteIndex = routeIndex;
        this.directionsRenderer.setPanel(null);
        this.directionsRenderer.setMap(this.map);
        this.directionsRenderer.setDirections(result);
        this.directionsRenderer.setRouteIndex(routeIndex);
        this.ensureOriginMarker(origin);
        this.bindRendererListener();
        this.renderRouteSummary(route);
        this.renderRouteAlternatives(result, routeIndex);
        this.setStepsVisible(false);

        const routeInfoBar = document.getElementById('routeInfoBar');
        const toggleButton = document.getElementById('toggleStepsBtn');
        if (routeInfoBar) routeInfoBar.style.display = 'flex';
        if (toggleButton) {
            toggleButton.disabled = !panel || !Array.isArray(route.legs) || route.legs.length === 0;
        }

        if (route.bounds) {
            const mobile = window.matchMedia('(max-width: 640px)').matches;
            this.map.fitBounds(route.bounds, mobile
                ? { top: 140, right: 24, bottom: 190, left: 24 }
                : { top: 170, right: 80, bottom: 140, left: 80 });
        }
    }

    /**
     * Remove the active route, generated panel content, listeners, and route marker.
     * @param {boolean} restoreViewport - Restore the view from before routing
     */
    clearRoute(restoreViewport = true) {
        this.requestSequence += 1;
        this.clearActiveStepFocus();

        if (this.rendererListener) {
            google.maps.event.removeListener(this.rendererListener);
            this.rendererListener = null;
        }
        this.directionsRenderer.setPanel(null);
        this.directionsRenderer.setMap(null);

        if (this.originMarker) {
            this.originMarker.setMap(null);
            this.originMarker = null;
        }

        const panel = document.getElementById('googleDirectionsPanel');
        if (panel) {
            panel.replaceChildren();
            panel.hidden = true;
            panel.setAttribute('aria-hidden', 'true');
        }

        const routeInfoBar = document.getElementById('routeInfoBar');
        const routeSummary = document.getElementById('routeSummary');
        const alternativesPanel = document.getElementById('routeAlternativesPanel');
        const routesButton = document.getElementById('toggleRoutesBtn');
        const routesLabel = document.getElementById('toggleRoutesLabel');
        const toggleButton = document.getElementById('toggleStepsBtn');
        const toggleLabel = document.getElementById('toggleStepsLabel');
        if (routeInfoBar) routeInfoBar.style.display = 'none';
        if (routeSummary) routeSummary.replaceChildren();
        if (alternativesPanel) {
            alternativesPanel.replaceChildren();
            alternativesPanel.hidden = true;
            alternativesPanel.setAttribute('aria-hidden', 'true');
        }
        if (routesButton) {
            routesButton.hidden = true;
            routesButton.disabled = true;
            routesButton.setAttribute('aria-expanded', 'false');
        }
        if (routesLabel) routesLabel.textContent = 'Routes';
        if (toggleButton) {
            toggleButton.disabled = true;
            toggleButton.setAttribute('aria-expanded', 'false');
        }
        if (toggleLabel) toggleLabel.textContent = 'View steps';
        document.body.classList.remove('directions-open', 'route-alternatives-open');

        this.activeResult = null;
        this.activeRouteIndex = 0;

        if (restoreViewport && this.previousViewport) {
            if (this.previousViewport.center) this.map.setCenter(this.previousViewport.center);
            if (Number.isFinite(this.previousViewport.zoom)) this.map.setZoom(this.previousViewport.zoom);
        }
        this.previousViewport = null;
    }

    /**
     * Show or hide the renderer's turn-by-turn panel.
     * @returns {boolean} Whether the panel is open after toggling
     */
    toggleSteps() {
        if (!this.activeResult) return false;
        return this.setStepsVisible(!document.body.classList.contains('directions-open'));
    }

    /** Show or hide the compact alternative-routes selector. */
    toggleRouteAlternatives() {
        if (!this.activeResult || this.activeResult.routes.length <= 1) return false;
        return this.setAlternativesVisible(!document.body.classList.contains('route-alternatives-open'));
    }

    /** Select another Google route and refresh every selected-route surface. */
    selectRouteAlternative(routeIndex) {
        const index = Number(routeIndex);
        const routes = this.activeResult && Array.isArray(this.activeResult.routes)
            ? this.activeResult.routes
            : [];
        const route = Number.isInteger(index) ? routes[index] : null;
        if (!route) return false;

        this.clearActiveStepFocus();
        this.activeRouteIndex = index;
        this.directionsRenderer.setRouteIndex(index);
        this.renderRouteSummary(route);
        this.renderRouteAlternatives(this.activeResult, index);
        this.setAlternativesVisible(false);

        if (route.bounds) {
            const mobile = window.matchMedia('(max-width: 640px)').matches;
            this.map.fitBounds(route.bounds, mobile
                ? { top: 140, right: 24, bottom: 190, left: 24 }
                : { top: 170, right: 80, bottom: 140, left: 80 });
        }
        return true;
    }

    /** Build a safe list of available Google route alternatives. */
    renderRouteAlternatives(result, activeIndex) {
        const panel = document.getElementById('routeAlternativesPanel');
        const toggleButton = document.getElementById('toggleRoutesBtn');
        const toggleLabel = document.getElementById('toggleRoutesLabel');
        const routes = result && Array.isArray(result.routes) ? result.routes : [];
        if (!panel || !toggleButton || !toggleLabel) return;

        panel.replaceChildren();
        const validRoutes = routes
            .map((route, index) => ({ route, index, totals: this.getRouteTotals(route) }))
            .filter(({ route }) => route && Array.isArray(route.legs) && route.legs.length > 0);

        if (validRoutes.length <= 1) {
            toggleButton.hidden = true;
            toggleButton.disabled = true;
            toggleButton.setAttribute('aria-expanded', 'false');
            toggleLabel.textContent = 'Routes';
            panel.hidden = true;
            panel.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('route-alternatives-open');
            return;
        }

        toggleButton.hidden = false;
        toggleButton.disabled = false;
        toggleLabel.textContent = `Routes (${validRoutes.length})`;

        const shortestDistance = Math.min(...validRoutes.map(({ totals }) => totals.distance));
        const fastestDuration = Math.min(...validRoutes.map(({ totals }) => totals.duration));

        const header = document.createElement('div');
        header.className = 'route-alternatives-header';
        const title = document.createElement('strong');
        title.textContent = 'Choose a route';
        const hint = document.createElement('span');
        hint.textContent = 'The shortest route is selected by default.';
        header.append(title, hint);
        panel.appendChild(header);

        const list = document.createElement('div');
        list.className = 'route-alternatives-list';

        validRoutes.forEach(({ route, index, totals }) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'route-alternative-option';
            option.classList.toggle('is-active', index === activeIndex);
            option.setAttribute('aria-pressed', index === activeIndex ? 'true' : 'false');
            option.addEventListener('click', () => this.selectRouteAlternative(index));

            const copy = document.createElement('span');
            copy.className = 'route-alternative-copy';
            const name = document.createElement('span');
            name.className = 'route-alternative-name';
            name.textContent = String(route.summary || `Route ${index + 1}`).trim();
            const meta = document.createElement('span');
            meta.className = 'route-alternative-meta';
            meta.textContent = `${this.formatPanelDistance(totals.distance)} · ${this.formatPanelDuration(totals.duration)}`;
            copy.append(name, meta);

            const badges = document.createElement('span');
            badges.className = 'route-alternative-badges';
            const isShortest = totals.distance === shortestDistance;
            const isFastest = totals.duration === fastestDuration;
            if (isShortest || isFastest) {
                const badge = document.createElement('span');
                badge.className = 'route-alternative-badge';
                badge.textContent = isShortest && isFastest
                    ? 'Fastest · shortest'
                    : (isFastest ? 'Fastest' : 'Shortest');
                badges.appendChild(badge);
            }
            if (index === activeIndex) {
                const selected = document.createElement('span');
                selected.className = 'route-alternative-selected';
                selected.textContent = 'Selected';
                badges.appendChild(selected);
            }

            option.append(copy, badges);
            list.appendChild(option);
        });

        panel.appendChild(list);
    }

    setAlternativesVisible(visible) {
        const panel = document.getElementById('routeAlternativesPanel');
        const toggleButton = document.getElementById('toggleRoutesBtn');
        const canShow = Boolean(
            visible && panel && toggleButton && this.activeResult &&
            Array.isArray(this.activeResult.routes) && this.activeResult.routes.length > 1
        );

        if (canShow) this.setStepsVisible(false);
        document.body.classList.toggle('route-alternatives-open', canShow);
        if (panel) {
            panel.hidden = !canShow;
            panel.setAttribute('aria-hidden', canShow ? 'false' : 'true');
        }
        if (toggleButton) toggleButton.setAttribute('aria-expanded', canShow ? 'true' : 'false');
        return canShow;
    }

    /** @returns {google.maps.DirectionsRoute|null} */
    getActiveRoute() {
        return this.activeResult ? this.activeResult.routes[this.activeRouteIndex] || null : null;
    }

    resolveOrigin() {
        const fallback = this.normalizeCoordinate({ lat: userLat, lng: userLng })
            || this.userLocation
            || { lat: 13.1475, lng: 123.7431 };

        if (!navigator.geolocation) {
            this.userLocation = fallback;
            return Promise.resolve(fallback);
        }

        return new Promise((resolve) => {
            navigator.geolocation.getCurrentPosition((position) => {
                const refreshed = this.normalizeCoordinate({
                    lat: position.coords.latitude,
                    lng: position.coords.longitude
                }) || fallback;

                userLat = refreshed.lat;
                userLng = refreshed.lng;
                this.userLocation = refreshed;

                if (window.userMarker && typeof window.userMarker.setPosition === 'function') {
                    window.userMarker.setPosition(refreshed.lat, refreshed.lng);
                    window.userMarker.show();
                }
                resolve(refreshed);
            }, (error) => {
                console.warn('Directions geolocation refresh failed; using the best known origin:', error.code);
                this.userLocation = fallback;
                resolve(fallback);
            }, {
                enableHighAccuracy: true,
                timeout: 6000,
                maximumAge: 30000
            });
        });
    }

    normalizeCoordinate(value) {
        if (!value) return null;
        const lat = Number(value.lat);
        const lng = Number(value.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng) ||
            lat < -90 || lat > 90 || lng < -180 || lng > 180) {
            return null;
        }
        return { lat, lng };
    }

    findShortestRouteIndex(routes) {
        let shortestIndex = -1;
        let shortestDistance = Infinity;

        routes.forEach((route, index) => {
            const hasLegs = route && Array.isArray(route.legs) && route.legs.length > 0;
            const distance = this.getRouteTotals(route).distance;
            if (hasLegs && Number.isFinite(distance) && distance < shortestDistance) {
                shortestDistance = distance;
                shortestIndex = index;
            }
        });

        return shortestIndex;
    }

    getRouteTotals(route) {
        return (route && Array.isArray(route.legs) ? route.legs : []).reduce((totals, leg) => {
            totals.distance += leg.distance && Number.isFinite(leg.distance.value) ? leg.distance.value : 0;
            totals.duration += leg.duration && Number.isFinite(leg.duration.value) ? leg.duration.value : 0;
            return totals;
        }, { distance: 0, duration: 0 });
    }

    renderRouteSummary(route) {
        const totals = this.getRouteTotals(route);
        const summary = document.getElementById('routeSummary');

        if (summary) {
            const distanceKm = totals.distance / 1000;
            const distanceText = `${distanceKm < 10 ? distanceKm.toFixed(1) : distanceKm.toFixed(0)} km`;
            const durationText = `${Math.max(1, Math.round(totals.duration / 60))} min`;
            const routeIcon = document.createElement('i');
            const clockIcon = document.createElement('i');
            routeIcon.className = 'fas fa-route';
            clockIcon.className = 'fas fa-clock';
            routeIcon.setAttribute('aria-hidden', 'true');
            clockIcon.setAttribute('aria-hidden', 'true');

            summary.replaceChildren(
                routeIcon,
                document.createTextNode(` ${distanceText} · `),
                clockIcon,
                document.createTextNode(` approx ${durationText}`)
            );
        }

        this.renderDirectionsPanel(route, totals);
    }

    /**
     * Render deterministic A-to-B instructions for the selected route.
     * DirectionsRenderer remains responsible only for the map polyline.
     * @param {google.maps.DirectionsRoute} route
     * @param {{distance: number, duration: number}} totals
     */
    renderDirectionsPanel(route, totals = this.getRouteTotals(route)) {
        const panel = document.getElementById('googleDirectionsPanel');
        const legs = route && Array.isArray(route.legs) ? route.legs : [];
        if (!panel) return;

        panel.replaceChildren();
        if (legs.length === 0) return;

        const steps = legs.flatMap((leg) => Array.isArray(leg.steps) ? leg.steps : []);
        const parsedSteps = steps.map((step) => ({
            step,
            instruction: this.parseStepInstruction(step.instructions)
        }));
        const firstInstruction = parsedSteps[0] ? parsedSteps[0].instruction.primary : '';
        const routeTitle = String(route.summary || firstInstruction || 'Driving directions').trim();

        const content = document.createElement('div');
        content.className = 'mfd-route';

        const header = document.createElement('header');
        header.className = 'mfd-route-header';

        const title = document.createElement('h3');
        title.className = 'mfd-route-title';
        title.textContent = routeTitle;

        const meta = document.createElement('p');
        meta.className = 'mfd-route-meta';
        meta.textContent = `${this.formatPanelDistance(totals.distance)}, ${this.formatPanelDuration(totals.duration)}`;

        header.append(title, meta);
        content.appendChild(header);

        const stepList = document.createElement('ol');
        stepList.className = 'mfd-route-steps';
        stepList.setAttribute('aria-label', 'Driving directions');

        parsedSteps.forEach(({ step, instruction }, index) => {
            const marker = index === 0 ? 'A' : this.getManeuverSymbol(step.maneuver);
            const stepKey = `route-${this.activeRouteIndex}-step-${index}`;
            stepList.appendChild(this.createDirectionRow({
                marker,
                markerLabel: index === 0 ? 'Starting point' : `Step ${index + 1}`,
                instruction: instruction.primary || 'Continue',
                detail: instruction.detail,
                distance: this.formatPanelDistance(step.distance && step.distance.value),
                endpoint: index === 0 ? 'start' : null,
                stepKey,
                position: step.start_location || step.end_location,
                path: step.path,
                markerTitle: instruction.primary || `Route step ${index + 1}`
            }));
        });

        const lastLeg = legs[legs.length - 1];
        const finalStep = parsedSteps.length > 0 ? parsedSteps[parsedSteps.length - 1].step : null;
        stepList.appendChild(this.createDirectionRow({
            marker: 'B',
            markerLabel: 'Destination',
            instruction: 'You have arrived at your destination',
            detail: String(lastLeg.end_address || '').trim(),
            distance: '0 m',
            endpoint: 'end',
            stepKey: `route-${this.activeRouteIndex}-destination`,
            position: lastLeg.end_location,
            path: finalStep && finalStep.path,
            markerTitle: 'Destination'
        }));

        content.appendChild(stepList);
        panel.appendChild(content);
    }

    /** Create one safe, keyboard-accessible three-column route row. */
    createDirectionRow({
        marker,
        markerLabel,
        instruction,
        detail,
        distance,
        endpoint,
        stepKey,
        position,
        path,
        markerTitle
    }) {
        const item = document.createElement('li');
        item.className = 'mfd-route-step';
        if (endpoint) item.classList.add(`mfd-route-step-${endpoint}`);

        const markerElement = document.createElement('span');
        markerElement.className = endpoint ? 'mfd-route-endpoint' : 'mfd-route-maneuver';
        markerElement.textContent = marker;
        markerElement.setAttribute('aria-label', markerLabel);

        const copy = document.createElement('div');
        copy.className = 'mfd-route-copy';

        const instructionElement = document.createElement('div');
        instructionElement.className = 'mfd-route-instruction';
        instructionElement.textContent = instruction;
        copy.appendChild(instructionElement);

        if (detail) {
            const detailElement = document.createElement('div');
            detailElement.className = 'mfd-route-detail';
            detailElement.textContent = detail;
            copy.appendChild(detailElement);
        }

        const distanceElement = document.createElement('span');
        distanceElement.className = 'mfd-route-distance';
        distanceElement.textContent = distance;

        item.append(markerElement, copy, distanceElement);

        const focusPosition = this.normalizeRoutePosition(position);
        if (focusPosition) {
            item.classList.add('is-interactive');
            item.dataset.stepKey = stepKey;
            item.tabIndex = 0;
            item.setAttribute('role', 'button');
            item.setAttribute('aria-label', `${markerLabel}: ${instruction}. ${distance}`);
            item.setAttribute('aria-pressed', this.selectedStepKey === stepKey ? 'true' : 'false');
            if (this.selectedStepKey === stepKey) item.classList.add('is-selected');

            const activate = () => this.focusRouteStep({
                stepKey,
                position: focusPosition,
                path,
                markerTitle: markerTitle || instruction
            });
            item.addEventListener('click', activate);
            item.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    activate();
                }
            });
        }

        return item;
    }

    /** Focus the map on one instruction and highlight its exact route segment. */
    focusRouteStep({ stepKey, position, path, markerTitle }) {
        const focusPosition = this.normalizeRoutePosition(position);
        if (!focusPosition) return false;

        this.clearActiveStepFocus();
        this.selectedStepKey = stepKey;

        const panel = document.getElementById('googleDirectionsPanel');
        if (panel) {
            panel.querySelectorAll('.mfd-route-step').forEach((row) => {
                const selected = row.dataset.stepKey === stepKey;
                row.classList.toggle('is-selected', selected);
                row.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });
        }

        this.map.panTo(focusPosition);
        const currentZoom = Number(this.map.getZoom());
        if (!Number.isFinite(currentZoom) || currentZoom < 17) this.map.setZoom(17);

        this.activeStepMarker = new google.maps.Marker({
            map: this.map,
            position: focusPosition,
            title: String(markerTitle || 'Selected route step'),
            zIndex: 1300,
            icon: {
                path: google.maps.SymbolPath.CIRCLE,
                scale: 8,
                fillColor: '#D9F855',
                fillOpacity: 1,
                strokeColor: '#191970',
                strokeWeight: 3
            }
        });

        const segmentPath = this.normalizeRoutePath(path);
        if (segmentPath.length > 1) {
            this.activeStepPolyline = new google.maps.Polyline({
                map: this.map,
                path: segmentPath,
                strokeColor: '#D9F855',
                strokeOpacity: 0.95,
                strokeWeight: 8,
                zIndex: 1200,
                clickable: false
            });
        }

        if (window.matchMedia('(max-width: 640px)').matches) {
            this.setStepsVisible(false);
        }
        return true;
    }

    /** Remove selected-step DOM state and temporary map overlays. */
    clearActiveStepFocus() {
        if (this.activeStepMarker) {
            this.activeStepMarker.setMap(null);
            this.activeStepMarker = null;
        }
        if (this.activeStepPolyline) {
            this.activeStepPolyline.setMap(null);
            this.activeStepPolyline = null;
        }

        const panel = document.getElementById('googleDirectionsPanel');
        if (panel) {
            panel.querySelectorAll('.mfd-route-step.is-selected').forEach((row) => {
                row.classList.remove('is-selected');
                row.setAttribute('aria-pressed', 'false');
            });
        }
        this.selectedStepKey = null;
    }

    normalizeRoutePosition(value) {
        if (!value) return null;
        const lat = typeof value.lat === 'function' ? value.lat() : Number(value.lat);
        const lng = typeof value.lng === 'function' ? value.lng() : Number(value.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
        return { lat, lng };
    }

    normalizeRoutePath(path) {
        return Array.isArray(path)
            ? path.map((point) => this.normalizeRoutePosition(point)).filter(Boolean)
            : [];
    }

    /** Convert Google instruction HTML to safe primary/detail text. */
    parseStepInstruction(value) {
        const source = String(value || '').trim();
        if (!source) return { primary: '', detail: '' };

        const documentFragment = new DOMParser().parseFromString(`<body>${source}</body>`, 'text/html');
        const body = documentFragment.body;
        body.querySelectorAll('script, style').forEach((element) => element.remove());

        const detailParts = Array.from(body.querySelectorAll('div'))
            .map((element) => String(element.textContent || '').replace(/\s+/g, ' ').trim())
            .filter(Boolean);
        body.querySelectorAll('div').forEach((element) => element.remove());

        return {
            primary: String(body.textContent || '').replace(/\s+/g, ' ').trim(),
            detail: detailParts.join(' ')
        };
    }

    getManeuverSymbol(maneuver) {
        const value = String(maneuver || '').toLowerCase();
        if (value.includes('uturn-left')) return '↶';
        if (value.includes('uturn-right')) return '↷';
        if (value.includes('left')) return '↰';
        if (value.includes('right')) return '↱';
        if (value.includes('merge') || value.includes('ramp')) return '↗';
        if (value.includes('roundabout')) return '↻';
        if (value.includes('straight')) return '↑';
        return '→';
    }

    formatPanelDistance(value) {
        const meters = Number(value);
        if (!Number.isFinite(meters) || meters <= 0) return '0 m';
        if (meters < 1000) {
            const precision = meters < 100 ? 0 : 1;
            return `${Number(meters.toFixed(precision))} m`;
        }
        return `${Number((meters / 1000).toFixed(1))} km`;
    }

    formatPanelDuration(value) {
        const seconds = Number(value);
        if (!Number.isFinite(seconds) || seconds <= 0) return '0 s';
        if (seconds <= 90) return `${Math.round(seconds)} s`;
        if (seconds < 3600) return `${Math.round(seconds / 60)} min`;
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.round((seconds % 3600) / 60);
        return minutes > 0 ? `${hours} hr ${minutes} min` : `${hours} hr`;
    }

    setStepsVisible(visible) {
        const panel = document.getElementById('googleDirectionsPanel');
        const toggleButton = document.getElementById('toggleStepsBtn');
        const toggleLabel = document.getElementById('toggleStepsLabel');
        const shouldShow = Boolean(visible && panel && this.activeResult);

        if (shouldShow) this.setAlternativesVisible(false);
        document.body.classList.toggle('directions-open', shouldShow);
        if (panel) {
            panel.hidden = !shouldShow;
            panel.setAttribute('aria-hidden', shouldShow ? 'false' : 'true');
        }
        if (toggleButton) toggleButton.setAttribute('aria-expanded', shouldShow ? 'true' : 'false');
        if (toggleLabel) toggleLabel.textContent = shouldShow ? 'Hide steps' : 'View steps';
        return shouldShow;
    }

    ensureOriginMarker(origin) {
        if (window.userMarker && window.userMarker.marker) return;

        this.originMarker = new google.maps.Marker({
            map: this.map,
            position: origin,
            title: 'Route start',
            zIndex: 900,
            icon: {
                path: google.maps.SymbolPath.CIRCLE,
                scale: 8,
                fillColor: '#191970',
                fillOpacity: 1,
                strokeColor: '#D9F855',
                strokeWeight: 3
            }
        });
    }

    bindRendererListener() {
        if (this.rendererListener) {
            google.maps.event.removeListener(this.rendererListener);
        }

        this.rendererListener = this.directionsRenderer.addListener('directions_changed', () => {
            const result = this.directionsRenderer.getDirections();
            const routeIndex = this.directionsRenderer.getRouteIndex();
            if (!result || !result.routes || !result.routes[routeIndex]) return;
            this.activeResult = result;
            this.activeRouteIndex = routeIndex;
            this.renderRouteSummary(result.routes[routeIndex]);
            this.renderRouteAlternatives(result, routeIndex);
        });
    }
}

function getDirectionsManager() {
    if (window.medfindDirectionsService) return window.medfindDirectionsService;
    if (!map || typeof google === 'undefined' || !google.maps ||
        typeof google.maps.DirectionsService !== 'function' ||
        typeof google.maps.DirectionsRenderer !== 'function') {
        return null;
    }

    window.medfindDirectionsService = new DirectionsService(map, { lat: userLat, lng: userLng });
    return window.medfindDirectionsService;
}

function getRoutingErrorMessage(status) {
    if (status === 'INVALID_DESTINATION') return 'This pharmacy location is invalid.';
    if (status === 'ZERO_RESULTS' || status === 'NOT_FOUND') return 'No driving route was found to this pharmacy.';
    if (status === 'OVER_QUERY_LIMIT') return 'Directions are busy right now. Please try again shortly.';
    if (status === 'REQUEST_DENIED') return 'Directions are unavailable right now.';
    if (status === 'SERVICE_UNAVAILABLE') return 'Directions are not available on this map.';
    return 'Could not calculate directions. Please try again.';
}

/**
 * Calculate and display directions to a pharmacy.
 * @param {number} lat - Latitude
 * @param {number} lng - Longitude
 */
window.getDirections = async function(lat, lng) {
    const safeLat = Number(lat);
    const safeLng = Number(lng);
    if (!Number.isFinite(safeLat) || !Number.isFinite(safeLng) ||
        safeLat < -90 || safeLat > 90 || safeLng < -180 || safeLng > 180) {
        console.error('Directions rejected invalid destination coordinates');
        window.alert(getRoutingErrorMessage('INVALID_DESTINATION'));
        return;
    }

    window.closePharmacyInfoWindow();
    const suggestionPanel = document.getElementById('nearestSuggestion');
    if (suggestionPanel) suggestionPanel.style.display = 'none';

    const manager = getDirectionsManager();
    if (!manager) {
        console.error('Directions service unavailable: required Google Maps classes or map instance are missing');
        window.alert(getRoutingErrorMessage('SERVICE_UNAVAILABLE'));
        return;
    }

    manager.clearRoute(true);

    try {
        const route = await manager.calculateRoute({ lat: safeLat, lng: safeLng });
        manager.displayRoute(route.result, route.routeIndex, route.origin);
    } catch (error) {
        const status = error && error.directionsStatus ? error.directionsStatus : 'UNKNOWN_ERROR';
        if (status === 'STALE_REQUEST') return;
        console.error('Google Directions request failed with status:', status);
        window.alert(getRoutingErrorMessage(status));
    }
};

/** Remove the active route and restore the previous map viewport. */
window.clearRoute = function() {
    const manager = getDirectionsManager();
    if (manager) manager.clearRoute(true);
};

/** Toggle the turn-by-turn directions panel for the active route. */
window.toggleDirections = function() {
    const manager = getDirectionsManager();
    if (manager) manager.toggleSteps();
};

/** Toggle the compact route-alternatives selector. */
window.toggleRouteAlternatives = function() {
    const manager = getDirectionsManager();
    if (manager) manager.toggleRouteAlternatives();
};

// Expose the reusable editor and initialization callback for Google Maps and tests.
window.PharmacyLocationEditor = PharmacyLocationEditor;
window.initializeMap = initializeMap;
window.initializeLocationEditor = initializeLocationEditor;
window.LOCATION_PICKER_CONTAINER_IDS = LOCATION_PICKER_CONTAINER_IDS;

// ============================================
// Search Data Helpers
// ============================================

/** Auto-dismiss delay for the transient "no results" map notice. */
const MEDFIND_NO_RESULTS_TIMEOUT_MS = 3000;

/** Fade-out duration for the "no results" toast, matched to its CSS transition. */
const MEDFIND_NO_RESULTS_FADE_MS = 200;

/** Longest medicine name rendered in the "no results" toast before truncation. */
const MEDFIND_NO_RESULTS_QUERY_MAX = 40;

/**
 * Resolve the live pharmacy dataset.
 * The blade template declares `pharmaciesData` and mutates it in place for
 * real-time inventory updates, so it is always preferred over stale copies.
 * @returns {Array} Pharmacy records
 */
function getPharmaciesDataset() {
    if (typeof pharmaciesData !== 'undefined' && Array.isArray(pharmaciesData)) {
        return pharmaciesData;
    }
    if (Array.isArray(window.pharmaciesData)) {
        return window.pharmaciesData;
    }
    return [];
}

/**
 * Resolve the active consumer map instance.
 * @returns {google.maps.Map|null} Active map
 */
function getActiveMap() {
    return map || window.map || null;
}

/**
 * Resolve the medicine-name list used by autocomplete.
 * @returns {Array<string>} Medicine names
 */
function getMedicineNameDataset() {
    if (typeof allMedicineNames !== 'undefined' && Array.isArray(allMedicineNames)) {
        return allMedicineNames;
    }
    if (Array.isArray(window.allMedicineNames)) {
        return window.allMedicineNames;
    }
    return [];
}

/**
 * Normalize display text with an optional fallback.
 * @param {*} value - Candidate text
 * @param {string} fallback - Fallback when blank
 * @returns {string}
 */
function normalizeSearchText(value, fallback = '') {
    if (value === null || value === undefined) return fallback;
    const text = String(value).trim();
    return text || fallback;
}

/**
 * Normalize a stock quantity to a non-negative integer.
 * @param {*} value - Candidate stock
 * @returns {number}
 */
function normalizeStockQuantity(value) {
    const stock = Number(value);
    return Number.isFinite(stock) && stock > 0 ? Math.floor(stock) : 0;
}

/**
 * Normalize a price to a non-negative number.
 * @param {*} value - Candidate price
 * @returns {number|null}
 */
function normalizeMedicinePrice(value) {
    const price = Number(value);
    return Number.isFinite(price) && price >= 0 ? price : null;
}

/**
 * Format a price using the peso sign expected by the suggestion panel.
 * @param {*} value - Candidate price
 * @returns {string}
 */
function formatPesoPrice(value) {
    const price = normalizeMedicinePrice(value);
    return `\u20B1${(price === null ? 0 : price).toFixed(2)}`;
}

/**
 * Normalize a geographic coordinate pair.
 * @param {*} lat - Candidate latitude
 * @param {*} lng - Candidate longitude
 * @returns {{lat: number, lng: number}|null}
 */
function normalizeCoordinates(lat, lng) {
    const safeLat = Number(lat);
    const safeLng = Number(lng);
    if (!Number.isFinite(safeLat) || !Number.isFinite(safeLng)) return null;
    if (safeLat < -90 || safeLat > 90 || safeLng < -180 || safeLng > 180) return null;
    return { lat: safeLat, lng: safeLng };
}

/**
 * Collect the in-stock medicines of a pharmacy that match a search query.
 * An empty query matches every in-stock medicine.
 * @param {Object} pharmacy - Pharmacy record
 * @param {string} query - Lowercase search query
 * @returns {Array<Object>} Matching medicines
 */
function getMatchingMedicines(pharmacy, query) {
    if (!pharmacy || !Array.isArray(pharmacy.medicines)) return [];

    return pharmacy.medicines.filter((medicine) => {
        if (!medicine || normalizeStockQuantity(medicine.stock) <= 0) return false;
        if (!query) return true;
        return normalizeSearchText(medicine.name).toLowerCase().includes(query);
    });
}

/**
 * Pick the cheapest medicine from a matching set.
 * @param {Array<Object>} medicines - Matching medicines
 * @returns {Object|null} Cheapest medicine
 */
function getCheapestMedicine(medicines) {
    if (!Array.isArray(medicines) || medicines.length === 0) return null;

    return medicines.reduce((cheapest, medicine) => {
        const price = normalizeMedicinePrice(medicine.price);
        const cheapestPrice = normalizeMedicinePrice(cheapest.price);
        if (price === null) return cheapest;
        if (cheapestPrice === null || price < cheapestPrice) return medicine;
        return cheapest;
    }, medicines[0]);
}

// ============================================
// SearchFilterService Class
// ============================================

/**
 * Filters pharmacies by medicine availability, drives Google marker visibility,
 * keeps the stats badges in sync, and triggers the nearest-pharmacy panel.
 */
class SearchFilterService {
    /**
     * @param {Array} pharmaciesData - Pharmacy records
     * @param {PharmacyMarkerManager} markerManager - Marker manager for visibility updates
     */
    constructor(pharmaciesData, markerManager) {
        this.pharmaciesData = Array.isArray(pharmaciesData) ? pharmaciesData : [];
        this.markerManager = markerManager || null;
        this.query = '';
        this.filteredPharmacies = this.pharmaciesData.slice();
        this.noResultsToast = null;
        this.noResultsFadingToast = null;
        this.noResultsTimer = null;
        this.noResultsFadeTimer = null;
    }

    /**
     * Resolve the dataset, preferring the live global array over the constructor copy.
     * @returns {Array} Pharmacy records
     */
    getDataset() {
        const liveData = getPharmaciesDataset();
        if (liveData.length > 0) return liveData;
        return this.pharmaciesData;
    }

    /**
     * Resolve the marker manager, falling back to the globally published instance.
     * @returns {Object|null} Marker manager
     */
    getMarkerManager() {
        if (this.markerManager && typeof this.markerManager.filterMarkers === 'function') {
            return this.markerManager;
        }
        const globalManager = window.pharmacyMarkerManager;
        return globalManager && typeof globalManager.filterMarkers === 'function' ? globalManager : null;
    }

    /**
     * Filter pharmacies by medicine name and stock, then refresh every search surface.
     * @param {*} rawQuery - Raw query string
     * @returns {Array} Filtered pharmacies
     */
    performSearch(rawQuery) {
        const dataset = this.getDataset();
        const query = normalizeSearchText(rawQuery).toLowerCase();

        const filtered = query
            ? dataset.filter((pharmacy) => getMatchingMedicines(pharmacy, query).length > 0)
            : dataset.slice();

        this.query = query;
        this.filteredPharmacies = filtered;

        // Keep the shared globals authoritative for other map surfaces.
        currentSearchQuery = query;
        currentFilteredPharmacies = filtered;

        // A stale popup would describe a pharmacy that may no longer be visible.
        if (typeof window.closePharmacyInfoWindow === 'function') {
            window.closePharmacyInfoWindow();
        }

        this.updateBadges(query, filtered, dataset);

        const markerManager = this.getMarkerManager();
        if (markerManager) {
            markerManager.filterMarkers(filtered);
        }

        // Always tear down the previous notice so repeat searches cannot stack them.
        this.hideNoResultsNotice();
        if (query && filtered.length === 0) {
            this.showNoResultsNotice(query);
        }

        const suggestionPanel = getSuggestionPanelManager();
        if (suggestionPanel) {
            if (query && filtered.length > 0) {
                suggestionPanel.show(filtered, query);
            } else {
                suggestionPanel.hide();
            }
        }

        return filtered;
    }

    /**
     * Reset the search input and restore all markers and badges.
     * @returns {Array} Full pharmacy list
     */
    clearSearch() {
        const input = document.getElementById('medicineSearch');
        if (input) input.value = '';
        hideAutocomplete();
        return this.performSearch('');
    }

    /** @returns {string} Active lowercase query */
    getCurrentQuery() {
        return this.query;
    }

    /** @returns {Array} Pharmacies matching the active query */
    getFilteredPharmacies() {
        return this.filteredPharmacies;
    }

    /**
     * Update the result badge, pharmacy count, and matching stock total.
     * @param {string} query - Active lowercase query
     * @param {Array} filtered - Filtered pharmacies
     * @param {Array} dataset - Full dataset
     */
    updateBadges(query, filtered, dataset) {
        const resultBadge = document.getElementById('searchResultBadge');
        if (resultBadge) {
            if (!query) {
                resultBadge.textContent = 'All locations';
            } else if (filtered.length === 0) {
                resultBadge.textContent = `No results for "${this.getBadgeQuery(query)}"`;
            } else {
                resultBadge.textContent = `${filtered.length} found`;
            }
        }

        const pharmacyCountBadge = document.getElementById('pharmacyCount');
        if (pharmacyCountBadge) {
            pharmacyCountBadge.textContent = String(filtered.length);
        }

        const stockCountBadge = document.getElementById('medicineStockCount');
        if (stockCountBadge) {
            const source = query ? filtered : dataset;
            const totalStock = source.reduce((total, pharmacy) => {
                return total + getMatchingMedicines(pharmacy, query)
                    .reduce((sum, medicine) => sum + normalizeStockQuantity(medicine.stock), 0);
            }, 0);
            stockCountBadge.textContent = String(totalStock);
        }
    }

    /**
     * Trim a query for badge display so long searches cannot break the stats bar.
     * @param {string} query - Active query
     * @returns {string}
     */
    getBadgeQuery(query) {
        const maxLength = 24;
        return query.length > maxLength ? `${query.slice(0, maxLength - 1)}\u2026` : query;
    }

    /**
     * Trim a query for the toast detail line so long searches stay readable.
     * @param {string} query - Active query
     * @returns {string}
     */
    getNoticeQuery(query) {
        const text = query === null || query === undefined ? '' : String(query);
        return text.length > MEDFIND_NO_RESULTS_QUERY_MAX
            ? `${text.slice(0, MEDFIND_NO_RESULTS_QUERY_MAX - 1)}\u2026`
            : text;
    }

    /**
     * Show a brief custom toast over the map when nothing stocks the medicine.
     * Rendered as app-owned DOM so it inherits no Google InfoWindow chrome.
     * @param {string} query - Active query
     */
    showNoResultsNotice(query) {
        if (typeof document === 'undefined' || !document.body) return;

        // Drop any notice still on screen so repeat searches cannot stack them.
        this.hideNoResultsNotice();

        const toast = document.createElement('div');
        toast.className = 'medfind-no-results-toast';
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');

        const badge = document.createElement('span');
        badge.className = 'mnr-badge';

        const icon = document.createElement('i');
        icon.className = 'fas fa-search';
        icon.setAttribute('aria-hidden', 'true');
        badge.appendChild(icon);

        const body = document.createElement('span');
        body.className = 'mnr-body';

        const title = document.createElement('span');
        title.className = 'mnr-title';
        title.textContent = 'No pharmacies found';

        const detail = document.createElement('span');
        detail.className = 'mnr-detail';
        detail.textContent = `No nearby pharmacy stocks "${this.getNoticeQuery(query)}" right now.`;

        body.append(title, detail);
        toast.append(badge, body);

        document.body.appendChild(toast);
        this.noResultsToast = toast;

        // Paint the hidden state first so the entry transition actually runs.
        if (typeof window.requestAnimationFrame === 'function') {
            window.requestAnimationFrame(() => {
                if (this.noResultsToast === toast) toast.classList.add('is-visible');
            });
        } else {
            toast.classList.add('is-visible');
        }

        this.noResultsTimer = window.setTimeout(() => {
            this.noResultsTimer = null;
            this.hideNoResultsNotice();
        }, MEDFIND_NO_RESULTS_TIMEOUT_MS);
    }

    /** Fade out the transient toast and cancel its pending timers. */
    hideNoResultsNotice() {
        if (this.noResultsTimer !== null) {
            window.clearTimeout(this.noResultsTimer);
            this.noResultsTimer = null;
        }

        // Finish any in-flight fade immediately so nothing lingers in the DOM.
        this.removeFadingNotice();

        const toast = this.noResultsToast;
        if (!toast) return;
        this.noResultsToast = null;

        toast.classList.remove('is-visible');
        this.noResultsFadingToast = toast;
        this.noResultsFadeTimer = window.setTimeout(() => {
            this.removeFadingNotice();
        }, MEDFIND_NO_RESULTS_FADE_MS);
    }

    /** Detach a fading toast from the DOM and clear its fade timer. */
    removeFadingNotice() {
        if (this.noResultsFadeTimer !== null) {
            window.clearTimeout(this.noResultsFadeTimer);
            this.noResultsFadeTimer = null;
        }

        const toast = this.noResultsFadingToast;
        this.noResultsFadingToast = null;
        if (toast && toast.parentNode) toast.parentNode.removeChild(toast);
    }
}

// ============================================
// SuggestionPanelManager Class
// ============================================

/**
 * Renders the nearest pharmacies that stock the searched medicine.
 * Reuses the dashboard's existing suggestion-panel styling.
 */
class SuggestionPanelManager {
    /**
     * @param {google.maps.Map} googleMap - Active consumer map
     * @param {Array} pharmaciesData - Pharmacy records
     */
    constructor(googleMap, pharmaciesData) {
        this.map = googleMap || null;
        this.pharmaciesData = Array.isArray(pharmaciesData) ? pharmaciesData : [];
        this.maxSuggestions = 3;
    }

    /** @returns {HTMLElement|null} The panel element when present on the page */
    getPanel() {
        return document.getElementById('nearestSuggestion');
    }

    /**
     * Display the nearest matching pharmacies for the active query.
     * @param {Array} filteredPharmacies - Pharmacies matching the query
     * @param {string} query - Active lowercase query
     * @returns {boolean} Whether the panel is now visible
     */
    show(filteredPharmacies, query) {
        const panel = this.getPanel();
        if (!panel) return false;

        const activeQuery = normalizeSearchText(query).toLowerCase();
        if (!activeQuery || !Array.isArray(filteredPharmacies) || filteredPharmacies.length === 0) {
            this.hide();
            return false;
        }

        const ranked = this.rankPharmacies(filteredPharmacies, activeQuery);
        if (ranked.length === 0) {
            this.hide();
            return false;
        }

        const nodes = [this.buildCloseButton(), this.buildHeader(activeQuery)];
        ranked.forEach((entry) => nodes.push(this.buildItem(entry)));

        panel.replaceChildren(...nodes);
        panel.style.display = 'block';
        return true;
    }

    /** Hide and empty the suggestion panel. */
    hide() {
        const panel = this.getPanel();
        if (!panel) return;
        panel.replaceChildren();
        panel.style.display = 'none';
    }

    /** @returns {boolean} Whether the panel is currently displayed */
    isVisible() {
        const panel = this.getPanel();
        return Boolean(panel && panel.style.display !== 'none');
    }

    /**
     * Sort matching pharmacies by distance and keep the nearest few.
     * @param {Array} filteredPharmacies - Pharmacies matching the query
     * @param {string} query - Active lowercase query
     * @returns {Array<Object>} Ranked suggestion entries
     */
    rankPharmacies(filteredPharmacies, query) {
        return filteredPharmacies
            .map((pharmacy) => {
                const coordinates = normalizeCoordinates(pharmacy && pharmacy.lat, pharmacy && pharmacy.lng);
                if (!coordinates) return null;

                const medicine = getCheapestMedicine(getMatchingMedicines(pharmacy, query));
                if (!medicine) return null;

                return {
                    id: normalizePharmacyId(pharmacy.id),
                    name: normalizeSearchText(pharmacy.name, 'Pharmacy'),
                    lat: coordinates.lat,
                    lng: coordinates.lng,
                    distance: calculateDistance(userLat, userLng, coordinates.lat, coordinates.lng),
                    price: formatPesoPrice(medicine.price),
                    stock: normalizeStockQuantity(medicine.stock)
                };
            })
            .filter((entry) => entry !== null && Number.isFinite(entry.distance))
            .sort((a, b) => a.distance - b.distance)
            .slice(0, this.maxSuggestions);
    }

    /** @returns {HTMLButtonElement} Dismiss control */
    buildCloseButton() {
        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'close-suggestion';
        closeButton.setAttribute('aria-label', 'Dismiss nearest pharmacy suggestions');
        closeButton.textContent = '\u00D7';
        closeButton.addEventListener('click', () => this.hide());
        return closeButton;
    }

    /**
     * @param {string} query - Active query
     * @returns {HTMLElement} Panel header
     */
    buildHeader(query) {
        const header = document.createElement('div');
        header.className = 'suggestion-header';

        const icon = document.createElement('i');
        icon.className = 'fas fa-location-arrow';
        icon.setAttribute('aria-hidden', 'true');

        const label = document.createElement('span');
        label.textContent = `Nearest pharmacy with "${query}" in stock`;

        header.append(icon, label);
        return header;
    }

    /**
     * @param {Object} entry - Ranked suggestion entry
     * @returns {HTMLElement} Suggestion row
     */
    buildItem(entry) {
        const item = document.createElement('div');
        item.className = 'suggestion-item';

        const info = document.createElement('div');
        info.className = 'suggestion-info';

        const name = document.createElement('div');
        name.className = 'suggestion-name';
        name.textContent = entry.name;

        const meta = document.createElement('div');
        meta.className = 'suggestion-meta';
        meta.appendChild(document.createTextNode(`${entry.distance.toFixed(1)} km away \u00B7 `));

        const price = document.createElement('span');
        price.className = 'price';
        price.textContent = entry.price;
        meta.appendChild(price);

        meta.appendChild(document.createTextNode(` \u00B7 ${entry.stock} in stock`));

        info.append(name, meta);

        const actions = document.createElement('div');
        actions.className = 'suggestion-actions';
        actions.append(this.buildDirectionsButton(entry), this.buildViewLink(entry));

        item.append(info, actions);
        return item;
    }

    /**
     * @param {Object} entry - Ranked suggestion entry
     * @returns {HTMLButtonElement} "Go" button wired to the routing service
     */
    buildDirectionsButton(entry) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn-directions';

        const icon = document.createElement('i');
        icon.className = 'fas fa-directions';
        icon.setAttribute('aria-hidden', 'true');

        button.append(icon, document.createTextNode(' Go'));
        button.setAttribute('aria-label', `Get directions to ${entry.name}`);
        button.addEventListener('click', () => {
            if (typeof window.getDirections === 'function') {
                window.getDirections(entry.lat, entry.lng);
            }
        });
        return button;
    }

    /**
     * @param {Object} entry - Ranked suggestion entry
     * @returns {HTMLElement} "View" link to the pharmacy detail page
     */
    buildViewLink(entry) {
        const link = document.createElement('a');
        link.className = 'btn-view';

        const icon = document.createElement('i');
        icon.className = 'fas fa-store';
        icon.setAttribute('aria-hidden', 'true');

        link.append(icon, document.createTextNode(' View'));
        link.setAttribute('aria-label', `View ${entry.name} products`);

        if (entry.id === null) {
            link.setAttribute('role', 'link');
            link.setAttribute('aria-disabled', 'true');
        } else {
            link.href = `/consumer/pharmacy/${encodeURIComponent(String(entry.id))}`;
        }
        return link;
    }
}

// ============================================
// Search Service Accessors
// ============================================

/**
 * Lazily build the shared search service so it always sees the newest managers.
 * @returns {SearchFilterService}
 */
function getSearchFilterService() {
    if (!window.medfindSearchService) {
        window.medfindSearchService = new SearchFilterService(
            getPharmaciesDataset(),
            window.pharmacyMarkerManager || null
        );
    }
    return window.medfindSearchService;
}

/**
 * Lazily build the suggestion panel manager when the panel exists.
 * @returns {SuggestionPanelManager|null}
 */
function getSuggestionPanelManager() {
    if (!document.getElementById('nearestSuggestion')) return null;
    if (!window.medfindSuggestionPanel) {
        window.medfindSuggestionPanel = new SuggestionPanelManager(getActiveMap(), getPharmaciesDataset());
    }
    return window.medfindSuggestionPanel;
}

// ============================================
// Medicine Name Autocomplete
// ============================================

/** Index of the keyboard-highlighted autocomplete option. */
let autocompleteSelectedIndex = -1;

/** Maximum number of autocomplete suggestions shown at once. */
const MEDFIND_AUTOCOMPLETE_LIMIT = 6;

/**
 * Rank medicine names for a query: exact, prefix, word-prefix, then substring.
 * @param {string} query - Lowercase query
 * @returns {Array<string>} Ranked medicine names
 */
function rankMedicineNameMatches(query) {
    const names = getMedicineNameDataset();
    if (!query || names.length === 0) return [];

    const isShortQuery = query.length <= 2;

    return names
        .filter((name) => {
            const candidate = normalizeSearchText(name).toLowerCase();
            if (!candidate) return false;
            if (isShortQuery) {
                return candidate.startsWith(query) ||
                    candidate.split(/\s+/).some((word) => word.startsWith(query));
            }
            return candidate.includes(query);
        })
        .map((name) => {
            const candidate = String(name).toLowerCase();
            let rank = 3;
            if (candidate === query) {
                rank = 0;
            } else if (candidate.startsWith(query)) {
                rank = 1;
            } else if (candidate.split(/\s+/).some((word) => word.startsWith(query))) {
                rank = 2;
            }
            return { name: String(name), rank };
        })
        .sort((a, b) => (a.rank !== b.rank ? a.rank - b.rank : a.name.localeCompare(b.name)))
        .slice(0, MEDFIND_AUTOCOMPLETE_LIMIT)
        .map((entry) => entry.name);
}

/**
 * Append text to a container, wrapping query matches in <strong> nodes.
 * Uses text nodes only so medicine names are never parsed as markup.
 * @param {HTMLElement} container - Target element
 * @param {string} text - Full label text
 * @param {string} query - Lowercase query
 */
function appendHighlightedText(container, text, query) {
    if (!query) {
        container.appendChild(document.createTextNode(text));
        return;
    }

    const lowerText = text.toLowerCase();
    let position = 0;

    while (position < text.length) {
        const matchIndex = lowerText.indexOf(query, position);
        if (matchIndex === -1) {
            container.appendChild(document.createTextNode(text.slice(position)));
            return;
        }
        if (matchIndex > position) {
            container.appendChild(document.createTextNode(text.slice(position, matchIndex)));
        }
        const match = document.createElement('strong');
        match.textContent = text.slice(matchIndex, matchIndex + query.length);
        container.appendChild(match);
        position = matchIndex + query.length;
    }
}

/** Close and empty the autocomplete dropdown. */
function hideAutocomplete() {
    const list = document.getElementById('autocompleteList');
    autocompleteSelectedIndex = -1;
    if (!list) return;

    list.replaceChildren();
    list.style.display = 'none';
    list.classList.remove('active');
}

/**
 * Apply the highlighted state to one autocomplete option.
 * @param {NodeListOf<HTMLElement>} items - Autocomplete options
 * @param {number} index - Index to highlight
 */
function highlightAutocompleteItem(items, index) {
    items.forEach((item, itemIndex) => {
        const isActive = itemIndex === index;
        item.classList.toggle('is-active', isActive);
        item.setAttribute('aria-selected', isActive ? 'true' : 'false');
        if (isActive) item.scrollIntoView({ block: 'nearest' });
    });
}

/**
 * Render medicine-name suggestions for the current search input value.
 */
function showAutocomplete() {
    const input = document.getElementById('medicineSearch');
    const list = document.getElementById('autocompleteList');
    if (!input || !list) return;

    const query = input.value.trim().toLowerCase();
    if (!query) {
        hideAutocomplete();
        return;
    }

    const matches = rankMedicineNameMatches(query);
    if (matches.length === 0) {
        hideAutocomplete();
        return;
    }

    const options = matches.map((name, index) => {
        const option = document.createElement('div');
        option.className = 'autocomplete-item';
        option.setAttribute('role', 'option');
        option.setAttribute('aria-selected', 'false');
        option.dataset.medicine = name;
        option.dataset.index = String(index);
        appendHighlightedText(option, name, query);

        const select = (event) => {
            event.preventDefault();
            event.stopPropagation();
            selectMedicineFromAutocomplete(name);
        };
        option.addEventListener('mousedown', select);
        option.addEventListener('click', select);
        option.addEventListener('touchstart', select, { passive: false });

        return option;
    });

    list.setAttribute('role', 'listbox');
    list.replaceChildren(...options);
    list.style.display = 'block';
    list.classList.add('active');
    autocompleteSelectedIndex = -1;
}

/**
 * Fill the search input from an autocomplete option and search immediately.
 * @param {string} medicineName - Selected medicine name
 */
function selectMedicineFromAutocomplete(medicineName) {
    const name = normalizeSearchText(medicineName);
    if (!name) return;

    const input = document.getElementById('medicineSearch');
    if (input) input.value = name;

    hideAutocomplete();
    getSearchFilterService().performSearch(name);
}

// ============================================
// Search UI Wiring
// ============================================

/**
 * Attach search, autocomplete, and dismissal handlers.
 * Pages without the consumer search controls are left untouched.
 */
function initializeMedicineSearchUI() {
    const input = document.getElementById('medicineSearch');
    const searchButton = document.getElementById('searchBtn');
    if (!input && !searchButton) return;

    if (searchButton && searchButton.dataset.medfindSearchBound !== 'true') {
        searchButton.dataset.medfindSearchBound = 'true';
        searchButton.addEventListener('click', (event) => {
            event.preventDefault();
            hideAutocomplete();
            getSearchFilterService().performSearch(input ? input.value : '');
        });
    }

    if (input && input.dataset.medfindSearchBound !== 'true') {
        input.dataset.medfindSearchBound = 'true';
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-controls', 'autocompleteList');

        input.addEventListener('input', () => showAutocomplete());

        input.addEventListener('keydown', (event) => {
            const list = document.getElementById('autocompleteList');
            const items = list && list.classList.contains('active')
                ? list.querySelectorAll('.autocomplete-item')
                : null;
            const hasOptions = Boolean(items && items.length > 0);

            if (event.key === 'ArrowDown' && hasOptions) {
                event.preventDefault();
                autocompleteSelectedIndex = (autocompleteSelectedIndex + 1) % items.length;
                highlightAutocompleteItem(items, autocompleteSelectedIndex);
                return;
            }

            if (event.key === 'ArrowUp' && hasOptions) {
                event.preventDefault();
                autocompleteSelectedIndex =
                    (autocompleteSelectedIndex - 1 + items.length) % items.length;
                highlightAutocompleteItem(items, autocompleteSelectedIndex);
                return;
            }

            if (event.key === 'Escape') {
                hideAutocomplete();
                return;
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                if (hasOptions && autocompleteSelectedIndex >= 0) {
                    const selected = items[autocompleteSelectedIndex];
                    const medicineName = selected ? selected.dataset.medicine : '';
                    if (medicineName) {
                        selectMedicineFromAutocomplete(medicineName);
                        return;
                    }
                }
                hideAutocomplete();
                getSearchFilterService().performSearch(input.value);
            }
        });
    }

    if (document.body && document.body.dataset.medfindSearchDismissBound !== 'true') {
        document.body.dataset.medfindSearchDismissBound = 'true';
        document.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof Element)) {
                hideAutocomplete();
                return;
            }
            if (target.closest('.search-card-minimal') || target.closest('.autocomplete-items')) {
                return;
            }
            hideAutocomplete();
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeMedicineSearchUI, { once: true });
} else {
    initializeMedicineSearchUI();
}

// ============================================
// Global Search API
// ============================================

/**
 * Run a medicine search. Called without arguments by the real-time inventory
 * listener, in which case the current input value is used.
 * @param {*} query - Optional explicit query
 * @returns {Array} Filtered pharmacies
 */
window.performSearch = function(query) {
    const input = document.getElementById('medicineSearch');
    const value = typeof query === 'string' ? query : (input ? input.value : '');
    return getSearchFilterService().performSearch(value);
};

/**
 * Clear the search input and restore all markers and badges.
 * @returns {Array} Full pharmacy list
 */
window.clearSearch = function() {
    return getSearchFilterService().clearSearch();
};

/** Hide the nearest-pharmacy suggestion panel. */
window.hideSuggestionPanel = function() {
    const panel = getSuggestionPanelManager();
    if (panel) panel.hide();
};

// Preserve the legacy public autocomplete callbacks used by the dashboard.
window.showAutocomplete = showAutocomplete;
window.hideAutocomplete = hideAutocomplete;
window.selectMedicineFromAutocomplete = selectMedicineFromAutocomplete;
window.selectMedicine = selectMedicineFromAutocomplete;

// Expose the search classes for reuse and tests.
window.SearchFilterService = SearchFilterService;
window.SuggestionPanelManager = SuggestionPanelManager;
