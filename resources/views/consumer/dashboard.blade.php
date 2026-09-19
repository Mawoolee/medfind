@extends('layouts.app')

@section('content')
<div class="map-container" style="height: 100vh; width: 100%; position: relative; overflow: hidden; margin-top: -64px;">
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

    <!-- Nearest Pharmacy Suggestion (appears after search) -->
    <div id="nearestSuggestion" class="nearest-suggestion-panel" style="display: none;"></div>

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

<!-- Route Info Bar - clean summary card: summary line on top, buttons row below -->
    <div id="routeInfoBar" role="status" aria-live="polite">
        <div id="routeSummary"></div>
        <div id="routeAlternativesPanel" class="route-alternatives-panel" role="region" aria-label="Alternative routes" aria-hidden="true" hidden></div>
        <div id="routeActions">
            <button id="toggleRoutesBtn" type="button" onclick="window.toggleRouteAlternatives()" aria-controls="routeAlternativesPanel" aria-expanded="false" hidden disabled>
                <i class="fas fa-code-branch"></i> <span id="toggleRoutesLabel">Routes</span>
            </button>
            <button id="toggleStepsBtn" type="button" onclick="window.toggleDirections()" aria-controls="googleDirectionsPanel" aria-expanded="false" disabled>
                <i class="fas fa-list-ul"></i> <span id="toggleStepsLabel">View steps</span> <i class="fas fa-chevron-up chevron"></i>
            </button>
            <button id="clearRouteBtn" type="button" onclick="window.clearRoute()">
                <i class="fas fa-times"></i> Clear Route
            </button>
        </div>
    </div>

    <!-- Google DirectionsRenderer writes turn-by-turn instructions here. -->
    <aside id="googleDirectionsPanel" class="google-directions-panel" aria-label="Turn-by-turn directions" aria-hidden="true" hidden></aside>


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

                console.info('[MedFind] Real-time: stock updated for pharmacy', e.pharmacyId, '?', e.medicineName, e.stock);
            });

        console.info('[MedFind] Listening on inventory channel for real-time updates');

        // Listen for new messages directed to this consumer (real-time chat)
        @auth
        if ('{{ auth()->user()->role }}' === 'consumer') {
            window.Echo.channel('consumer.{{ auth()->id() }}')
                .listen('.message.sent', function(e) {
                    if (e.direction === 'pharmacy_to_consumer') {
                        loadChatHeads();
                    }
                });
        }
        @endauth
    });
</script>

<style>
    /* Pulsing animation for user location marker */
    @keyframes medfindPulse {
        0% { transform: scale(1); opacity: 0.6; }
        70% { transform: scale(2.5); opacity: 0; }
        100% { transform: scale(1); opacity: 0; }
    }
    .medfind-user-location {
        background: transparent !important;
        border: none !important;
    }

    /* Nearest pharmacy suggestion panel */
    .nearest-suggestion-panel {
        position: fixed !important;
        /* Matches .medfind-no-results-toast's desktop offset so the panel and the
           toast sit at the same distance below the search bar (ends ~168px). */
        top: 184px !important;
        left: 50% !important;
        transform: translateX(-50%) !important;
        z-index: 9997 !important;
        width: 90% !important;
        max-width: 420px !important;
        background: rgba(255, 255, 255, 0.97) !important;
        backdrop-filter: blur(12px) !important;
        border-radius: 16px !important;
        padding: 14px 18px !important;
        box-shadow: 0 8px 32px rgba(25, 25, 112, 0.12) !important;
        border: 1px solid rgba(148, 0, 211, 0.15) !important;
        font-family: system-ui, -apple-system, sans-serif !important;
    }
    .nearest-suggestion-panel .suggestion-header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 10px;
    }
    .nearest-suggestion-panel .suggestion-header i {
        color: #9400D3;
        font-size: 14px;
    }
    .nearest-suggestion-panel .suggestion-header span {
        font-size: 12px;
        font-weight: 700;
        color: #191970;
    }
    .nearest-suggestion-panel .suggestion-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 12px;
        border-radius: 12px;
        background: rgba(148, 0, 211, 0.04);
        border: 1px solid rgba(148, 0, 211, 0.08);
        margin-bottom: 8px;
    }
    .nearest-suggestion-panel .suggestion-item:last-child {
        margin-bottom: 0;
    }
    .nearest-suggestion-panel .suggestion-info {
        flex: 1;
        min-width: 0;
    }
    .nearest-suggestion-panel .suggestion-name {
        font-size: 13px;
        font-weight: 700;
        color: #191970;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .nearest-suggestion-panel .suggestion-meta {
        font-size: 11px;
        color: #64748b;
        margin-top: 2px;
    }
    .nearest-suggestion-panel .suggestion-meta .price {
        color: #9400D3;
        font-weight: 700;
    }
    .nearest-suggestion-panel .suggestion-actions {
        display: flex;
        gap: 6px;
        flex-shrink: 0;
    }
    .nearest-suggestion-panel .btn-directions {
        background: #191970;
        color: #D9F855;
        border: none;
        padding: 7px 12px;
        border-radius: 9999px;
        font-size: 10px;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
    }
    .nearest-suggestion-panel .btn-directions:hover {
        opacity: 0.85;
    }
    .nearest-suggestion-panel .btn-view {
        background: #9400D3;
        color: #fff;
        border: none;
        padding: 7px 12px;
        border-radius: 9999px;
        font-size: 10px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
    }
    .nearest-suggestion-panel .btn-view:hover {
        opacity: 0.85;
    }
    .nearest-suggestion-panel .close-suggestion {
        position: absolute;
        top: 10px;
        right: 14px;
        background: none;
        border: none;
        color: #94a3b8;
        font-size: 18px;
        cursor: pointer;
        line-height: 1;
    }
    .nearest-suggestion-panel .close-suggestion:hover {
        color: #191970;
    }

    /* Controlled MedFind turn-by-turn panel. Google renders the map route while
       this panel owns the selected route's readable A-to-B instructions. */
    .google-directions-panel {
        --directions-surface: rgba(255, 255, 255, 0.98);
        --directions-surface-solid: #ffffff;
        --directions-text: #475569;
        --directions-heading: #191970;
        --directions-distance: #9400D3;
        --directions-muted: #7c879b;
        --directions-tint: rgba(148, 0, 211, 0.045);
        --directions-border: rgba(148, 0, 211, 0.14);
        --directions-row-border: rgba(148, 0, 211, 0.09);
        --directions-shadow: 0 14px 38px rgba(25, 25, 112, 0.16);
        display: none;
        position: fixed;
        top: 188px;
        right: 20px;
        z-index: 9998;
        width: min(370px, calc(100vw - 40px));
        max-height: min(56vh, calc(100dvh - 336px));
        overflow-x: hidden;
        overflow-y: auto;
        box-sizing: border-box;
        background: var(--directions-surface);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        border: 1px solid var(--directions-border);
        border-radius: 16px;
        box-shadow: var(--directions-shadow);
        color: var(--directions-text);
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        font-size: 13px;
        line-height: 1.45;
        overscroll-behavior: contain;
        scrollbar-width: thin;
        scrollbar-color: rgba(148, 0, 211, 0.28) transparent;
        -webkit-overflow-scrolling: touch;
    }
    body.directions-open .google-directions-panel {
        display: block;
    }
    /* Controlled route markup replaces Google provider tables and alternatives. */
    .google-directions-panel::before {
        content: none;
        display: none;
    }
    .mfd-route {
        width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
    .mfd-route-header {
        position: sticky;
        top: 0;
        z-index: 2;
        padding: 16px 18px 13px;
        background: var(--directions-surface-solid);
        border-bottom: 1px solid var(--directions-border);
        box-shadow: 0 4px 12px rgba(25, 25, 112, 0.045);
    }
    .mfd-route-title {
        margin: 0;
        color: var(--directions-heading);
        font-size: 15px;
        font-weight: 800;
        line-height: 1.3;
        overflow-wrap: anywhere;
    }
    .mfd-route-meta {
        margin: 7px 0 0;
        color: var(--directions-heading);
        font-size: 13px;
        font-weight: 700;
        line-height: 1.3;
    }
    .mfd-route-steps {
        width: 100%;
        min-width: 0;
        padding: 0;
        margin: 0;
        list-style: none;
    }
    .mfd-route-step {
        display: grid;
        grid-template-columns: 44px minmax(0, 1fr) 62px;
        align-items: center;
        width: 100%;
        min-width: 0;
        box-sizing: border-box;
        padding: 11px 14px;
        border-bottom: 1px solid var(--directions-row-border);
        background: var(--directions-surface-solid);
    }
    .mfd-route-step:nth-child(even) {
        background: var(--directions-tint);
    }
    .mfd-route-step.is-interactive {
        cursor: pointer;
        transition: background-color 0.15s ease, box-shadow 0.15s ease;
    }
    .mfd-route-step.is-interactive:hover,
    .mfd-route-step.is-interactive:focus-visible {
        background: rgba(148, 0, 211, 0.09);
        outline: none;
        box-shadow: inset 3px 0 0 rgba(148, 0, 211, 0.5);
    }
    .mfd-route-step.is-selected {
        background: rgba(148, 0, 211, 0.14) !important;
        box-shadow: inset 4px 0 0 #9400D3;
    }
    .mfd-route-step.is-selected .mfd-route-endpoint,
    .mfd-route-step.is-selected .mfd-route-maneuver,
    .mfd-route-step.is-selected .mfd-route-instruction {
        color: #9400D3;
    }
    .mfd-route-step:last-child {
        border-bottom: 0;
    }
    .mfd-route-endpoint,
    .mfd-route-maneuver {
        display: inline-flex;
        width: 32px;
        height: 32px;
        align-items: center;
        justify-content: center;
        justify-self: start;
        color: var(--directions-heading);
        font-size: 21px;
        font-weight: 800;
        line-height: 1;
    }
    .mfd-route-endpoint {
        font-size: 20px;
    }
    .mfd-route-copy {
        min-width: 0;
        padding-right: 8px;
    }
    .mfd-route-instruction {
        color: var(--directions-text);
        font-size: 13px;
        font-weight: 500;
        line-height: 1.42;
        overflow-wrap: anywhere;
    }
    .mfd-route-detail {
        margin-top: 2px;
        color: var(--directions-muted);
        font-size: 11px;
        font-weight: 400;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }
    .mfd-route-distance {
        min-width: 0;
        justify-self: end;
        color: var(--directions-distance);
        font-size: 12px;
        font-weight: 800;
        line-height: 1.25;
        text-align: right;
        white-space: nowrap;
    }
    .mfd-route-step-end .mfd-route-instruction {
        color: var(--directions-muted);
    }
    .google-directions-panel::-webkit-scrollbar {
        width: 5px;
        height: 0;
    }
    .google-directions-panel::-webkit-scrollbar-track {
        background: transparent;
    }
    .google-directions-panel::-webkit-scrollbar-thumb {
        background: rgba(148, 0, 211, 0.28);
        border-radius: 9999px;
    }
    .google-directions-panel::-webkit-scrollbar-thumb:hover {
        background: rgba(148, 0, 211, 0.42);
    }

    /* Route summary card: a compact rounded card, centered at the bottom.
       Column layout so the summary sits on top and the two action buttons form
       a neat row below. The JS toggles display between "none" and "flex"; the
       flex-direction:column here means "flex" always lays it out as a column. */
    #routeInfoBar {
        display: none;
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
        position: fixed;
        bottom: 24px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 9999;
        width: calc(100% - 24px);
        max-width: 480px;
        background: #ffffff;
        border-radius: 16px;
        padding: 12px 16px;
        box-shadow: 0 4px 20px rgba(25, 25, 112, 0.15);
        border: 1px solid rgba(148, 0, 211, 0.12);
        font-family: system-ui, -apple-system, sans-serif;
        box-sizing: border-box;
    }
    /* "X km · approx Y min" summary line */
    #routeSummary {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        font-size: 14px;
        font-weight: 700;
        color: #191970;
        text-align: center;
    }
    /* Row holding the two balanced action buttons */
    #routeActions {
        display: flex;
        align-items: stretch;
        gap: 10px;
        min-width: 0;
    }

    /* Optional route alternatives live in a separate popover above the summary. */
    .route-alternatives-panel {
        position: absolute;
        right: 0;
        bottom: calc(100% + 10px);
        left: 0;
        z-index: 10001;
        display: none;
        max-height: min(320px, 44vh);
        padding: 12px;
        overflow-x: hidden;
        overflow-y: auto;
        border: 1px solid rgba(148, 0, 211, 0.16);
        border-radius: 16px;
        background: rgba(255, 255, 255, 0.98);
        box-shadow: 0 12px 32px rgba(25, 25, 112, 0.18);
        box-sizing: border-box;
        overscroll-behavior: contain;
    }
    body.route-alternatives-open .route-alternatives-panel {
        display: block;
    }
    .route-alternatives-panel[hidden],
    #toggleRoutesBtn[hidden] {
        display: none !important;
    }
    .route-alternatives-header {
        display: flex;
        flex-direction: column;
        gap: 3px;
        padding: 2px 2px 10px;
        border-bottom: 1px solid rgba(148, 0, 211, 0.1);
    }
    .route-alternatives-header strong {
        color: #191970;
        font-size: 14px;
        font-weight: 800;
        line-height: 1.25;
    }
    .route-alternatives-header span {
        color: #64748b;
        font-size: 11px;
        line-height: 1.35;
    }
    .route-alternatives-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding-top: 10px;
    }
    .route-alternative-option {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 10px;
        width: 100%;
        min-width: 0;
        padding: 10px 12px;
        border: 1px solid rgba(148, 0, 211, 0.12);
        border-radius: 12px;
        background: #ffffff;
        color: #334155;
        font-family: inherit;
        text-align: left;
        cursor: pointer;
        transition: border-color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
    }
    .route-alternative-option:hover,
    .route-alternative-option:focus-visible {
        border-color: rgba(148, 0, 211, 0.42);
        background: rgba(148, 0, 211, 0.035);
        outline: none;
    }
    .route-alternative-option.is-active {
        border-color: #9400D3;
        background: rgba(148, 0, 211, 0.07);
        box-shadow: inset 3px 0 0 #9400D3;
    }
    .route-alternative-copy {
        display: flex;
        min-width: 0;
        flex-direction: column;
        gap: 4px;
    }
    .route-alternative-name {
        color: #191970;
        font-size: 12.5px;
        font-weight: 750;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }
    .route-alternative-meta {
        color: #64748b;
        font-size: 11px;
        font-weight: 600;
        line-height: 1.25;
    }
    .route-alternative-badges {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        justify-content: center;
        gap: 5px;
    }
    .route-alternative-badge,
    .route-alternative-selected {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 3px 7px;
        border-radius: 9999px;
        font-size: 9px;
        font-weight: 800;
        line-height: 1.2;
        white-space: nowrap;
    }
    .route-alternative-badge {
        background: rgba(217, 248, 85, 0.45);
        color: #191970;
    }
    .route-alternative-selected {
        background: #191970;
        color: #D9F855;
    }
    #toggleRoutesBtn {
        flex: 1 !important;
        min-width: 0 !important;
        min-height: 44px !important;
        padding: 0 12px !important;
        border: 1px solid rgba(148, 0, 211, 0.22) !important;
        border-radius: 9999px !important;
        background: rgba(148, 0, 211, 0.08) !important;
        color: #191970 !important;
        box-shadow: 0 2px 8px rgba(148, 0, 211, 0.12) !important;
        font-family: system-ui, -apple-system, sans-serif !important;
        font-size: 12px !important;
        font-weight: 700 !important;
        white-space: nowrap !important;
        cursor: pointer !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 5px !important;
    }
    #toggleRoutesBtn:hover,
    #toggleRoutesBtn:focus-visible,
    body.route-alternatives-open #toggleRoutesBtn {
        border-color: #9400D3 !important;
        background: #9400D3 !important;
        color: #ffffff !important;
        outline: none !important;
    }
    #toggleRoutesBtn:disabled {
        cursor: not-allowed !important;
        opacity: 0.55 !important;
        box-shadow: none !important;
    }

    /* "View steps" / "Hide steps" toggle button */
    #toggleStepsBtn {
        flex: 1 !important;
        min-width: 0 !important;
        background: #191970 !important;
        color: #D9F855 !important;
        border: none !important;
        padding: 0 16px !important;
        min-height: 44px !important;
        border-radius: 9999px !important;
        font-weight: 700 !important;
        font-size: 13px !important;
        cursor: pointer !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 6px !important;
        box-shadow: 0 2px 8px rgba(25, 25, 112, 0.25) !important;
        white-space: nowrap !important;
        transition: background 0.2s ease !important;
        font-family: system-ui, -apple-system, sans-serif !important;
    }
    #toggleStepsBtn:hover {
        background: #2a2a8a !important;
    }
    /* Clear Route button - purple, balanced beside the toggle */
    #clearRouteBtn {
        flex: 1 !important;
        min-width: 0 !important;
        background: #9400D3 !important;
        color: #ffffff !important;
        border: none !important;
        padding: 0 16px !important;
        min-height: 44px !important;
        border-radius: 9999px !important;
        font-weight: 700 !important;
        font-size: 13px !important;
        cursor: pointer !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 6px !important;
        box-shadow: 0 2px 8px rgba(148, 0, 211, 0.3) !important;
        white-space: nowrap !important;
        transition: background 0.2s ease !important;
        font-family: system-ui, -apple-system, sans-serif !important;
    }
    #clearRouteBtn:hover {
        background: #a916e0 !important;
    }
    #toggleStepsBtn:disabled {
        cursor: not-allowed !important;
        opacity: 0.55 !important;
        box-shadow: none !important;
    }
    #toggleStepsBtn .chevron {
        transition: transform 0.2s ease !important;
    }
    /* Rotate the chevron when steps are open */
    body.directions-open #toggleStepsBtn .chevron {
        transform: rotate(180deg) !important;
    }

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
        white-space: nowrap !important;
        max-width: calc(100% - 24px) !important;
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
        padding: 10px 12px !important;
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
    
    @media (max-width: 640px) {
        .stats-bar-fixed {
            display: none !important;
        }
        .search-panel {
            top: 84px !important;
            width: calc(100% - 24px) !important;
        }
        .search-card-minimal {
            padding: 5px 5px 5px 14px !important;
        }
        /* 16px input keeps iOS from auto-zooming the map view on focus.
           Horizontal padding must stay non-zero so typed text and the caret
           don't sit flush against the pill's edge. */
        .search-card-minimal input {
            font-size: 16px !important;
            padding: 9px 12px !important;
        }
        .search-card-minimal button {
            padding: 8px 14px !important;
            font-size: 12px !important;
        }
        .chat-modal-fixed {
            width: calc(100% - 32px) !important;
            right: 16px !important;
            bottom: 76px !important;
        }
        .autocomplete-items {
            width: 100% !important;
        }
        /* Active messenger-style chat window fits small screens */
        #activeChatWindow {
            width: calc(100vw - 24px) !important;
            right: 12px !important;
            left: 12px !important;
            bottom: 12px !important;
        }
        /* Controlled directions become a compact bottom sheet above route controls. */
        body.directions-open .google-directions-panel {
            position: fixed;
            top: auto;
            right: 12px;
            bottom: 148px;
            left: 12px;
            width: calc(100vw - 24px);
            max-height: min(42vh, calc(100dvh - 260px));
            border-radius: 16px;
            z-index: 9998;
        }
        .mfd-route-header {
            padding: 14px 16px 12px;
        }
        .mfd-route-title {
            font-size: 14px;
        }
        .mfd-route-meta {
            margin-top: 6px;
            font-size: 12.5px;
        }
        .mfd-route-step {
            grid-template-columns: 42px minmax(0, 1fr) 58px;
            padding: 10px 12px;
        }
        .mfd-route-copy {
            padding-right: 6px;
        }
        .mfd-route-instruction {
            font-size: 12.5px;
        }
        .mfd-route-distance {
            font-size: 11.5px;
        }
        .route-alternatives-panel {
            bottom: calc(100% + 8px);
            max-height: min(36vh, 280px);
            padding: 10px;
            border-radius: 14px;
        }
        .route-alternatives-header {
            padding-bottom: 8px;
        }
        .route-alternative-option {
            gap: 8px;
            padding: 9px 10px;
        }
        .route-alternative-name {
            font-size: 12px;
        }
        .route-alternative-badges {
            gap: 4px;
        }
        #routeActions {
            gap: 6px;
        }
        #toggleRoutesBtn,
        #toggleStepsBtn,
        #clearRouteBtn {
            min-height: 42px !important;
            padding: 0 7px !important;
            gap: 4px !important;
            font-size: 11px !important;
        }
        /* The sheet's 148px bottom offset clears the enlarged route card at
           bottom:24px, preserving a comfortable gap on compact screens. */
        /* Mobile search bar starts at 84px and is ~46px tall, so 146px keeps the
           same gap the "no pharmacies found" toast uses on this breakpoint. */
        .nearest-suggestion-panel {
            top: 146px !important;
        }
        /* Nearest suggestion buttons stay tappable */
        .nearest-suggestion-panel .btn-directions,
        .nearest-suggestion-panel .btn-view {
            padding: 8px 12px !important;
            font-size: 11px !important;
        }
        .nearest-suggestion-panel .suggestion-name {
            font-size: 14px !important;
        }
    }

    /* -- Dark mode overrides for map UI panels ------------------- */
    html.dark .stats-bar-fixed {
        background: rgba(15, 15, 35, 0.97) !important;
        border-color: rgba(148, 0, 211, 0.3) !important;
        box-shadow: 0 4px 20px rgba(0,0,0,0.4) !important;
    }
    html.dark .stats-bar-fixed .stat-number {
        color: #e2e8f0 !important;
    }
    html.dark .stats-bar-fixed .stat-label {
        color: #94a3b8 !important;
    }
    html.dark .stats-bar-fixed .stat-divider {
        background: rgba(255,255,255,0.12) !important;
    }
    html.dark .search-card-minimal {
        background: rgba(15, 15, 35, 0.97) !important;
        border-color: rgba(148, 0, 211, 0.35) !important;
        box-shadow: 0 4px 20px rgba(0,0,0,0.4) !important;
    }
    html.dark .search-card-minimal input {
        color: #e2e8f0 !important;
        background: transparent !important;
    }
    /* The global `html.dark input:not([type="checkbox"])...:not([type="hidden"])`
       rule in resources/css/app.css has higher specificity than the rule above,
       so it wins and paints a nested dark rectangle inside the search pill.
       The id selector here outranks it, scoped to this input only. */
    html.dark .search-panel .search-card-minimal input#medicineSearch {
        background: transparent !important;
        background-color: transparent !important;
        color: #e2e8f0 !important;
    }
    html.dark .search-card-minimal input::placeholder {
        color: #475569 !important;
    }
    html.dark .autocomplete-items {
        background: rgba(15, 15, 35, 0.98) !important;
        border-color: rgba(148, 0, 211, 0.3) !important;
    }
    html.dark .autocomplete-item {
        color: #e2e8f0 !important;
        border-color: rgba(255,255,255,0.06) !important;
    }
    html.dark .autocomplete-item:hover {
        background: rgba(148, 0, 211, 0.15) !important;
    }
    html.dark .nearest-suggestion-panel {
        background: rgba(15, 15, 35, 0.97) !important;
        border-color: rgba(148, 0, 211, 0.3) !important;
        box-shadow: 0 8px 32px rgba(0,0,0,0.5) !important;
    }
    html.dark .nearest-suggestion-panel .suggestion-header span {
        color: #e2e8f0 !important;
    }
    html.dark .nearest-suggestion-panel .suggestion-item {
        background: rgba(148, 0, 211, 0.1) !important;
        border-color: rgba(148, 0, 211, 0.2) !important;
    }
    html.dark .nearest-suggestion-panel .suggestion-name {
        color: #e2e8f0 !important;
    }
    html.dark .nearest-suggestion-panel .suggestion-meta {
        color: #94a3b8 !important;
    }
    html.dark .nearest-suggestion-panel .close-suggestion {
        color: #64748b !important;
    }
    html.dark .nearest-suggestion-panel .close-suggestion:hover {
        color: #e2e8f0 !important;
    }
    html.dark #routeInfoBar {
        background: rgba(15, 15, 35, 0.97) !important;
        border-color: rgba(148, 0, 211, 0.3) !important;
    }
    html.dark #routeSummary {
        color: #e2e8f0 !important;
    }
    html.dark .route-alternatives-panel {
        border-color: rgba(196, 91, 234, 0.28);
        background: rgba(29, 29, 64, 0.98);
        box-shadow: 0 12px 32px rgba(8, 8, 24, 0.5);
    }
    html.dark .route-alternatives-header {
        border-bottom-color: rgba(196, 91, 234, 0.18);
    }
    html.dark .route-alternatives-header strong,
    html.dark .route-alternative-name {
        color: #f8fafc;
    }
    html.dark .route-alternatives-header span,
    html.dark .route-alternative-meta {
        color: #aeb4c5;
    }
    html.dark .route-alternative-option {
        border-color: rgba(196, 91, 234, 0.18);
        background: rgba(255, 255, 255, 0.035);
        color: #d5d8e3;
    }
    html.dark .route-alternative-option:hover,
    html.dark .route-alternative-option:focus-visible {
        border-color: rgba(196, 91, 234, 0.48);
        background: rgba(148, 0, 211, 0.11);
    }
    html.dark .route-alternative-option.is-active {
        border-color: #c45bea;
        background: rgba(148, 0, 211, 0.17);
        box-shadow: inset 3px 0 0 #c45bea;
    }
    html.dark .route-alternative-badge {
        background: rgba(217, 248, 85, 0.16);
        color: #D9F855;
    }
    html.dark .route-alternative-selected {
        background: #D9F855;
        color: #191970;
    }
    html.dark #toggleRoutesBtn {
        border-color: rgba(196, 91, 234, 0.32) !important;
        background: rgba(148, 0, 211, 0.16) !important;
        color: #D9F855 !important;
    }
    html.dark body.route-alternatives-open #toggleRoutesBtn,
    html.dark #toggleRoutesBtn:hover,
    html.dark #toggleRoutesBtn:focus-visible {
        border-color: #c45bea !important;
        background: #9400D3 !important;
        color: #ffffff !important;
    }
    html.dark .google-directions-panel {
        --directions-surface: rgba(29, 29, 64, 0.98);
        --directions-surface-solid: #202047;
        --directions-text: #d5d8e3;
        --directions-heading: #ffffff;
        --directions-distance: #D9F855;
        --directions-muted: #aeb4c5;
        --directions-tint: rgba(148, 0, 211, 0.11);
        --directions-border: rgba(196, 91, 234, 0.25);
        --directions-row-border: rgba(196, 91, 234, 0.16);
        --directions-shadow: 0 14px 38px rgba(8, 8, 24, 0.45);
    }
    html.dark .mfd-route-header {
        box-shadow: 0 4px 12px rgba(8, 8, 24, 0.2);
    }
    html.dark .mfd-route-step:nth-child(even) {
        background: rgba(148, 0, 211, 0.13);
    }
    html.dark .mfd-route-step.is-interactive:hover,
    html.dark .mfd-route-step.is-interactive:focus-visible {
        background: rgba(148, 0, 211, 0.2);
    }
    html.dark .mfd-route-step.is-selected {
        background: rgba(148, 0, 211, 0.28) !important;
        box-shadow: inset 4px 0 0 #D9F855;
    }
    html.dark .mfd-route-step.is-selected .mfd-route-endpoint,
    html.dark .mfd-route-step.is-selected .mfd-route-maneuver,
    html.dark .mfd-route-step.is-selected .mfd-route-instruction {
        color: #D9F855;
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

            // Auto-refresh active chat window if open
            if (activeChatPharmacyId) {
                const activeConv = conversationsData.find(c => c.pharmacy_id == activeChatPharmacyId);
                if (activeConv) {
                    renderActiveChatMessages(activeConv);
                    const msgs = document.getElementById('activeChatMessages');
                    if (msgs) msgs.scrollTop = msgs.scrollHeight;
                }
            }
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
            // On hover: switch to close ?
            head.onmouseenter = function() {
                cornerBtn.textContent = '?';
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
            // No unread � show close only on hover, hidden otherwise
            cornerBtn.style.cssText = baseStyle + 'background:#6b7280;color:#fff;opacity:0;';
            cornerBtn.textContent = '?';
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
        if (msg.sender === 'pharmacy') {
            // Pharmacy message (left side)
            const pharmacyRow = document.createElement('div');
            pharmacyRow.style.cssText = 'display:flex;justify-content:flex-start;gap:6px;align-items:flex-end;margin-bottom:8px;';
            pharmacyRow.innerHTML =
                '<div style="width:26px;height:26px;border-radius:50%;background:#191970;display:flex;align-items:center;justify-content:center;flex-shrink:0;">' +
                    '<i class="fas fa-store" style="color:#D9F855;font-size:10px;"></i>' +
                '</div>' +
                '<div style="max-width:75%;">' +
                    '<div style="background:#fff;border:1px solid rgba(148,0,211,0.15);color:#191970;font-size:12px;font-weight:500;padding:8px 14px;border-radius:16px 16px 16px 4px;line-height:1.4;">' + escHtml(msg.message) + '</div>' +
                    '<p style="font-size:10px;color:#94a3b8;margin-top:2px;">' + escHtml(conv.pharmacy_name) + '</p>' +
                '</div>';
            container.appendChild(pharmacyRow);
        } else {
            // Consumer message (right side)
            const consumerRow = document.createElement('div');
            consumerRow.style.cssText = 'display:flex;justify-content:flex-end;gap:6px;align-items:flex-end;margin-bottom:8px;';
            consumerRow.innerHTML =
                '<div style="max-width:75%;">' +
                    '<div style="background:#191970;color:#D9F855;font-size:12px;font-weight:500;padding:8px 14px;border-radius:16px 16px 4px 16px;line-height:1.4;">' + escHtml(msg.message) + '</div>' +
                    '<p style="font-size:10px;color:#94a3b8;text-align:right;margin-top:2px;">You</p>' +
                '</div>' +
                '<div style="width:26px;height:26px;border-radius:50%;background:rgba(148,0,211,0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">' +
                    '<i class="fas fa-user" style="color:#9400D3;font-size:10px;"></i>' +
                '</div>';
            container.appendChild(consumerRow);

            // Legacy: if msg.reply exists (old format), also show it
            if (msg.reply) {
                const replyRow = document.createElement('div');
                replyRow.style.cssText = 'display:flex;justify-content:flex-start;gap:6px;align-items:flex-end;margin-bottom:8px;';
                replyRow.innerHTML =
                    '<div style="width:26px;height:26px;border-radius:50%;background:#191970;display:flex;align-items:center;justify-content:center;flex-shrink:0;">' +
                        '<i class="fas fa-store" style="color:#D9F855;font-size:10px;"></i>' +
                    '</div>' +
                    '<div style="max-width:75%;">' +
                        '<div style="background:#fff;border:1px solid rgba(148,0,211,0.15);color:#191970;font-size:12px;font-weight:500;padding:8px 14px;border-radius:16px 16px 16px 4px;line-height:1.4;">' + escHtml(msg.reply) + '</div>' +
                        '<p style="font-size:10px;color:#94a3b8;margin-top:2px;">' + escHtml(conv.pharmacy_name) + '</p>' +
                    '</div>';
                container.appendChild(replyRow);
            }
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
        setInterval(loadChatHeads, 5000);
    }
    @endauth
});
</script>@endsection
