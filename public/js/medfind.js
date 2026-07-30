// ============================================
// MEDFIND - FULL JS LOGIC WITH WORKING POPUP BUTTONS
// ============================================

let map;
let markers = [];
let userLat = 13.1475;
let userLng = 123.7431;
let currentSearchQuery = "";
let currentFilteredPharmacies = [];
let chatVisible = false;

function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371;
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

function closePopup() {
    if (map) map.closePopup();
}

function performSearch() {
    const input = document.getElementById("medicineSearch");
    const query = input ? input.value.trim() : "";
    currentSearchQuery = query.toLowerCase();

    const autocompleteList = document.getElementById("autocompleteList");
    if (autocompleteList) autocompleteList.style.display = "none";

    const data = typeof pharmaciesData !== "undefined" ? pharmaciesData : [];

    if (!currentSearchQuery) {
        currentFilteredPharmacies = data;
        const badge = document.getElementById("searchResultBadge");
        if (badge) badge.innerHTML = "All pharmacies";
    } else {
        currentFilteredPharmacies = data.filter((pharmacy) => {
            return pharmacy.medicines.some(
                (med) =>
                    med.name.toLowerCase().includes(currentSearchQuery) &&
                    med.stock > 0
            );
        });

        const badge = document.getElementById("searchResultBadge");
        if (badge) {
            badge.innerHTML =
                currentFilteredPharmacies.length === 0
                    ? "No results"
                    : `${currentFilteredPharmacies.length} found`;
        }
    }

    let totalStock = 0;
    currentFilteredPharmacies.forEach((ph) => {
        const meds = currentSearchQuery
            ? ph.medicines.filter(
                  (m) =>
                      m.name.toLowerCase().includes(currentSearchQuery) &&
                      m.stock > 0
              )
            : ph.medicines.filter((m) => m.stock > 0);
        totalStock += meds.reduce((sum, m) => sum + m.stock, 0);
    });

    const phCount = document.getElementById("pharmacyCount");
    if (phCount) phCount.textContent = currentFilteredPharmacies.length;

    const stockCount = document.getElementById("medicineStockCount");
    if (stockCount) stockCount.textContent = totalStock;

    updateMapMarkers();
}

function createPopupContent(pharmacy) {
    const distance = calculateDistance(
        userLat,
        userLng,
        pharmacy.lat,
        pharmacy.lng
    );

    let displayMeds = currentSearchQuery
        ? pharmacy.medicines.filter(
              (m) =>
                  m.name.toLowerCase().includes(currentSearchQuery) &&
                  m.stock > 0
          )
        : pharmacy.medicines.filter((m) => m.stock > 0);

    let medsHtml = "";
    if (!displayMeds.length) {
        medsHtml = `<div style="color:#94a3b8;font-size:12px;padding:8px 0;text-align:center;">No stock available</div>`;
    } else {
        displayMeds.slice(0, 4).forEach((med) => {
            const rxBadge = med.prescription
                ? `<div style="font-size:10px;color:#9400D3;font-weight:600;margin-top:2px;">🔞 Prescription Required</div>`
                : "";

            medsHtml += `
                <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid rgba(148,0,211,0.1);">
                    <div style="padding-right:8px;">
                        <div style="color:#191970;font-weight:700;font-size:12px;line-height:1.2;">${med.name}</div>
                        ${rxBadge}
                    </div>
                    <span style="background:#191970;color:#D9F855;font-weight:700;font-size:11px;padding:4px 10px;border-radius:9999px;white-space:nowrap;box-shadow:0 1px 2px rgba(25,25,112,0.1);display:inline-block;">
                        ₱${med.price} | ${med.stock} pcs
                    </span>
                </div>
            `;
        });
        if (displayMeds.length > 4) {
            medsHtml += `<div style="font-size:10px;color:#9400D3;padding-top:6px;text-align:center;font-weight:600;">+${displayMeds.length - 4} more items</div>`;
        }
    }

    // Escape for JavaScript
    const escapedName = pharmacy.name.replace(/'/g, "\\'").replace(/"/g, '&quot;');

    return `
        <div style="background:#ffffff;padding:18px;border-radius:24px;font-family:system-ui,-apple-system,sans-serif;position:relative;width:280px;box-sizing:border-box;border:1px solid rgba(148,0,211,0.1);">
            
            <button onclick="window.closePopup()" style="position:absolute;top:12px;right:14px;background:transparent;border:none;font-size:18px;color:#94a3b8;cursor:pointer;outline:none;padding:4px;line-height:1;z-index:9999;">✕</button>

            <div style="display:flex;align-items:center;gap:6px;margin-bottom:2px;">
                <span style="font-size:16px;">🏪</span>
                <span style="font-size:15px;font-weight:800;color:#191970;line-height:1.2;">${pharmacy.name}</span>
            </div>

            <div style="font-size:11px;color:#64748b;margin-bottom:8px;padding-left:22px;">
                📍 ${pharmacy.address}
            </div>

            <div style="background:rgba(148,0,211,0.08);color:#191970;font-size:10px;font-weight:600;padding:4px 10px;border-radius:9999px;display:inline-flex;align-items:center;gap:4px;margin-bottom:12px;">
                <span>✏️</span> Distance: ${distance.toFixed(1)} km from your location
            </div>

            <div style="background:rgba(148,0,211,0.04);padding:10px 12px;border-radius:16px;margin-bottom:14px;border:1px solid rgba(148,0,211,0.06);">
                <div style="font-size:11px;font-weight:800;color:#191970;margin-bottom:4px;display:flex;align-items:center;gap:4px;">
                    <span>💊</span> Available Stock:
                </div>
                ${medsHtml}
            </div>

            <div style="display:flex;gap:8px;">
                <button onclick="window.openChatFromPopup('${escapedName}')" 
                        style="flex:1;background:#191970;color:#D9F855;border:none;padding:10px 6px;border-radius:9999px;font-size:10px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:4px;outline:none;box-shadow:0 2px 4px rgba(25,25,112,0.15);z-index:9999;pointer-events:auto;position:relative;">
                    💬 Chat & Prescription
                </button>
                
                <button onclick="window.open('https://www.google.com/maps/dir/?api=1&destination=${pharmacy.lat},${pharmacy.lng}','_blank')" 
                        style="flex:1;background:#9400D3;color:#ffffff;border:none;padding:10px 6px;border-radius:9999px;font-size:10px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:4px;outline:none;box-shadow:0 2px 4px rgba(148,0,211,0.15);z-index:9999;pointer-events:auto;position:relative;">
                    🗺️ Directions
                </button>
            </div>

        </div>
    `;
}

function updateMapMarkers() {
    markers.forEach((m) => map.removeLayer(m));
    markers = [];

    if (currentFilteredPharmacies.length === 0) {
        const tempPopup = L.popup()
            .setLatLng([userLat, userLng])
            .setContent(`
                <div style="padding:16px;text-align:center;font-size:12px;color:#64748b;font-family:system-ui,-apple-system,sans-serif;">
                    No results found
                </div>
            `)
            .openOn(map);
        setTimeout(() => map.closePopup(tempPopup), 2000);
        return;
    }

    currentFilteredPharmacies.forEach((pharmacy) => {
        const markerIcon = L.divIcon({
            html: `<div style="width:16px;height:16px;border-radius:50%;background:#191970;border:3px solid #D9F855;box-shadow:0 4px 6px -1px rgba(25,25,112,0.2);"></div>`,
            iconSize: [16, 16],
            iconAnchor: [8, 8],
            className: "marker-dot",
        });

        const marker = L.marker([pharmacy.lat, pharmacy.lng], {
            icon: markerIcon,
        });

        const content = createPopupContent(pharmacy);
        
        marker.bindPopup(content, {
            maxWidth: 320,
            minWidth: 280,
            className: "custom-popup",
        });

        marker.on('popupopen', function() {
            // Force pointer events on popup content
            setTimeout(function() {
                const popupContent = document.querySelector('.custom-popup .leaflet-popup-content');
                if (popupContent) {
                    popupContent.style.pointerEvents = 'auto';
                    const buttons = popupContent.querySelectorAll('button');
                    buttons.forEach(function(btn) {
                        btn.style.pointerEvents = 'auto';
                        btn.style.cursor = 'pointer';
                    });
                }
            }, 10);
        });

        marker.addTo(map);
        markers.push(marker);
    });
}

function initMap() {
    const mapElement = document.getElementById("medfindMap");
    if (!mapElement) return;

    map = L.map("medfindMap", {
        center: [userLat, userLng],
        zoom: 14,
    });

    L.tileLayer(
        "https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png",
        {
            attribution: "© OSM",
            subdomains: "abcd",
            maxZoom: 19,
        }
    ).addTo(map);

    map.on("click", function() {
        closePopup();
    });

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                userLat = pos.coords.latitude;
                userLng = pos.coords.longitude;
                map.setView([userLat, userLng], 14);
                performSearch();
            },
            () => performSearch()
        );
    } else {
        performSearch();
    }
}

function toggleChat() {
    const modal = document.getElementById("chatModal");
    if (!modal) return;
    chatVisible = !chatVisible;
    modal.style.display = chatVisible ? "flex" : "none";
}

function openChatFromPopup(name) {
    // Close the map popup first
    closePopup();
    
    // Open chat modal
    if (!chatVisible) {
        toggleChat();
    }
    
    const chatMsgs = document.getElementById("chatMessages");
    if (chatMsgs) {
        chatMsgs.innerHTML = `
            <div class="message received"><div class="bubble">Chat with ${name}</div></div>
            <div class="message received"><div class="bubble">How can we help with your inquiry or prescription?</div></div>
        `;
        chatMsgs.scrollTop = chatMsgs.scrollHeight;
    }
}

function openChat(name) {
    if (!chatVisible) toggleChat();
    const chatMsgs = document.getElementById("chatMessages");
    if (chatMsgs) {
        chatMsgs.innerHTML = `
            <div class="message received"><div class="bubble">Chat with ${name}</div></div>
            <div class="message received"><div class="bubble">How can we help with your inquiry or prescription?</div></div>
        `;
    }
}

function sendChatMessage() {
    const input = document.getElementById("chatInput");
    if (!input) return;
    const msg = input.value.trim();
    if (!msg) return;
    const div = document.getElementById("chatMessages");
    if (div) {
        div.innerHTML += `<div class="message sent"><div class="bubble">${msg}</div></div>`;
        input.value = "";
        div.scrollTop = div.scrollHeight;
        setTimeout(() => {
            div.innerHTML += `<div class="message received"><div class="bubble">Will assist you shortly.</div></div>`;
            div.scrollTop = div.scrollHeight;
        }, 1000);
    }
}

// ============================================
// AUTOCOMPLETE FUNCTION
// ============================================
function showAutocomplete() {
    const input = document.getElementById("medicineSearch");
    if (!input) return;
    
    const query = input.value.trim();
    const list = document.getElementById("autocompleteList");
    if (!list) return;

    if (query === '') {
        list.style.display = "none";
        list.classList.remove('active');
        list.innerHTML = "";
        return;
    }

    if (typeof allMedicineNames === "undefined" || !allMedicineNames.length) {
        list.style.display = "none";
        list.classList.remove('active');
        return;
    }

    const matches = allMedicineNames
        .filter((n) => n.toLowerCase().includes(query.toLowerCase()))
        .slice(0, 6);

    if (!matches.length) {
        list.style.display = "none";
        list.classList.remove('active');
        list.innerHTML = "";
        return;
    }

    let html = '';
    matches.forEach((m) => {
        const highlighted = m.replace(
            new RegExp(query, 'gi'), 
            '<strong>$&</strong>'
        );
        html += `<div class="autocomplete-item" onclick="selectMedicine('${m.replace(/'/g, "\\'")}')">${highlighted}</div>`;
    });
    
    list.innerHTML = html;
    list.style.display = "block";
    list.classList.add('active');
}

function selectMedicine(medicineName) {
    const input = document.getElementById("medicineSearch");
    if (input) {
        input.value = medicineName;
    }
    const list = document.getElementById("autocompleteList");
    if (list) {
        list.style.display = "none";
        list.classList.remove('active');
    }
    performSearch();
}

// ============================================
// EVENT LISTENERS
// ============================================
document.addEventListener("DOMContentLoaded", function() {
    initMap();
    
    const input = document.getElementById("medicineSearch");
    const btn = document.getElementById("searchBtn");

    if (btn) {
        btn.addEventListener("click", performSearch);
    }
    
    if (input) {
        input.addEventListener("input", showAutocomplete);
        input.addEventListener("keypress", function(e) {
            if (e.key === "Enter") {
                performSearch();
                const list = document.getElementById("autocompleteList");
                if (list) list.style.display = "none";
            }
        });
    }

    document.addEventListener("click", function(e) {
        const searchCard = document.querySelector(".search-card-minimal");
        if (searchCard && !searchCard.contains(e.target)) {
            const list = document.getElementById("autocompleteList");
            if (list) list.style.display = "none";
        }
    });
});

// Make functions globally accessible
window.closePopup = closePopup;
window.openChatFromPopup = openChatFromPopup;
window.openChat = openChat;
window.sendChatMessage = sendChatMessage;
window.toggleChat = toggleChat;
window.performSearch = performSearch;
window.showAutocomplete = showAutocomplete;
window.selectMedicine = selectMedicine;