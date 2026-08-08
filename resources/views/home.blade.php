@extends('layouts.app')

@section('content')
<div class="map-wrapper" style="position: relative; height: 100vh; width: 100%;">
    
    <!-- Stats Bar -->
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
            <input type="text" id="medicineSearch" placeholder="Search for a medicine..." autocomplete="off" spellcheck="false">
            <button id="searchBtn" type="button">Search</button>
        </div>
        <div id="autocompleteList" class="autocomplete-items"></div>
    </div>

    <!-- Map Container -->
    <div id="medfindMap" style="height: 100%; width: 100%;"></div>

    <!-- Chat Button -->
    <div class="chat-float-fixed">
        <button onclick="toggleChat()" class="chat-toggle-btn">
            <i class="fas fa-comment"></i>
        </button>
    </div>

    <!-- Chat Modal -->
    <div id="chatModal" class="chat-modal-fixed">
        <div class="chat-header" onclick="toggleChat()">
            <span>MedFind</span>
            <span>✕</span>
        </div>
        <div id="chatMessages" class="chat-messages">
            <div class="message received">
                <div class="bubble">Hi! How can I help you today?</div>
            </div>
        </div>
        <div class="chat-input-area">
            <input type="text" id="chatInput" placeholder="Type a message...">
            <button onclick="sendChatMessage()">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
</div>

<script>
    const pharmaciesData = @json($formattedPharmacies ?? []);
    const allMedicineNames = @json($medicineNames ?? []);
    
    console.log('Pharmacies Data:', pharmaciesData);
    console.log('Medicine Names:', allMedicineNames);
</script>

<style>
    .map-wrapper {
        position: relative;
        height: 100vh;
        width: 100%;
        overflow: hidden;
    }
    
    #medfindMap {
        height: 100%;
        width: 100%;
        z-index: 1;
    }
    
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
        position: relative !important;
        z-index: 9999 !important;
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
        pointer-events: auto !important;
        position: relative !important;
        z-index: 9999 !important;
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
        pointer-events: auto !important;
        position: relative !important;
        z-index: 9999 !important;
    }
    
    .search-card-minimal button:hover {
        background: #2a2a8a !important;
    }
    
    /* AUTOCOMPLETE STYLES - COMPLETELY FIXED */
    .autocomplete-items {
        position: absolute !important;
        top: calc(100% + 4px) !important;
        left: 0 !important;
        right: 0 !important;
        width: 100% !important;
        background: #ffffff !important;
        border-radius: 14px !important;
        box-shadow: 0 8px 30px rgba(25, 25, 112, 0.12) !important;
        max-height: 220px !important;
        overflow-y: auto !important;
        z-index: 99999 !important;
        border: 1px solid rgba(148, 0, 211, 0.12) !important;
        display: none !important;
        pointer-events: auto !important;
        margin-top: 2px !important;
    }
    
    .autocomplete-items.active {
        display: block !important;
    }
    
    .autocomplete-item {
        padding: 12px 16px !important;
        cursor: pointer !important;
        font-size: 13px !important;
        color: #1a1a2e !important;
        border-bottom: 1px solid rgba(148, 0, 211, 0.06) !important;
        pointer-events: auto !important;
        transition: background 0.15s ease !important;
        user-select: none !important;
        -webkit-user-select: none !important;
        position: relative !important;
        z-index: 99999 !important;
    }
    
    .autocomplete-item:hover {
        background: rgba(148, 0, 211, 0.08) !important;
    }
    
    .autocomplete-item:active {
        background: rgba(148, 0, 211, 0.15) !important;
    }
    
    .autocomplete-item strong {
        color: #9400D3 !important;
        font-weight: 700 !important;
    }
    
    .autocomplete-item:last-child {
        border-bottom: none !important;
    }
    
    /* Scrollbar for autocomplete */
    .autocomplete-items::-webkit-scrollbar {
        width: 5px;
    }
    .autocomplete-items::-webkit-scrollbar-track {
        background: rgba(148, 0, 211, 0.05);
        border-radius: 10px;
    }
    .autocomplete-items::-webkit-scrollbar-thumb {
        background: rgba(148, 0, 211, 0.25);
        border-radius: 10px;
    }
    .autocomplete-items::-webkit-scrollbar-thumb:hover {
        background: rgba(148, 0, 211, 0.4);
    }
    
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
        pointer-events: auto !important;
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
        pointer-events: auto !important;
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
        pointer-events: auto !important;
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
        pointer-events: auto !important;
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
        pointer-events: auto !important;
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
        pointer-events: auto !important;
    }
    
    .chat-input-area button:hover {
        background: #2a2a8a !important;
    }
    
    .leaflet-popup {
        z-index: 9999 !important;
        pointer-events: auto !important;
    }
    
    .leaflet-popup-content-wrapper {
        z-index: 9999 !important;
        pointer-events: auto !important;
        background: transparent !important;
        box-shadow: 0 20px 25px -5px rgba(25, 25, 112, 0.15), 0 8px 10px -6px rgba(25, 25, 112, 0.1) !important;
        padding: 0 !important;
        border-radius: 24px !important;
        overflow: visible !important;
        border: none !important;
    }
    
    .leaflet-popup-content {
        margin: 0 !important;
        width: 100% !important;
        line-height: 1.4 !important;
        pointer-events: auto !important;
        z-index: 9999 !important;
    }
    
    .leaflet-popup-content * {
        pointer-events: auto !important;
    }
    
    .leaflet-popup-content button {
        pointer-events: auto !important;
        cursor: pointer !important;
        z-index: 9999 !important;
        position: relative !important;
    }
    
    .leaflet-popup-content div {
        pointer-events: auto !important;
    }
    
    .leaflet-popup-tip-container {
        z-index: 9999 !important;
    }
    
    .leaflet-popup-tip {
        background: #ffffff !important;
        box-shadow: none !important;
        z-index: 9999 !important;
    }
    
    .leaflet-popup-close-button {
        display: none !important;
    }
    
    .leaflet-control-container {
        z-index: 100 !important;
    }
    
    .leaflet-control-zoom {
        z-index: 100 !important;
    }
    
    .leaflet-control-zoom a {
        pointer-events: auto !important;
        background: white !important;
        color: #191970 !important;
    }
    
    .leaflet-control-zoom a:hover {
        background: #f0f0ff !important;
    }
    
    .leaflet-control-attribution {
        z-index: 100 !important;
    }
    
    .custom-popup .leaflet-popup-content-wrapper {
        pointer-events: auto !important;
    }
    
    .custom-popup .leaflet-popup-content {
        pointer-events: auto !important;
    }
    
    .custom-popup .leaflet-popup-content button {
        pointer-events: auto !important;
        cursor: pointer !important;
        z-index: 9999 !important;
        position: relative !important;
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
@endsection