@extends('layouts.app')

@section('content')
<div class="map-container" style="height: 100vh; width: 100%; position: relative; overflow: hidden;">
    <!-- Map Container -->
    <div id="medfindMap" style="height: 100%; width: 100%;"></div>
    
    <!-- Stats Bar - Fixed position para hindi matabunan -->
    <div class="stats-bar-fixed">
        <span class="stat-item">
            <span class="stat-number" id="pharmacyCount">{{ $pharmacyCount ?? 0 }}</span>
            <span class="stat-label">Pharmacies</span>
        </span>
        <span class="stat-divider"></span>
        <span class="stat-item">
            <span class="stat-number" id="medicineStockCount">{{ $medicineStockCount ?? 0 }}</span>
            <span class="stat-label">Medicines</span>
        </span>
        <span class="stat-divider"></span>
        <span class="stat-item">
            <span class="stat-label" id="searchResultBadge">All locations</span>
        </span>
    </div>

    <!-- Search Bar -->
    <div class="search-panel">
        <div class="search-card-minimal">
            <i class="fas fa-search text-[#9400D3]/40 text-sm"></i>
            <input type="text" id="medicineSearch" placeholder="Search for a medicine..." autocomplete="on">
            <button id="searchBtn">Search</button>
        </div>
        <div id="autocompleteList" class="autocomplete-items" style="display: none;"></div>
    </div>

<!-- Messenger-style Chat Heads -->
    <div id="chatHeadsContainer" style="position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;align-items:flex-end;gap:10px;"></div>

    <!-- Active Chat Window -->
    <div id="activeChatWindow" style="display:none;position:fixed;bottom:24px;right:24px;z-index:10000;width:320px;background:#fff;border-radius:18px;box-shadow:0 8px 40px rgba(25,25,112,0.15);border:1px solid rgba(148,0,211,0.12);overflow:hidden;font-family:system-ui,-apple-system,sans-serif;">
        <!-- Chat Window Header -->
        <div style="background:#191970;padding:10px 14px;display:flex;align-items:center;gap:10px;">
            <div style="width:32px;height:32px;border-radius:50%;background:rgba(217,248,85,0.15);display:flex;align-items:center;justify-content:center;shrink:0;">
                <i class="fas fa-store" style="color:#D9F855;font-size:13px;"></i>
            </div>
            <div style="flex:1;min-width:0;">
                <p id="activeChatName" style="color:#fff;font-weight:800;font-size:13px;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></p>
            </div>
            <div style="display:flex;gap:6px;align-items:center;">
                <button onclick="minimizeChatWindow()" title="Minimize"
                    style="background:rgba(255,255,255,0.15);border:none;color:#fff;width:26px;height:26px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px;">
                    <i class="fas fa-minus"></i>
                </button>
                <button onclick="closeChatWindow()" title="Close"
                    style="background:rgba(255,255,255,0.15);border:none;color:#fff;width:26px;height:26px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <!-- Messages area -->
        <div id="activeChatMessages" style="height:280px;overflow-y:auto;padding:12px;background:#f8f4ff;display:flex;flex-direction:column;gap:10px;"></div>
        <!-- Input -->
        <div style="padding:10px;background:#fff;border-top:1px solid rgba(148,0,211,0.08);display:flex;gap:8px;align-items:center;">
            <input type="text" id="activeChatInput" placeholder="Type a message..."
                style="flex:1;border:1px solid rgba(148,0,211,0.2);border-radius:10px;padding:8px 12px;font-size:12px;outline:none;color:#191970;background:#f8f4ff;"
                onkeypress="if(event.key==='Enter')sendActiveChatMessage()">
            <button onclick="sendActiveChatMessage()"
                style="background:#191970;border:none;color:#D9F855;width:34px;height:34px;border-radius:10px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>

<!-- Clear Route Button - shows when a route is active -->
    <div id="clearRouteBtn" class="clear-route-fixed" style="display: none;">
        <button onclick="window.clearRoute()" class="clear-route-btn">
            <i class="fas fa-times mr-1"></i> Clear Route
        </button>
    </div>

    <!-- Route Summary - shows distance & ETA above the routing panel -->
    <div id="routeSummary" class="route-summary-fixed" style="display: none;"></div>


</div>

<!-- Pass PHP data to JavaScript -->
<script>
    const pharmaciesData = @json($formattedPharmacies ?? []);
    const allMedicineNames = @json($medicineNames ?? []);

    // Auto-trigger directions when arriving from pharmacy details with ?dir=1&lat=..&lng=..
    document.addEventListener('DOMContentLoaded', function() {
        const params = new URLSearchParams(window.location.search);
        const dir = params.get('dir');
        const lat = params.get('lat');
        const lng = params.get('lng');
        if (dir === '1' && lat && lng) {
            // Wait for the map to initialize before routing
            setTimeout(function() {
                if (typeof window.getDirections === 'function') {
                    window.getDirections(parseFloat(lat), parseFloat(lng));
                }
            }, 1200);
        }
    });

    // -------------------------------------------------------
    // REAL-TIME: Listen for inventory updates via Reverb/Echo
    // Updates the in-memory pharmaciesData and re-renders map.
    // -------------------------------------------------------
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.Echo) return;

        window.Echo.channel('inventory')
            .listen('.inventory.updated', function (e) {
                if (!e || !e.pharmacyId) return;

                // Update pharmaciesData in place
                if (typeof pharmaciesData === 'undefined') return;
                const pharmacy = pharmaciesData.find(p => p.id === e.pharmacyId);
                if (!pharmacy) return;

                if (!pharmacy.medicines) pharmacy.medicines = [];
                const med = pharmacy.medicines.find(m => m.id === e.medicineId);
                if (med) {
                    med.stock = e.stock;
                    med.price = e.price;
                } else if (e.stock > 0) {
                    // New medicine added to this pharmacy's stock
                    pharmacy.medicines.push({
                        id: e.medicineId,
                        name: e.medicineName,
                        stock: e.stock,
                        price: e.price,
                        prescription: e.prescription,
                    });
                }

                // Re-draw map markers with fresh data
                if (typeof window.performSearch === 'function') {
                    window.performSearch();
                }

                console.info('[MedFind] Real-time: stock updated for pharmacy', e.pharmacyId, '→', e.medicineName, e.stock);
            });

        console.info('[MedFind] Listening on inventory channel for real-time updates');
    });
</script>

<style>
    /* Force all UI elements to be on top */
    .stats-bar-fixed {
        position: fixed !important;
        top: 76px !important;
        left: 50% !important;
        transform: translateX(-50%) !important;
        z-index: 9999 !important;
        background: rgba(255, 255, 255, 0.95) !important;
        backdrop-filter: blur(12px) !important;
        border-radius: 9999px !important;
        padding: 6px 20px !important;
        box-shadow: 0 4px 20px rgba(25, 25, 112, 0.08) !important;
        border: 1px solid rgba(148, 0, 211, 0.12) !important;
        display: flex !important;
        align-items: center !important;
        gap: 16px !important;
        font-size: 12px !important;
        pointer-events: none !important;
    }
    
    .stats-bar-fixed .stat-item {
        display: flex !important;
        align-items: center !important;
        gap: 4px !important;
    }
    
    .stats-bar-fixed .stat-number {
        font-weight: 700 !important;
        color: #191970 !important;
    }
    
    .stats-bar-fixed .stat-label {
        color: #94a3b8 !important;
    }
    
    .stats-bar-fixed .stat-divider {
        width: 1px !important;
        height: 16px !important;
        background: #e2e8f0 !important;
    }
    
    .search-panel {
        position: fixed !important;
        top: 120px !important;
        left: 50% !important;
        transform: translateX(-50%) !important;
        z-index: 9998 !important;
        width: 90% !important;
        max-width: 440px !important;
    }
    
    .search-card-minimal {
        background: #ffffff !important;
        border-radius: 16px !important;
        box-shadow: 0 4px 20px rgba(25, 25, 112, 0.08) !important;
        padding: 6px 6px 6px 16px !important;
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
        width: 100% !important;
        pointer-events: auto !important;
        border: 1px solid rgba(148, 0, 211, 0.15) !important;
    }
    
    .search-card-minimal input {
        flex: 1 !important;
        border: none !important;
        padding: 10px 0 !important;
        font-size: 13px !important;
        font-family: system-ui, -apple-system, sans-serif !important;
        outline: none !important;
        background: transparent !important;
        color: #191970 !important;
    }
    
    .search-card-minimal input::placeholder {
        color: #bbb !important;
    }
    
    .search-card-minimal button {
        background: #191970 !important;
        border: none !important;
        color: #D9F855 !important;
        padding: 8px 16px !important;
        border-radius: 12px !important;
        font-weight: 600 !important;
        cursor: pointer !important;
        font-size: 12px !important;
        font-family: system-ui, -apple-system, sans-serif !important;
        transition: 0.2s !important;
    }
    
    .search-card-minimal button:hover {
        background: #2a2a8a !important;
    }
    
    .autocomplete-items {
        position: absolute !important;
        top: calc(100% + 6px) !important;
        left: 50% !important;
        transform: translateX(-50%) !important;
        width: 100% !important;
        background: #ffffff !important;
        border-radius: 14px !important;
        box-shadow: 0 6px 24px rgba(25, 25, 112, 0.08) !important;
        max-height: 200px !important;
        overflow-y: auto !important;
        z-index: 99999 !important;
        border: 1px solid rgba(148, 0, 211, 0.15) !important;
        display: none !important;
    }
    
    .autocomplete-items.active {
        display: block !important;
    }
    
    .autocomplete-item {
        padding: 10px 16px !important;
        cursor: pointer !important;
        font-size: 13px !important;
        color: #191970 !important;
        border-bottom: 1px solid rgba(148, 0, 211, 0.08) !important;
    }
    
    .autocomplete-item:hover {
        background: rgba(148, 0, 211, 0.05) !important;
    }
    
    .autocomplete-item strong {
        color: #9400D3 !important;
        font-weight: 700 !important;
    }
    
    .autocomplete-item:last-child {
        border-bottom: none !important;
    }
    
/* Clear Route Button - Fixed position */
    .clear-route-fixed {
        position: fixed !important;
        bottom: 84px !important;
        right: 24px !important;
        z-index: 9999 !important;
    }
    
    .clear-route-btn {
        background: #9400D3 !important;
        color: #ffffff !important;
        border: none !important;
        padding: 10px 16px !important;
        border-radius: 9999px !important;
        font-weight: 700 !important;
        font-size: 12px !important;
        cursor: pointer !important;
        box-shadow: 0 4px 16px rgba(148, 0, 211, 0.3) !important;
        transition: 0.2s !important;
        display: flex !important;
        align-items: center !important;
        gap: 4px !important;
        font-family: system-ui, -apple-system, sans-serif !important;
    }
    
.clear-route-btn:hover {
        background: #7a00b0 !important;
        transform: scale(1.03) !important;
    }
    
    /* Route Summary - Fixed position above routing panel */
    .route-summary-fixed {
        position: fixed !important;
        bottom: 84px !important;
        right: 180px !important;
        z-index: 9999 !important;
        background: #ffffff !important;
        border-radius: 12px !important;
        padding: 8px 16px !important;
        box-shadow: 0 4px 16px rgba(25, 25, 112, 0.12) !important;
        border: 1px solid rgba(148, 0, 211, 0.15) !important;
        font-size: 12px !important;
        font-weight: 700 !important;
        color: #191970 !important;
        font-family: system-ui, -apple-system, sans-serif !important;
        align-items: center !important;
        gap: 6px !important;
    }
    
    .route-summary-fixed i {
        color: #9400D3 !important;
    }
    
    /* Chat Button - Fixed position with highest z-index */
    .chat-float-fixed {
        position: fixed !important;
        bottom: 24px !important;
        right: 24px !important;
        z-index: 9999 !important;
    }
    
    .chat-toggle-btn {
        background: #191970 !important;
        width: 48px !important;
        height: 48px !important;
        border-radius: 50% !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        cursor: pointer !important;
        box-shadow: 0 4px 16px rgba(25, 25, 112, 0.25) !important;
        transition: 0.2s !important;
        color: #D9F855 !important;
        font-size: 18px !important;
        border: none !important;
    }
    
    .chat-toggle-btn:hover {
        transform: scale(1.06) !important;
        background: #2a2a8a !important;
    }
    
    .chat-modal-fixed {
        position: fixed !important;
        bottom: 84px !important;
        right: 24px !important;
        width: 310px !important;
        background: #ffffff !important;
        border-radius: 18px !important;
        box-shadow: 0 10px 40px rgba(25, 25, 112, 0.12) !important;
        display: none !important;
        flex-direction: column !important;
        overflow: hidden !important;
        z-index: 10000 !important;
        border: 1px solid rgba(148, 0, 211, 0.12) !important;
    }
    
    .chat-modal-fixed.active {
        display: flex !important;
    }
    
    .chat-header {
        padding: 12px 16px !important;
        font-weight: 700 !important;
        display: flex !important;
        justify-content: space-between !important;
        cursor: pointer !important;
        border-bottom: 1px solid rgba(148, 0, 211, 0.1) !important;
        color: #191970 !important;
        font-size: 13px !important;
        background: rgba(148, 0, 211, 0.03) !important;
    }
    
    .chat-messages {
        height: 260px !important;
        overflow-y: auto !important;
        padding: 12px !important;
        background: rgba(148, 0, 211, 0.02) !important;
    }
    
    .message {
        margin-bottom: 10px !important;
        display: flex !important;
        flex-direction: column !important;
    }
    
    .message.sent {
        align-items: flex-end !important;
    }
    .message.received {
        align-items: flex-start !important;
    }
    
    .bubble {
        max-width: 85% !important;
        padding: 8px 12px !important;
        border-radius: 12px !important;
        font-size: 12px !important;
        line-height: 1.4 !important;
    }
    
    .message.sent .bubble {
        background: #191970 !important;
        color: #D9F855 !important;
    }
    
    .message.received .bubble {
        background: #ffffff !important;
        border: 1px solid rgba(148, 0, 211, 0.15) !important;
        color: #191970 !important;
    }
    
    .chat-input-area {
        display: flex !important;
        padding: 10px !important;
        border-top: 1px solid rgba(148, 0, 211, 0.1) !important;
        gap: 6px !important;
        background: #ffffff !important;
    }
    
    .chat-input-area input {
        flex: 1 !important;
        padding: 8px 12px !important;
        border-radius: 10px !important;
        border: 1px solid rgba(148, 0, 211, 0.15) !important;
        outline: none !important;
        font-size: 12px !important;
        font-family: system-ui, -apple-system, sans-serif !important;
        background: rgba(148, 0, 211, 0.02) !important;
        color: #191970 !important;
    }
    
    .chat-input-area input:focus {
        border-color: #9400D3 !important;
    }
    
    .chat-input-area button {
        background: #191970 !important;
        border: none !important;
        color: #D9F855 !important;
        padding: 8px 14px !important;
        border-radius: 10px !important;
        cursor: pointer !important;
        font-size: 12px !important;
        font-weight: 600 !important;
    }
    
    .chat-input-area button:hover {
        background: #2a2a8a !important;
    }
    
    /* Force Leaflet popup to be below UI */
    .leaflet-popup {
        z-index: 50 !important;
    }
    
    .leaflet-popup-content-wrapper {
        z-index: 50 !important;
    }
    
    .leaflet-popup-tip-container {
        z-index: 50 !important;
    }
    
    .leaflet-control-container {
        z-index: 50 !important;
    }
    
    .leaflet-control-zoom {
        z-index: 50 !important;
    }
    
    @media (max-width: 640px) {
        .stats-bar-fixed {
            top: 68px !important;
            padding: 4px 12px !important;
            gap: 8px !important;
            font-size: 10px !important;
        }
        .stats-bar-fixed .stat-divider {
            height: 12px !important;
        }
        .search-panel {
            top: 108px !important;
        }
        .search-card-minimal {
            padding: 4px 4px 4px 12px !important;
        }
        .search-card-minimal input {
            font-size: 12px !important;
            padding: 8px 0 !important;
        }
        .search-card-minimal button {
            padding: 6px 12px !important;
            font-size: 11px !important;
        }
        .chat-modal-fixed {
            width: calc(100% - 32px) !important;
            right: 16px !important;
            bottom: 76px !important;
        }
        .autocomplete-items {
            width: 100% !important;
        }
    }
</style>

<script>
// ============================================
// MESSENGER-STYLE CHAT HEADS
// ============================================
let activeChatPharmacyId = null;
let conversationsData = [];
let chatWindowMinimized = false;

// Dismissed heads: { pharmacyId: lastMessageTimestamp }
// A head is dismissed until a newer message/reply arrives.
function getDismissed() {
    try { return JSON.parse(localStorage.getItem('mf_dismissed_heads') || '{}'); } catch(e) { return {}; }
}
function saveDismissed(obj) {
    try { localStorage.setItem('mf_dismissed_heads', JSON.stringify(obj)); } catch(e) {}
}
function dismissHead(pharmacyId, latestTs) {
    const d = getDismissed();
    d[pharmacyId] = latestTs;
    saveDismissed(d);
}
function isDismissed(conv) {
    const d = getDismissed();
    if (!d[conv.pharmacy_id]) return false;
    // Find the latest timestamp in this conversation (message or reply)
    let latest = '';
    conv.messages.forEach(function(m) {
        if (m.replied_at && m.replied_at > latest) latest = m.replied_at;
        if (m.created_at && m.created_at > latest) latest = m.created_at;
    });
    // Dismissed if the stored timestamp matches or is newer than latest activity
    return d[conv.pharmacy_id] >= latest;
}

function loadChatHeads() {
    @auth
    fetch('{{ route("consumer.messages.json") }}', { credentials: 'same-origin' })
        .then(r => r.json())
        .then(function(data) {
            // Only show heads that are NOT dismissed
            conversationsData = data.filter(function(conv) {
                return !isDismissed(conv);
            });
            renderChatHeads();
        })
        .catch(function() {});
    @endauth
}

function renderChatHeads() {
    const container = document.getElementById('chatHeadsContainer');
    if (!container) return;

    // Don't render heads if chat window is open
    if (activeChatPharmacyId && !chatWindowMinimized) {
        container.innerHTML = '';
        return;
    }

    container.innerHTML = '';
    conversationsData.forEach(function(conv) {
        const head = document.createElement('div');
        head.style.cssText = 'position:relative;cursor:pointer;width:50px;height:50px;';
        head.title = conv.pharmacy_name;
        head.onclick = function() { openChatWindow(conv.pharmacy_id); };

        const circle = document.createElement('div');
        circle.style.cssText = 'width:50px;height:50px;border-radius:50%;background:#191970;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 16px rgba(25,25,112,0.25);border:2.5px solid #D9F855;transition:transform 0.15s;';
        circle.innerHTML = '<i class="fas fa-store" style="color:#D9F855;font-size:18px;"></i>';

        head.appendChild(circle);

        // Top-right corner overlay: unread badge OR close button on hover
        const cornerBtn = document.createElement('span');
        const baseStyle = 'position:absolute;top:-4px;right:-4px;border-radius:9999px;min-width:20px;height:20px;display:flex;align-items:center;justify-content:center;padding:0 4px;border:2px solid #fff;line-height:1;font-size:10px;font-weight:800;cursor:pointer;transition:background 0.15s,color 0.15s;';

        if (conv.unread > 0) {
            // Show unread count by default
            cornerBtn.style.cssText = baseStyle + 'background:#e53e3e;color:#fff;';
            cornerBtn.textContent = conv.unread > 9 ? '9+' : conv.unread;
            // On hover: switch to close ✕
            head.onmouseenter = function() {
                cornerBtn.textContent = '✕';
                cornerBtn.style.background = '#6b7280';
                cornerBtn.style.color = '#fff';
                circle.style.transform = 'scale(1.1)';
            };
            head.onmouseleave = function() {
                cornerBtn.textContent = conv.unread > 9 ? '9+' : conv.unread;
                cornerBtn.style.background = '#e53e3e';
                cornerBtn.style.color = '#fff';
                circle.style.transform = 'scale(1)';
            };
        } else {
            // No unread — show close only on hover, hidden otherwise
            cornerBtn.style.cssText = baseStyle + 'background:#6b7280;color:#fff;opacity:0;';
            cornerBtn.textContent = '✕';
            head.onmouseenter = function() {
                cornerBtn.style.opacity = '1';
                circle.style.transform = 'scale(1.1)';
            };
            head.onmouseleave = function() {
                cornerBtn.style.opacity = '0';
                circle.style.transform = 'scale(1)';
            };
        }

        // Close button dismisses this head and persists across refresh
        cornerBtn.onclick = function(e) {
            e.stopPropagation();
            // Save latest timestamp so it only reappears on newer activity
            let latest = '';
            conv.messages.forEach(function(m) {
                if (m.replied_at && m.replied_at > latest) latest = m.replied_at;
                if (m.created_at && m.created_at > latest) latest = m.created_at;
            });
            dismissHead(conv.pharmacy_id, latest);
            conversationsData = conversationsData.filter(c => c.pharmacy_id != conv.pharmacy_id);
            renderChatHeads();
        };

        head.appendChild(cornerBtn);
        container.appendChild(head);
    });
}

function openChatWindow(pharmacyId) {
    const conv = conversationsData.find(c => c.pharmacy_id == pharmacyId);
    if (!conv) return;

    activeChatPharmacyId = pharmacyId;
    chatWindowMinimized = false;

    const win = document.getElementById('activeChatWindow');
    document.getElementById('activeChatName').textContent = conv.pharmacy_name;
    renderActiveChatMessages(conv);
    win.style.display = 'block';

    // Hide chat heads while window is open
    document.getElementById('chatHeadsContainer').innerHTML = '';

    // Scroll to bottom
    setTimeout(function() {
        const msgs = document.getElementById('activeChatMessages');
        if (msgs) msgs.scrollTop = msgs.scrollHeight;
    }, 50);
}

function renderActiveChatMessages(conv) {
    const container = document.getElementById('activeChatMessages');
    if (!container) return;
    container.innerHTML = '';

    conv.messages.forEach(function(msg) {
        // Consumer message (right)
        const consumerRow = document.createElement('div');
        consumerRow.style.cssText = 'display:flex;justify-content:flex-end;gap:6px;align-items:flex-end;';
        consumerRow.innerHTML =
            '<div style="max-width:75%;">' +
                '<div style="background:#191970;color:#D9F855;font-size:12px;font-weight:500;padding:8px 14px;border-radius:16px 16px 4px 16px;line-height:1.4;">' + escHtml(msg.message) + '</div>' +
                '<p style="font-size:10px;color:#94a3b8;text-align:right;margin-top:2px;">You</p>' +
            '</div>' +
            '<div style="width:26px;height:26px;border-radius:50%;background:rgba(148,0,211,0.1);display:flex;align-items:center;justify-content:center;shrink:0;">' +
                '<i class="fas fa-user" style="color:#9400D3;font-size:10px;"></i>' +
            '</div>';
        container.appendChild(consumerRow);

        // Pharmacy reply (left)
        if (msg.reply) {
            const replyRow = document.createElement('div');
            replyRow.style.cssText = 'display:flex;justify-content:flex-start;gap:6px;align-items:flex-end;';
            replyRow.innerHTML =
                '<div style="width:26px;height:26px;border-radius:50%;background:#191970;display:flex;align-items:center;justify-content:center;shrink:0;">' +
                    '<i class="fas fa-store" style="color:#D9F855;font-size:10px;"></i>' +
                '</div>' +
                '<div style="max-width:75%;">' +
                    '<div style="background:#fff;border:1px solid rgba(148,0,211,0.15);color:#191970;font-size:12px;font-weight:500;padding:8px 14px;border-radius:16px 16px 16px 4px;line-height:1.4;">' + escHtml(msg.reply) + '</div>' +
                    '<p style="font-size:10px;color:#94a3b8;margin-top:2px;">' + escHtml(conv.pharmacy_name) + '</p>' +
                '</div>';
            container.appendChild(replyRow);
        }
    });
}

function minimizeChatWindow() {
    const win = document.getElementById('activeChatWindow');
    win.style.display = 'none';
    chatWindowMinimized = true;
    renderChatHeads();
}

function closeChatWindow() {
    const win = document.getElementById('activeChatWindow');
    win.style.display = 'none';
    if (activeChatPharmacyId) {
        // Persist dismiss so head does not reappear on refresh
        const conv = conversationsData.find(c => c.pharmacy_id == activeChatPharmacyId);
        if (conv) {
            let latest = '';
            conv.messages.forEach(function(m) {
                if (m.replied_at && m.replied_at > latest) latest = m.replied_at;
                if (m.created_at && m.created_at > latest) latest = m.created_at;
            });
            dismissHead(activeChatPharmacyId, latest);
        }
        conversationsData = conversationsData.filter(c => c.pharmacy_id != activeChatPharmacyId);
    }
    activeChatPharmacyId = null;
    chatWindowMinimized = false;
    renderChatHeads();
}

function sendActiveChatMessage() {
    const input = document.getElementById('activeChatInput');
    if (!input || !activeChatPharmacyId) return;
    const msg = input.value.trim();
    if (!msg) return;

    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const fd = new FormData();
    fd.append('_token', token);
    fd.append('pharmacy_id', activeChatPharmacyId);
    fd.append('message', msg);

    input.value = '';

    fetch('{{ route("consumer.message.send") }}', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function() {
        // Reload conversations and re-render
        loadChatHeads();
        setTimeout(function() {
            const conv = conversationsData.find(c => c.pharmacy_id == activeChatPharmacyId);
            if (conv) {
                renderActiveChatMessages(conv);
                const msgs = document.getElementById('activeChatMessages');
                if (msgs) msgs.scrollTop = msgs.scrollHeight;
            }
        }, 600);
    });
}

function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.addEventListener('DOMContentLoaded', function() {
    @auth
    if ('{{ auth()->user()->role }}' === 'consumer') {
        loadChatHeads();
        setInterval(loadChatHeads, 15000);
    }
    @endauth
});
</script>@endsection