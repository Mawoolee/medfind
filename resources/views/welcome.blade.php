<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MedFind - Real-Time Pharmaceutical Inventory Locator | Legazpi City</title>
    
    <!-- Leaflet CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #f0f4f8;
            color: #1e293b;
            overflow-x: hidden;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #0a3b4e 0%, #1a6d5e 100%);
            color: white;
            padding: 16px 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.1);
        }

        .header h1 {
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .header p {
            font-size: 0.85rem;
            opacity: 0.9;
            margin-top: 4px;
        }

        .stats-row {
            display: flex;
            gap: 20px;
            margin-top: 12px;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-start;
        }

        .stat-badge {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(8px);
            padding: 8px 24px;
            border-radius: 40px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-width: 180px;
            white-space: nowrap;
        }

        .stat-badge strong {
            font-size: 1.2rem;
            font-weight: 700;
        }

        /* Map container */
        .map-container {
            position: relative;
            height: calc(100vh - 130px);
            width: 100%;
        }

        #medfindMap {
            height: 100%;
            width: 100%;
            background: #c8e0e0;
        }

        /* Floating search panel */
        .search-panel {
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            z-index: 1000;
            display: flex;
            justify-content: center;
            pointer-events: none;
        }

        .search-card {
            background: white;
            border-radius: 50px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            padding: 6px 6px 6px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            max-width: 550px;
            width: 90%;
            pointer-events: auto;
            border: 1px solid rgba(0,0,0,0.05);
            position: relative;
        }

        .search-card input {
            flex: 1;
            border: none;
            padding: 14px 0;
            font-size: 1rem;
            font-family: inherit;
            outline: none;
            background: transparent;
        }

        .search-card button {
            background: linear-gradient(135deg, #1a6d5e, #0f5447);
            border: none;
            color: white;
            padding: 10px 28px;
            border-radius: 40px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        .search-card button:hover {
            background: linear-gradient(135deg, #0f5447, #0a3b4e);
            transform: scale(0.97);
        }

        /* Autocomplete dropdown */
        .autocomplete-items {
            position: absolute;
            top: 100%;
            left: 24px;
            right: 100px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            max-height: 280px;
            overflow-y: auto;
            z-index: 1001;
            margin-top: 8px;
        }

        .autocomplete-item {
            padding: 12px 20px;
            cursor: pointer;
            transition: background 0.2s;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.9rem;
        }

        .autocomplete-item:hover {
            background: #eef2ff;
        }

        .autocomplete-item strong {
            color: #1a6d5e;
        }

        /* Custom popup styling */
        .custom-popup .leaflet-popup-content-wrapper {
            border-radius: 20px;
            padding: 0;
            box-shadow: 0 5px 25px rgba(0,0,0,0.25);
            min-width: 360px;
            max-width: 400px;
        }

        .custom-popup .leaflet-popup-content {
            margin: 0 !important;
            width: auto !important;
            min-width: 360px;
        }

        .custom-popup .leaflet-popup-tip {
            background: white;
        }

        /* I-hide ang default Leaflet close button */
        .custom-popup .leaflet-popup-close-button {
            display: none;
        }

        .pharmacy-popup {
            font-family: 'Inter', sans-serif;
            padding: 18px 18px 16px 18px;
            min-width: 340px;
            position: relative;
        }

        .pharmacy-popup h4 {
            font-size: 1.15rem;
            font-weight: 700;
            color: #0a3b4e;
            margin-bottom: 8px;
            padding-right: 32px;
        }

        .pharmacy-popup .address {
            font-size: 0.75rem;
            color: #5b6e8c;
            margin-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 8px;
        }

        .stock-info {
            background: #eef2ff;
            padding: 12px 14px;
            border-radius: 16px;
            margin: 12px 0;
        }

        .stock-info strong {
            font-size: 0.8rem;
            color: #1e40af;
            display: block;
            margin-bottom: 8px;
        }

        .med-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px dashed #cbd5e1;
            flex-wrap: wrap;
            gap: 6px;
        }

        .med-item:last-child {
            border-bottom: none;
        }

        .med-name-wrapper {
            flex: 2;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
        }

        .med-name {
            font-weight: 600;
            font-size: 0.85rem;
            color: #1e293b;
        }

        .rx-badge {
            display: inline-block;
            background: #f59e0b;
            color: white;
            font-size: 0.6rem;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 500;
            white-space: nowrap;
        }

        .med-price-stock {
            text-align: right;
            flex-shrink: 0;
            white-space: nowrap;
        }

        .med-price {
            font-weight: 700;
            color: #059669;
            font-size: 0.85rem;
        }

        .med-stock {
            display: inline-block;
            background: #10b981;
            color: white;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 8px;
        }

        .med-stock.low {
            background: #f59e0b;
        }

        .med-stock.out {
            background: #ef4444;
        }

        .distance-text {
            font-size: 0.7rem;
            color: #3b5e7a;
            margin: 10px 0 6px 0;
            font-weight: 600;
            background: #f1f5f9;
            padding: 6px 12px;
            border-radius: 12px;
            display: inline-block;
        }

        .action-popup-buttons {
            display: flex;
            gap: 12px;
            margin-top: 16px;
        }

        .popup-btn {
            flex: 1;
            padding: 10px 8px;
            border-radius: 40px;
            border: none;
            font-weight: 600;
            font-size: 0.75rem;
            cursor: pointer;
            transition: 0.2s;
            font-family: inherit;
        }

        .popup-btn.chat {
            background: #1a6d5e;
            color: white;
        }

        .popup-btn.chat:hover {
            background: #0f5447;
            transform: translateY(-1px);
        }

        .popup-btn.directions {
            background: #2c7da0;
            color: white;
        }

        .popup-btn.directions:hover {
            background: #1f5e7a;
            transform: translateY(-1px);
        }

        /* Custom close button sa loob ng popup - dito mo pwedeng baguhin ang top at right */
        .popup-close-btn {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 30px;
            height: 30px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 15px;
            font-weight: bold;
            color: #475569;
            transition: all 0.2s;
            z-index: 25;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
            border: 1px solid #e2e8f0;
        }

        .popup-close-btn:hover {
            color: #ef4444;
            background: #fef2f2;
            transform: scale(1.1);
            border-color: #fecaca;
        }

        /* Floating Chat Widget */
        .chat-float {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 2000;
        }

        .chat-toggle {
            background: linear-gradient(135deg, #1a6d5e, #0f5447);
            width: 56px;
            height: 56px;
            border-radius: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            transition: 0.2s;
            font-size: 22px;
            font-weight: 600;
            color: white;
        }

        .chat-toggle:hover {
            transform: scale(1.05);
        }

        .chat-modal {
            position: fixed;
            bottom: 90px;
            right: 24px;
            width: 350px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            display: none;
            flex-direction: column;
            overflow: hidden;
            z-index: 2001;
            animation: slideUp 0.2s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .chat-header {
            background: #0a3b4e;
            color: white;
            padding: 14px 18px;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
            cursor: pointer;
        }

        .chat-messages {
            height: 320px;
            overflow-y: auto;
            padding: 12px;
            background: #f8fafc;
        }

        .message {
            margin-bottom: 12px;
            display: flex;
            flex-direction: column;
        }

        .message.sent {
            align-items: flex-end;
        }

        .message.received {
            align-items: flex-start;
        }

        .bubble {
            max-width: 85%;
            padding: 8px 14px;
            border-radius: 18px;
            font-size: 0.85rem;
        }

        .message.sent .bubble {
            background: #1a6d5e;
            color: white;
        }

        .message.received .bubble {
            background: white;
            border: 1px solid #e2e8f0;
            color: #1e293b;
        }

        .chat-input-area {
            display: flex;
            padding: 12px;
            border-top: 1px solid #e2e8f0;
            gap: 8px;
            background: white;
        }

        .chat-input-area input {
            flex: 1;
            padding: 10px 14px;
            border-radius: 25px;
            border: 1px solid #cbd5e1;
            outline: none;
            font-family: inherit;
        }

        .chat-input-area button {
            background: #1a6d5e;
            border: none;
            color: white;
            padding: 0 18px;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
        }

        @media (max-width: 640px) {
            .search-card {
                width: 95%;
                padding: 4px 4px 4px 16px;
            }
            .search-card button {
                padding: 8px 18px;
                font-size: 0.8rem;
            }
            .autocomplete-items {
                left: 16px;
                right: 80px;
            }
            .chat-modal {
                width: calc(100% - 40px);
                right: 20px;
            }
            .stats-row {
                gap: 10px;
            }
            .stat-badge {
                font-size: 0.7rem;
                padding: 6px 12px;
                min-width: auto;
                white-space: nowrap;
            }
            .stat-badge strong {
                font-size: 1rem;
            }
            .header h1 {
                font-size: 1.3rem;
            }
            .custom-popup .leaflet-popup-content-wrapper {
                min-width: 280px;
                max-width: 320px;
            }
            .custom-popup .leaflet-popup-content {
                min-width: 280px;
            }
            .pharmacy-popup {
                min-width: 260px;
                padding: 14px;
            }
            .med-item {
                flex-direction: column;
                align-items: flex-start;
            }
            .med-price-stock {
                text-align: left;
                margin-top: 2px;
            }
        }
    </style>
</head>
<body>

<div class="header">
    <h1>MedFind</h1>
    <div class="stats-row">
        <div class="stat-badge">
            <strong id="pharmacyCount">0</strong> Partner Pharmacies
        </div>
        <div class="stat-badge">
            <strong id="medicineStockCount">0</strong> Medicines in Stock
        </div>
        <div class="stat-badge" id="searchResultBadge">
            Ready to search
        </div>
    </div>
</div>

<div class="map-container">
    <div class="search-panel">
        <div class="search-card">
            <input type="text" id="medicineSearch" placeholder="Search medicine... e.g., Paracetamol, Amoxicillin, Ibuprofen" autocomplete="off">
            <button id="searchBtn">Search</button>
            <div id="autocompleteList" class="autocomplete-items" style="display: none;"></div>
        </div>
    </div>
    <div id="medfindMap"></div>
</div>

<!-- Floating Chat -->
<div class="chat-float">
    <div class="chat-toggle" onclick="toggleChat()">
        💬
    </div>
    <div id="chatModal" class="chat-modal">
        <div class="chat-header" onclick="toggleChat()">
            <span>MedFind Support</span>
            <span>—</span>
        </div>
        <div id="chatMessages" class="chat-messages">
            <div class="message received">
                <div class="bubble">Hello! Ask about medicine availability or chat with pharmacy staff. You can also send prescription inquiries here.</div>
            </div>
        </div>
        <div class="chat-input-area">
            <input type="text" id="chatInput" placeholder="Type your message...">
            <button onclick="sendChatMessage()">Send</button>
        </div>
    </div>
</div>

<script>
    // ============================================
    // MEDFIND - PHARMACY DATA (Legazpi City)
    // ============================================
    
    const pharmaciesData = [
        { id: 1, name: "Mercury Drug - Legazpi", address: "Rizal St., Legazpi City", lat: 13.1486, lng: 123.7412,
          medicines: [
              { name: "Paracetamol 500mg", price: 85, stock: 45, prescription: false },
              { name: "Amoxicillin 500mg", price: 125, stock: 23, prescription: true },
              { name: "Ibuprofen 200mg", price: 95, stock: 12, prescription: false },
              { name: "Mefenamic Acid 500mg", price: 110, stock: 8, prescription: false }
          ] },
        { id: 2, name: "Watsons - Pacific Mall", address: "Pacific Mall, Legazpi City", lat: 13.1505, lng: 123.7480,
          medicines: [
              { name: "Paracetamol 500mg", price: 92, stock: 28, prescription: false },
              { name: "Amoxicillin 500mg", price: 135, stock: 8, prescription: true },
              { name: "Cetirizine 10mg", price: 60, stock: 15, prescription: false },
              { name: "Loperamide 2mg", price: 48, stock: 20, prescription: false }
          ] },
        { id: 3, name: "South Star Drug", address: "Lapu-Lapu St., Legazpi City", lat: 13.1550, lng: 123.7395,
          medicines: [
              { name: "Paracetamol 500mg", price: 78, stock: 3, prescription: false },
              { name: "Ibuprofen 200mg", price: 88, stock: 15, prescription: false },
              { name: "Amoxicillin 500mg", price: 118, stock: 12, prescription: true },
              { name: "Losartan 50mg", price: 195, stock: 6, prescription: true }
          ] },
        { id: 4, name: "Generics Pharmacy", address: "Peñaranda St., Legazpi City", lat: 13.1420, lng: 123.7300,
          medicines: [
              { name: "Paracetamol 500mg", price: 65, stock: 0, prescription: false },
              { name: "Amoxicillin 500mg", price: 90, stock: 5, prescription: true },
              { name: "Mefenamic Acid 500mg", price: 85, stock: 7, prescription: false }
          ] },
        { id: 5, name: "SM City Legazpi Pharmacy", address: "SM City Legazpi, Airport Rd", lat: 13.1563, lng: 123.7318,
          medicines: [
              { name: "Paracetamol 500mg", price: 88, stock: 110, prescription: false },
              { name: "Amoxicillin 500mg", price: 132, stock: 22, prescription: true },
              { name: "Losartan 50mg", price: 210, stock: 8, prescription: true },
              { name: "Cetirizine 10mg", price: 58, stock: 35, prescription: false }
          ] },
        { id: 6, name: "ACE Medical Pharmacy", address: "ACE Medical Center, Legazpi", lat: 13.1402, lng: 123.7350,
          medicines: [
              { name: "Paracetamol 500mg", price: 95, stock: 33, prescription: false },
              { name: "Amoxicillin 500mg", price: 140, stock: 0, prescription: true },
              { name: "Ibuprofen 200mg", price: 100, stock: 5, prescription: false }
          ] },
        { id: 7, name: "Tamaoyan Drugstore", address: "Tamaoyan St, Legazpi City", lat: 13.1462, lng: 123.7375,
          medicines: [
              { name: "Loperamide 2mg", price: 45, stock: 12, prescription: false },
              { name: "Amoxicillin 500mg", price: 115, stock: 5, prescription: true },
              { name: "Hyoscine 10mg", price: 70, stock: 4, prescription: false }
          ] },
        { id: 8, name: "Kilicao Botika", address: "Kilicao, Legazpi City", lat: 13.1535, lng: 123.7442,
          medicines: [
              { name: "Paracetamol 500mg", price: 72, stock: 25, prescription: false },
              { name: "Cetirizine 10mg", price: 55, stock: 9, prescription: false }
          ] }
    ];
    
    // Extract all unique medicine names for autocomplete
    const allMedicineNames = [];
    pharmaciesData.forEach(pharmacy => {
        pharmacy.medicines.forEach(medicine => {
            if (!allMedicineNames.includes(medicine.name)) {
                allMedicineNames.push(medicine.name);
            }
        });
    });
    allMedicineNames.sort();
    
    // Global variables
    let map;
    let markers = [];
    let userLat = 13.1486;
    let userLng = 123.7412;
    let currentSearchQuery = "";
    let currentFilteredPharmacies = [];
    let activeChatPharmacy = null;
    let chatVisible = false;
    let activePopup = null;
    
    // Distance calculation
    function calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                  Math.cos(lat1 * Math.PI/180) * Math.cos(lat2 * Math.PI/180) *
                  Math.sin(dLon/2) * Math.sin(dLon/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return R * c;
    }
    
    // Close popup function
    function closePopup() {
        if (map && activePopup) {
            map.closePopup();
            activePopup = null;
        }
    }
    
    // Get user location
    function getUserLocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    userLat = position.coords.latitude;
                    userLng = position.coords.longitude;
                    if (map) map.setView([userLat, userLng], 14);
                    performSearch();
                },
                () => performSearch()
            );
        } else {
            performSearch();
        }
    }
    
    // Autocomplete
    function showAutocompleteSuggestions() {
        const input = document.getElementById("medicineSearch");
        const query = input.value.trim().toLowerCase();
        const autocompleteDiv = document.getElementById("autocompleteList");
        
        if (query === "") {
            autocompleteDiv.style.display = "none";
            return;
        }
        
        const matches = allMedicineNames.filter(name => 
            name.toLowerCase().includes(query)
        ).slice(0, 8);
        
        if (matches.length === 0) {
            autocompleteDiv.style.display = "none";
            return;
        }
        
        let html = "";
        matches.forEach(match => {
            const highlighted = match.replace(new RegExp(`(${query})`, 'gi'), '<strong>$1</strong>');
            html += `<div class="autocomplete-item" onclick="selectMedicine('${match.replace(/'/g, "\\'")}')">${highlighted}</div>`;
        });
        
        autocompleteDiv.innerHTML = html;
        autocompleteDiv.style.display = "block";
    }
    
    function selectMedicine(medicineName) {
        document.getElementById("medicineSearch").value = medicineName;
        document.getElementById("autocompleteList").style.display = "none";
        performSearch();
    }
    
    document.addEventListener("click", function(e) {
        const searchCard = document.querySelector(".search-card");
        if (searchCard && !searchCard.contains(e.target)) {
            document.getElementById("autocompleteList").style.display = "none";
        }
    });
    
    // Perform search & filter
    function performSearch() {
        const query = document.getElementById("medicineSearch").value.trim();
        currentSearchQuery = query.toLowerCase();
        
        document.getElementById("autocompleteList").style.display = "none";
        
        if (currentSearchQuery === "") {
            currentFilteredPharmacies = pharmaciesData;
            document.getElementById("searchResultBadge").innerHTML = "All pharmacies";
        } else {
            currentFilteredPharmacies = pharmaciesData.filter(pharmacy => {
                return pharmacy.medicines.some(med => 
                    med.name.toLowerCase().includes(currentSearchQuery) && med.stock > 0
                );
            });
            if (currentFilteredPharmacies.length === 0) {
                document.getElementById("searchResultBadge").innerHTML = "No stock found";
            } else {
                document.getElementById("searchResultBadge").innerHTML = `${currentFilteredPharmacies.length} pharmacy(s) have "${currentSearchQuery}"`;
            }
        }
        
        let totalStock = 0;
        currentFilteredPharmacies.forEach(ph => {
            const relevantMeds = currentSearchQuery === "" ? 
                ph.medicines.filter(m => m.stock > 0) :
                ph.medicines.filter(m => m.name.toLowerCase().includes(currentSearchQuery) && m.stock > 0);
            totalStock += relevantMeds.reduce((sum, m) => sum + m.stock, 0);
        });
        
        document.getElementById("pharmacyCount").innerText = currentFilteredPharmacies.length;
        document.getElementById("medicineStockCount").innerText = totalStock;
        
        updateMapMarkers();
    }
    
    // Update map markers with custom close button inside popup
    function updateMapMarkers() {
        markers.forEach(marker => map.removeLayer(marker));
        markers = [];
        
        currentFilteredPharmacies.forEach(pharmacy => {
            const distance = calculateDistance(userLat, userLng, pharmacy.lat, pharmacy.lng);
            const distanceText = distance.toFixed(1);
            
            let displayMeds;
            if (currentSearchQuery === "") {
                displayMeds = pharmacy.medicines.filter(m => m.stock > 0);
            } else {
                displayMeds = pharmacy.medicines.filter(m => 
                    m.name.toLowerCase().includes(currentSearchQuery) && m.stock > 0
                );
            }
            
            // Build medicine list HTML
            let medsHtml = `<div class="stock-info"><strong>Available Stock:</strong>`;
            if (displayMeds.length === 0) {
                medsHtml += `<span style="color:gray;">No matching medicine in stock</span>`;
            } else {
                displayMeds.forEach(med => {
                    const stockClass = med.stock < 5 ? (med.stock === 0 ? 'out' : 'low') : '';
                    const prescriptionBadge = med.prescription ? '<span class="rx-badge">Prescription Required</span>' : '';
                    medsHtml += `
                        <div class="med-item">
                            <div class="med-name-wrapper">
                                <span class="med-name">${med.name}</span>
                                ${prescriptionBadge}
                            </div>
                            <span class="med-price-stock">
                                <span class="med-price">₱${med.price}</span>
                                <span class="med-stock ${stockClass}">${med.stock} pcs</span>
                            </span>
                        </div>
                    `;
                });
            }
            medsHtml += `</div>`;
            
            // Custom close button sa loob ng popup
            const popupContent = `
                <div class="pharmacy-popup">
                    <div class="popup-close-btn" onclick="closePopup()">✕</div>
                    <h4>${pharmacy.name}</h4>
                    <div class="address">📍 ${pharmacy.address}</div>
                    <div class="distance-text">Distance: ${distanceText} km from your location</div>
                    ${medsHtml}
                    <div class="action-popup-buttons">
                        <button class="popup-btn chat" data-pharmacy="${pharmacy.name.replace(/"/g, '&quot;')}">Chat & Prescription</button>
                        <button class="popup-btn directions" data-lat="${pharmacy.lat}" data-lng="${pharmacy.lng}">Directions</button>
                    </div>
                </div>
            `;
            
            const hasLowStock = displayMeds.some(m => m.stock < 5);
            const markerIcon = L.divIcon({
                html: `<div style="background: ${hasLowStock ? '#f59e0b' : '#10b981'}; width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3); font-size: 16px; font-weight: bold; color: white;">H</div>`,
                iconSize: [34, 34],
                className: 'custom-marker'
            });
            
            const marker = L.marker([pharmacy.lat, pharmacy.lng], { icon: markerIcon })
                .bindPopup(popupContent, { maxWidth: 400, minWidth: 340, className: 'custom-popup' })
                .addTo(map);
            
            // Track active popup and bind button events
            marker.on('popupopen', function() {
                activePopup = marker;
                const popupNode = document.querySelector('.custom-popup .pharmacy-popup');
                if (popupNode) {
                    const chatBtn = popupNode.querySelector('.popup-btn.chat');
                    const dirBtn = popupNode.querySelector('.popup-btn.directions');
                    if (chatBtn) {
                        const phName = chatBtn.getAttribute('data-pharmacy') || pharmacy.name;
                        chatBtn.onclick = (e) => {
                            e.stopPropagation();
                            openChat(phName);
                        };
                    }
                    if (dirBtn) {
                        const lat = parseFloat(dirBtn.getAttribute('data-lat'));
                        const lng = parseFloat(dirBtn.getAttribute('data-lng'));
                        dirBtn.onclick = (e) => {
                            e.stopPropagation();
                            getDirections(lat, lng);
                        };
                    }
                }
            });
            
            marker.on('popupclose', function() {
                activePopup = null;
            });
            
            markers.push(marker);
        });
        
        if (currentFilteredPharmacies.length === 0 && currentSearchQuery !== "") {
            const tempPopup = L.popup()
                .setLatLng([userLat, userLng])
                .setContent(`<div style="padding:20px;font-family:Inter;text-align:center;">No pharmacies have <strong>${currentSearchQuery}</strong> in stock.<br>Try a different medicine or check back later.</div>`)
                .openOn(map);
            setTimeout(() => map.closePopup(tempPopup), 4000);
        }
    }
    
    function getDirections(lat, lng) {
        window.open(`https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`, '_blank');
    }
    
    // Chat functions
    function toggleChat() {
        const modal = document.getElementById("chatModal");
        chatVisible = !chatVisible;
        modal.style.display = chatVisible ? "flex" : "none";
        if (chatVisible) {
            const msgDiv = document.getElementById("chatMessages");
            msgDiv.scrollTop = msgDiv.scrollHeight;
        }
    }
    
    function openChat(pharmacyName) {
        if (!chatVisible) toggleChat();
        activeChatPharmacy = pharmacyName;
        const chatMsgDiv = document.getElementById("chatMessages");
        chatMsgDiv.innerHTML = `
            <div class="message received">
                <div class="bubble">You are now chatting with <strong>${pharmacyName}</strong>. Share your prescription or ask about stock availability.</div>
            </div>
            <div class="message received">
                <div class="bubble">Our pharmacist will assist you shortly. You can send your prescription image or questions here.</div>
            </div>
        `;
    }
    
    function sendChatMessage() {
        const input = document.getElementById("chatInput");
        const message = input.value.trim();
        if (!message) return;
        
        const messagesDiv = document.getElementById("chatMessages");
        const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        
        messagesDiv.innerHTML += `
            <div class="message sent">
                <div class="bubble">${escapeHtml(message)}</div>
                <div style="font-size:0.6rem;color:#aaa;margin-top:2px;">${time}</div>
            </div>
        `;
        input.value = "";
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
        
        setTimeout(() => {
            const replyTime = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            messagesDiv.innerHTML += `
                <div class="message received">
                    <div class="bubble">Thank you for your message! We've received your inquiry. Our staff will verify stock and prescription requirements and get back to you shortly.</div>
                    <div style="font-size:0.6rem;color:#aaa;margin-top:2px;">${replyTime}</div>
                </div>
            `;
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }, 1500);
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Initialize map
    function initMap() {
        map = L.map('medfindMap').setView([13.1486, 123.7412], 14);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> &copy; CartoDB',
            subdomains: 'abcd',
            maxZoom: 19,
            minZoom: 12
        }).addTo(map);
        
        // Close popup when clicking on map
        map.on('click', function() {
            closePopup();
        });
        
        getUserLocation();
    }
    
    // Event listeners
    document.addEventListener("DOMContentLoaded", () => {
        initMap();
        const searchInput = document.getElementById("medicineSearch");
        const searchBtn = document.getElementById("searchBtn");
        searchBtn.addEventListener("click", performSearch);
        searchInput.addEventListener("keypress", (e) => {
            if (e.key === "Enter") performSearch();
        });
        searchInput.addEventListener("input", showAutocompleteSuggestions);
    });
</script>
</body>
</html>