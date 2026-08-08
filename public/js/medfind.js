// ============================================
// MEDFIND - FULL JS LOGIC WITH WORKING AUTOCOMPLETE
// ============================================

let map;
let markers = [];
let userLat = 13.1475;
let userLng = 123.7431;
let currentSearchQuery = "";
let currentFilteredPharmacies = [];
let chatVisible = false;
let selectedIndex = -1;
let inventorySelectedIndex = -1;

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
    if (autocompleteList) {
        autocompleteList.style.display = "none";
        autocompleteList.classList.remove('active');
        autocompleteList.innerHTML = "";
    }

    const data = typeof pharmaciesData !== "undefined" ? pharmaciesData : [];

    if (!currentSearchQuery) {
        currentFilteredPharmacies = data;
        const badge = document.getElementById("searchResultBadge");
        if (badge) badge.innerHTML = "All pharmacies";
    } else {
        currentFilteredPharmacies = data.filter((pharmacy) => {
            return pharmacy.medicines && pharmacy.medicines.some(
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
        if (ph.medicines) {
            const meds = currentSearchQuery
                ? ph.medicines.filter(
                      (m) =>
                          m.name.toLowerCase().includes(currentSearchQuery) &&
                          m.stock > 0
                  )
                : ph.medicines.filter((m) => m.stock > 0);
            totalStock += meds.reduce((sum, m) => sum + m.stock, 0);
        }
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

    let displayMeds = [];
    if (pharmacy.medicines) {
        displayMeds = currentSearchQuery
            ? pharmacy.medicines.filter(
                  (m) =>
                      m.name.toLowerCase().includes(currentSearchQuery) &&
                      m.stock > 0
              )
            : pharmacy.medicines.filter((m) => m.stock > 0);
    }

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

    const escapedName = pharmacy.name.replace(/'/g, "\\'").replace(/"/g, '&quot;');

    return `
        <div style="background:#ffffff;padding:18px;border-radius:24px;font-family:system-ui,-apple-system,sans-serif;position:relative;width:280px;box-sizing:border-box;border:1px solid rgba(148,0,211,0.1);">
            
            <button onclick="window.closePopup()" style="position:absolute;top:12px;right:14px;background:transparent;border:none;font-size:18px;color:#94a3b8;cursor:pointer;outline:none;padding:4px;line-height:1;z-index:9999;">✕</button>

            <div style="display:flex;align-items:center;gap:6px;margin-bottom:2px;">
                <span style="font-size:16px;">🏪</span>
                <span style="font-size:15px;font-weight:800;color:#191970;line-height:1.2;">${pharmacy.name}</span>
            </div>

            <div style="font-size:11px;color:#64748b;margin-bottom:8px;padding-left:22px;">
                📍 ${pharmacy.address || 'No address available'}
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
    markers.forEach((m) => {
        if (map) map.removeLayer(m);
    });
    markers = [];

    if (!map) return;

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
            }, 50);
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
        zoomControl: true,
    });

    L.tileLayer(
        "https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png",
        {
            attribution: "© OSM",
            subdomains: "abcd",
            maxZoom: 19,
        }
    ).addTo(map);

    map.zoomControl.setPosition('bottomright');

    map.on("click", function() {
        closePopup();
    });

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                userLat = pos.coords.latitude;
                userLng = pos.coords.longitude;
                map.setView([userLat, userLng], 14);
                performSearch();
            },
            function() {
                performSearch();
            }
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
    modal.classList.toggle('active', chatVisible);
}

function openChatFromPopup(name) {
    closePopup();
    
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
// AUTOCOMPLETE FUNCTION - COMPLETELY REWRITTEN
// ============================================
function showAutocomplete() {
    const input = document.getElementById("medicineSearch");
    if (!input) {
        console.log('Input not found');
        return;
    }
    
    const query = input.value.trim();
    const list = document.getElementById("autocompleteList");
    if (!list) {
        console.log('List not found');
        return;
    }

    // If query is empty, hide autocomplete
    if (query === '') {
        list.style.display = "none";
        list.classList.remove('active');
        list.innerHTML = "";
        selectedIndex = -1;
        return;
    }

    // Check if medicine names data exists
    if (typeof allMedicineNames === "undefined" || !allMedicineNames || !allMedicineNames.length) {
        console.log('No medicine names data or empty');
        list.style.display = "none";
        list.classList.remove('active');
        return;
    }

    const normalizedQuery = query.toLowerCase();
    const shortQuery = normalizedQuery.length <= 2;

    // Filter medicine names with smarter ranking.
    const matches = allMedicineNames
        .filter(function(n) {
            if (!n) return false;
            const lower = n.toLowerCase();
            if (shortQuery) {
                return lower.startsWith(normalizedQuery) || lower.split(/\s+/).some(word => word.startsWith(normalizedQuery));
            }
            return lower.includes(normalizedQuery);
        })
        .map(function(n) {
            const lower = n.toLowerCase();
            let rank = 2;
            if (lower === normalizedQuery) {
                rank = 0;
            } else if (lower.startsWith(normalizedQuery)) {
                rank = 1;
            } else if (lower.split(/\s+/).some(word => word.startsWith(normalizedQuery))) {
                rank = 2;
            } else {
                rank = 3;
            }
            return { name: n, rank };
        })
        .sort(function(a, b) {
            if (a.rank !== b.rank) return a.rank - b.rank;
            return a.name.localeCompare(b.name);
        })
        .slice(0, 6)
        .map(function(item) { return item.name; });

    // If no matches, hide autocomplete
    if (!matches.length) {
        list.style.display = "none";
        list.classList.remove('active');
        list.innerHTML = "";
        selectedIndex = -1;
        return;
    }

    // Build autocomplete list
    let html = '';
    matches.forEach(function(m, index) {
        const highlighted = m.replace(
            new RegExp(normalizedQuery.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi'), 
            '<strong>$&</strong>'
        );
        const escaped = m.replace(/'/g, "\\'").replace(/"/g, '&quot;');
        html += `<div class="autocomplete-item" data-medicine="${escaped}" data-index="${index}">${highlighted}</div>`;
    });
    
    list.innerHTML = html;
    list.style.display = "block";
    list.classList.add('active');
    selectedIndex = -1;
    
    list.querySelectorAll('.autocomplete-item').forEach(function(item) {
        const selectItem = function(e) {
            e.preventDefault();
            e.stopPropagation();
            const medicineName = this.getAttribute('data-medicine');
            if (medicineName) {
                selectMedicineFromAutocomplete(medicineName);
            }
        };

        item.addEventListener('mousedown', selectItem);
        item.addEventListener('click', selectItem);
        
        item.addEventListener('touchstart', function(e) {
            e.preventDefault();
            const medicineName = this.getAttribute('data-medicine');
            if (medicineName) {
                selectMedicineFromAutocomplete(medicineName);
            }
        }, { passive: false });
    });
    
    console.log('Autocomplete shown with', matches.length, 'matches');
}

function isAutocompleteTarget(target) {
    const list = document.getElementById('autocompleteList');
    return list && (list.contains(target) || target.closest('.autocomplete-items'));
}

function selectMedicineFromAutocomplete(medicineName) {
    console.log('Selected from autocomplete:', medicineName);
    
    // Set the value in the search input
    const input = document.getElementById("medicineSearch");
    if (input) {
        input.value = medicineName;
    }
    
    // Hide the autocomplete list
    const list = document.getElementById("autocompleteList");
    if (list) {
        list.style.display = "none";
        list.classList.remove('active');
        list.innerHTML = "";
    }
    
    // Perform the search
    performSearch();
}

function showInventoryAutocomplete() {
    const input = document.getElementById("pharmacyInventorySearch");
    const list = document.getElementById("inventoryAutocompleteList");
    if (!input || !list) return;

    const query = input.value.trim();
    if (query === '') {
        list.style.display = "none";
        list.innerHTML = "";
        inventorySelectedIndex = -1;
        filterInventoryTable('');
        return;
    }

    filterInventoryTable(query);

    if (typeof inventoryMedicineNames === "undefined" || !inventoryMedicineNames.length) {
        list.style.display = "none";
        list.innerHTML = "";
        return;
    }

    const normalizedQuery = query.toLowerCase();
    const shortQuery = normalizedQuery.length <= 2;

    const matches = inventoryMedicineNames
        .filter(function(name) {
            if (!name) return false;
            const lower = name.toLowerCase();
            if (shortQuery) {
                return lower.startsWith(normalizedQuery) || lower.split(/\s+/).some(word => word.startsWith(normalizedQuery));
            }
            return lower.includes(normalizedQuery);
        })
        .map(function(name) {
            const lower = name.toLowerCase();
            let rank = 2;
            if (lower === normalizedQuery) {
                rank = 0;
            } else if (lower.startsWith(normalizedQuery)) {
                rank = 1;
            } else if (lower.split(/\s+/).some(word => word.startsWith(normalizedQuery))) {
                rank = 2;
            } else {
                rank = 3;
            }
            return { name: name, rank };
        })
        .sort(function(a, b) {
            if (a.rank !== b.rank) return a.rank - b.rank;
            return a.name.localeCompare(b.name);
        })
        .slice(0, 6)
        .map(function(item) { return item.name; });

    if (!matches.length) {
        list.style.display = "none";
        list.innerHTML = "";
        inventorySelectedIndex = -1;
        return;
    }

    let html = '';
    matches.forEach(function(item, index) {
        const highlighted = item.replace(
            new RegExp(normalizedQuery.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi'), 
            '<strong>$&</strong>'
        );
        const escaped = item.replace(/'/g, "\\'").replace(/"/g, '&quot;');
        html += `<div class="autocomplete-item" data-medicine="${escaped}" data-index="${index}">${highlighted}</div>`;
    });

    list.innerHTML = html;
    list.style.display = "block";
    inventorySelectedIndex = -1;

    list.querySelectorAll('.autocomplete-item').forEach(function(item) {
        const selectItem = function(e) {
            e.preventDefault();
            e.stopPropagation();
            const medicineName = this.getAttribute('data-medicine');
            if (medicineName) {
                selectInventoryMedicineFromAutocomplete(medicineName);
            }
        };

        item.addEventListener('mousedown', selectItem);
        item.addEventListener('click', selectItem);
        item.addEventListener('touchstart', function(e) {
            e.preventDefault();
            const medicineName = this.getAttribute('data-medicine');
            if (medicineName) {
                selectInventoryMedicineFromAutocomplete(medicineName);
            }
        }, { passive: false });
    });
}

function selectInventoryMedicineFromAutocomplete(medicineName) {
    const input = document.getElementById("pharmacyInventorySearch");
    const list = document.getElementById("inventoryAutocompleteList");
    if (input) {
        input.value = medicineName;
    }
    if (list) {
        list.style.display = "none";
        list.innerHTML = "";
    }
    filterInventoryTable(medicineName);
}

function filterInventoryTable(query) {
    const searchTerm = query.trim().toLowerCase();
    const rows = document.querySelectorAll('#inventoryTableBody tr');
    let visibleCount = 0;

    rows.forEach(function(row) {
        const nameCell = row.querySelector('[data-medicine-name]');
        if (!nameCell) return;

        const nameText = nameCell.textContent.trim().toLowerCase();
        const dosageText = row.querySelector('[data-medicine-dosage]')?.textContent.trim().toLowerCase() || '';
        const matches = !searchTerm || nameText.includes(searchTerm) || dosageText.includes(searchTerm);
        row.style.display = matches ? '' : 'none';
        if (matches) visibleCount += 1;
    });

    const resultCount = document.getElementById('inventorySearchResultCount');
    if (resultCount) {
        if (!searchTerm) {
            resultCount.textContent = `Showing all inventory items.`;
        } else {
            resultCount.textContent = `${visibleCount} item${visibleCount === 1 ? '' : 's'} found for "${query}".`;
        }
    }
}

function isInventoryAutocompleteTarget(target) {
    const list = document.getElementById('inventoryAutocompleteList');
    const wrapper = document.querySelector('.inventory-search-wrapper');
    return !!(list && (list.contains(target) || target.closest('.inventory-search-wrapper'))) || !!(wrapper && wrapper.contains(target));
}

// Legacy function for backward compatibility
function selectMedicine(medicineName) {
    selectMedicineFromAutocomplete(medicineName);
}

// ============================================
// EVENT LISTENERS
// ============================================
document.addEventListener("DOMContentLoaded", function() {
    console.log('DOM loaded - Initializing MedFind');
    console.log('allMedicineNames:', (typeof allMedicineNames !== 'undefined') ? allMedicineNames : null);
    
    initMap();
    
    const input = document.getElementById("medicineSearch");
    const btn = document.getElementById("searchBtn");

    console.log('Input element:', input);
    console.log('Button element:', btn);

    if (btn) {
        btn.addEventListener("click", function(e) {
            e.preventDefault();
            performSearch();
        });
    }
    
    if (input) {
        input.addEventListener("input", function(e) {
            showAutocomplete();
        });
        console.log('Input event attached');
        
        input.addEventListener("keypress", function(e) {
            if (e.key === "Enter") {
                e.preventDefault();
                performSearch();
                const list = document.getElementById("autocompleteList");
                if (list) {
                    list.style.display = "none";
                    list.classList.remove('active');
                    list.innerHTML = "";
                }
            }
        });
        
        // Handle keyboard navigation for autocomplete
        input.addEventListener("keydown", function(e) {
            const list = document.getElementById("autocompleteList");
            if (!list || list.style.display === 'none' || list.style.display === '') return;
            
            const items = list.querySelectorAll('.autocomplete-item');
            if (!items.length) return;
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = (selectedIndex + 1) % items.length;
                highlightItem(items, selectedIndex);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = (selectedIndex - 1 + items.length) % items.length;
                highlightItem(items, selectedIndex);
            } else if (e.key === 'Enter' && selectedIndex >= 0) {
                e.preventDefault();
                const selectedItem = items[selectedIndex];
                if (selectedItem) {
                    const medicineName = selectedItem.getAttribute('data-medicine');
                    if (medicineName) {
                        selectMedicineFromAutocomplete(medicineName);
                    }
                }
            }
        });
    }

    const inventoryInput = document.getElementById('pharmacyInventorySearch');
    const inventoryBtn = document.getElementById('inventorySearchBtn');
    if (inventoryBtn) {
        inventoryBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const query = inventoryInput ? inventoryInput.value.trim() : '';
            filterInventoryTable(query);
            const list = document.getElementById('inventoryAutocompleteList');
            if (list) {
                list.style.display = 'none';
                list.innerHTML = '';
            }
        });
    }

    if (inventoryInput) {
        inventoryInput.addEventListener('input', function() {
            showInventoryAutocomplete();
        });

        inventoryInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const query = inventoryInput.value.trim();
                filterInventoryTable(query);
                const list = document.getElementById('inventoryAutocompleteList');
                if (list) {
                    list.style.display = 'none';
                    list.innerHTML = '';
                }
            }
        });

        inventoryInput.addEventListener('keydown', function(e) {
            const list = document.getElementById('inventoryAutocompleteList');
            if (!list || list.style.display === 'none' || list.style.display === '') return;
            
            const items = list.querySelectorAll('.autocomplete-item');
            if (!items.length) return;
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                inventorySelectedIndex = (inventorySelectedIndex + 1) % items.length;
                highlightItem(items, inventorySelectedIndex);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                inventorySelectedIndex = (inventorySelectedIndex - 1 + items.length) % items.length;
                highlightItem(items, inventorySelectedIndex);
            } else if (e.key === 'Enter' && inventorySelectedIndex >= 0) {
                e.preventDefault();
                const selectedItem = items[inventorySelectedIndex];
                if (selectedItem) {
                    const medicineName = selectedItem.getAttribute('data-medicine');
                    if (medicineName) {
                        selectInventoryMedicineFromAutocomplete(medicineName);
                    }
                }
            }
        });

        filterInventoryTable('');
    }

    // Close autocomplete when clicking outside
    document.addEventListener("click", function(e) {
        const searchCard = document.querySelector(".search-card-minimal");
        const autocompleteList = document.getElementById("autocompleteList");
        const inventoryList = document.getElementById('inventoryAutocompleteList');

        if (searchCard && autocompleteList) {
            if (!searchCard.contains(e.target) && !isAutocompleteTarget(e.target)) {
                autocompleteList.style.display = "none";
                autocompleteList.classList.remove('active');
                autocompleteList.innerHTML = "";
                selectedIndex = -1;
            }
        }

        if (inventoryList && !isInventoryAutocompleteTarget(e.target)) {
            inventoryList.style.display = 'none';
            inventoryList.innerHTML = '';
            inventorySelectedIndex = -1;
        }
    });
});

function highlightItem(items, index) {
    items.forEach(function(item, i) {
        if (i === index) {
            item.style.background = 'rgba(148, 0, 211, 0.15)';
            item.scrollIntoView({ block: 'nearest' });
        } else {
            item.style.background = '';
        }
    });
}

// Make functions globally accessible
window.closePopup = closePopup;
window.openChatFromPopup = openChatFromPopup;
window.openChat = openChat;
window.sendChatMessage = sendChatMessage;
window.toggleChat = toggleChat;
window.performSearch = performSearch;
window.showInventoryAutocomplete = showInventoryAutocomplete;
window.filterInventoryTable = filterInventoryTable;
window.showAutocomplete = showAutocomplete;
window.showInventoryAutocomplete = showInventoryAutocomplete;
window.filterInventoryTable = filterInventoryTable;
window.selectMedicine = selectMedicine;
window.selectMedicineFromAutocomplete = selectMedicineFromAutocomplete;
window.selectInventoryMedicineFromAutocomplete = selectInventoryMedicineFromAutocomplete;
window.selectInventoryMedicineFromAutocomplete = selectInventoryMedicineFromAutocomplete;

function getCsrfToken() {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
}

function updateUnreadBadge(count) {
    const badge = document.getElementById('pharmacyUnreadCountBadge');
    if (badge) badge.textContent = count;
}

function pollUnreadCount() {
    if (!document.getElementById('pharmacyUnreadCountBadge')) return;
    fetch('/pharmacy/unread-count', { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (data && typeof data.count !== 'undefined') {
                updateUnreadBadge(data.count);
                if (data.count === 0) {
                    document.querySelectorAll('.message-new').forEach(el => el.style.display = 'none');
                }
            }
        }).catch(err => console.log('Unread count poll error', err));
}

// Event delegation for mark-as-read buttons and verify buttons
document.addEventListener('click', function(e) {
    if (e.target && e.target.closest && e.target.closest('.js-mark-read')) {
        const btn = e.target.closest('.js-mark-read');
        const id = btn.getAttribute('data-id');
        if (!id) return;
        btn.disabled = true;
        const token = getCsrfToken();
        fetch('/pharmacy/message/mark-read-ajax/' + id, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({})
        }).then(resp => resp.json())
        .then(data => {
            btn.disabled = false;
            if (data && data.success) {
                updateUnreadBadge(data.count ?? 0);
                const container = btn.closest('.bg-white');
                if (container) {
                    const newLabel = container.querySelector('.message-new');
                    if (newLabel) newLabel.remove();
                }
            } else {
                console.log('Mark read failed', data);
            }
        }).catch(err => {
            btn.disabled = false;
            console.log('mark-read-ajax error', err);
        });
    }

    // Verify button flow - open modal
    if (e.target && e.target.closest && e.target.closest('.js-verify-button')) {
        const btn = e.target.closest('.js-verify-button');
        const id = btn.getAttribute('data-id');
        if (!id) return;

        const modal = document.getElementById('verifyModal');
        if (!modal) return;
        modal.dataset.messageId = id;
        // reset modal fields
        const approvedRadio = modal.querySelector('input[name="verify_status"][value="approved"]');
        if (approvedRadio) approvedRadio.checked = true;
        const notesField = modal.querySelector('#verifyNotes');
        if (notesField) notesField.value = '';
        modal.style.display = 'flex';
    }
});

// Modal wiring for Verify modal
(function(){
  const verifyModal = document.getElementById('verifyModal');
  if (!verifyModal) {
    /* no modal on this page */
  } else {
    const confirmBtn = document.getElementById('confirmVerifyBtn');
    const cancelBtn = document.getElementById('cancelVerifyBtn');
    const backdrop = document.getElementById('verifyModalBackdrop');
    if (confirmBtn) {
      confirmBtn.addEventListener('click', function(){
        const id = verifyModal.dataset.messageId;
        if (!id) return;
        const statusInput = verifyModal.querySelector('input[name="verify_status"]:checked');
        const status = statusInput ? statusInput.value : 'approved';
        const notesField = verifyModal.querySelector('#verifyNotes');
        const notes = notesField ? notesField.value : '';
        confirmBtn.disabled = true;
        const token = getCsrfToken();
        fetch('/pharmacy/message/verify-ajax/' + id, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
            'Accept': 'application/json'
          },
          credentials: 'same-origin',
          body: JSON.stringify({ status: status, notes: notes })
        }).then(r => r.json()).then(data => {
          confirmBtn.disabled = false;
          verifyModal.style.display = 'none';
          verifyModal.dataset.messageId = '';
          if (data && data.success) {
            const origBtn = document.querySelector('.js-verify-button[data-id="' + id + '"]');
            if (origBtn && origBtn.parentElement) {
              origBtn.parentElement.innerHTML = `<div class="text-sm"><span class="px-2 py-1 rounded-full text-white text-xs font-semibold ${data.status === 'approved' ? 'bg-green-600' : 'bg-red-600'}">${data.status.charAt(0).toUpperCase() + data.status.slice(1)}</span><div class="text-xs text-gray-500">By: ${data.verifier} at ${data.verified_at}</div></div>`;
            } else {
              location.reload();
            }
          } else {
            alert('Verification failed: ' + (data.error || 'Unknown error'));
          }
        }).catch(err => {
          confirmBtn.disabled = false;
          verifyModal.style.display = 'none';
          verifyModal.dataset.messageId = '';
          console.log('verify-ajax error', err);
          alert('Verification error. See console for details.');
        });
      });
    }
    if (cancelBtn) cancelBtn.addEventListener('click', function(){ verifyModal.style.display = 'none'; verifyModal.dataset.messageId = ''; });
    if (backdrop) backdrop.addEventListener('click', function(){ verifyModal.style.display = 'none'; verifyModal.dataset.messageId = ''; });
  }
})();

// Start polling when on pages that have the badge
document.addEventListener('DOMContentLoaded', function() {
    pollUnreadCount();
    setInterval(pollUnreadCount, 10000);
});

console.log('MedFind JS loaded - All functions ready');