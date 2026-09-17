const fs = require('fs');

// Read the backup
let content = fs.readFileSync('c:\\medfind\\public\\js\\medfind-google.js.backup', 'utf8');

// Fix 1: Replace the createMarkerIcon() call in createMarkers() with inline logic
const oldCreateMarkers = `            // Create marker icon
            const icon = this.createMarkerIcon(pharmacy);
            
            // Create marker
            const marker = new google.maps.Marker({
                position: { lat: pharmacy.lat, lng: pharmacy.lng },
                map: this.map,
                icon: icon,
                title: pharmacy.name,
                zIndex: 100, // Lower than user location marker
                optimized: false // Required for custom icons to display properly
            });`;

const newCreateMarkers = `            // Check if pharmacy has logo
            const hasLogo = pharmacy.logo && pharmacy.logo.trim() !== '';
            
            console.log(\`Creating marker for \${pharmacy.name} - Logo: \${pharmacy.logo || 'null'}\`);
            
            // Create marker with INLINE icon (WORKING APPROACH)
            let marker;
            
            if (hasLogo) {
                // Pharmacy has logo - use logo image
                marker = new google.maps.Marker({
                    position: { lat: pharmacy.lat, lng: pharmacy.lng },
                    map: this.map,
                    icon: {
                        url: pharmacy.logo,
                        scaledSize: new google.maps.Size(48, 48),
                        anchor: new google.maps.Point(24, 48)
                    },
                    title: pharmacy.name,
                    zIndex: 100
                });
            } else {
                // No logo - use purple circle (INLINE, not method call)
                marker = new google.maps.Marker({
                    position: { lat: pharmacy.lat, lng: pharmacy.lng },
                    map: this.map,
                    icon: {
                        path: google.maps.SymbolPath.CIRCLE,
                        scale: 12,
                        fillColor: '#9400D3',  // Purple
                        fillOpacity: 1.0,
                        strokeColor: '#191970',  // Navy stroke
                        strokeWeight: 2
                    },
                    title: pharmacy.name,
                    zIndex: 100
                });
            }`;

content = content.replace(oldCreateMarkers, newCreateMarkers);

// Fix 2: Remove createMarkerIcon() method - everything from its JSDoc to closing brace
const createMarkerIconRegex = /    \/\*\*\r?\n     \* Create marker icon based on pharmacy logo availability[\s\S]*?        \}\r?\n    \}\r?\n/;
content = content.replace(createMarkerIconRegex, '');

// Fix 3: Fix UserLocationMarker.createMarker() - replace SVG with simple circle
const oldUserMarker = /\/\*\*\r?\n     \* Create the user location marker with custom pulsing icon[\s\S]*?this\.addPulsingAnimation\(\);\r?\n    \}/;

const newUserMarker = `/**
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
    }`;

content = content.replace(oldUserMarker, newUserMarker);

// Fix 4: Remove addPulsingAnimation() method
const addPulsingRegex = /    \/\*\*\r?\n     \* Add CSS animation for pulsing effect[\s\S]*?document\.head\.appendChild\(style\);\r?\n    \}\r?\n/;
content = content.replace(addPulsingRegex, '');

// Write the fixed file
fs.writeFileSync('c:\\medfind\\public\\js\\medfind-google.js', content, 'utf8');
console.log('Fixed file written successfully');
