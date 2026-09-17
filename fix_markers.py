import re

# Read the backup file
with open(r'c:\medfind\public\js\medfind-google.js.backup', 'r', encoding='utf-8') as f:
    lines = f.readlines()

# Find line numbers for methods to replace
create_marker_start = None
create_marker_end = None
add_pulsing_start = None
add_pulsing_end = None
create_icon_start = None
create_icon_end = None
pharmacy_create_markers_start = None
pharmacy_create_markers_end = None

for i, line in enumerate(lines):
    # UserLocationMarker.createMarker()
    if 'Create the user location marker with custom pulsing icon' in line:
        create_marker_start = i - 1  # Include the /** comment
    if create_marker_start and 'this.addPulsingAnimation();' in line:
        create_marker_end = i + 2  # Include closing brace
        
    # UserLocationMarker.addPulsingAnimation()
    if 'Add CSS animation for pulsing effect' in line:
        add_pulsing_start = i - 1
    if add_pulsing_start and add_pulsing_end is None and 'document.head.appendChild(style);' in line:
        add_pulsing_end = i + 2
        
    # PharmacyMarkerManager.createMarkerIcon()
    if 'Create marker icon based on pharmacy logo availability' in line:
        create_icon_start = i - 1
    if create_icon_start and create_icon_end is None and 'scale: 24  // Makes it 48px diameter' in line:
        # Find the closing braces
        for j in range(i+1, min(i+10, len(lines))):
            if lines[j].strip() == '}':
                create_icon_end = j + 1
                break
                
    # PharmacyMarkerManager.createMarkers()
    if 'Create markers for all pharmacies' in line:
        pharmacy_create_markers_start = i - 1
    if pharmacy_create_markers_start and 'console.log(`Created ${this.markers.length} pharmacy markers`);' in line:
        pharmacy_create_markers_end = i + 2

print(f"createMarker: {create_marker_start} to {create_marker_end}")
print(f"addPulsing: {add_pulsing_start} to {add_pulsing_end}")
print(f"createIcon: {create_icon_start} to {create_icon_end}")
print(f"pharmacyCreateMarkers: {pharmacy_create_markers_start} to {pharmacy_create_markers_end}")
