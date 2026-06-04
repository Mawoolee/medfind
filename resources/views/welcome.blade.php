<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MedFind - Real-Time Pharmaceutical Inventory Locator | Legazpi City</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ============================================
           MEDFIND - MAIN STYLESHEET
           ============================================ */
        
        /* Reset & Base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #f0f2f5;
            color: #1a1a2e;
            line-height: 1.5;
        }
        
        /* ============================================
           HEADER SECTION
           ============================================ */
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .header p {
            opacity: 0.95;
            font-size: 1.1em;
            margin-bottom: 20px;
        }
        
        /* Stats Bar */
        .stats {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 25px;
            flex-wrap: wrap;
        }
        
        .stat-card {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            padding: 15px 30px;
            border-radius: 16px;
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            background: rgba(255,255,255,0.25);
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: 800;
        }
        
        .stat-label {
            font-size: 0.85em;
            opacity: 0.9;
            margin-top: 5px;
        }
        
        /* ============================================
           CONTAINER & LAYOUT
           ============================================ */
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        /* ============================================
           SEARCH BOX SECTION
           ============================================ */
        
        .search-box {
            background: white;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .search-input-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .search-input-group input {
            flex: 1;
            padding: 16px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 14px;
            font-size: 16px;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .search-input-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        
        /* Buttons */
        .btn {
            padding: 16px 32px;
            border: none;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102,126,234,0.4);
        }
        
        .btn-secondary {
            background: #48bb78;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #38a169;
            transform: translateY(-2px);
        }
        
        /* Filter Buttons */
        .filter-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 10px 20px;
            background: #f0f2f5;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .filter-btn:hover {
            background: #e0e0e0;
        }
        
        .filter-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        /* ============================================
           RESULTS HEADER
           ============================================ */
        
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .results-header h2 {
            font-size: 1.5em;
            color: #1a1a2e;
        }
        
        .results-count {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .sort-select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 10px;
            background: white;
            font-family: inherit;
            cursor: pointer;
        }
        
        /* ============================================
           PHARMACY CARDS GRID
           ============================================ */
        
        .pharmacies-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 25px;
        }
        
        .pharmacy-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .pharmacy-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        
        .pharmacy-image {
            height: 120px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
        }
        
        .pharmacy-info {
            padding: 20px;
        }
        
        .pharmacy-name {
            font-size: 1.2em;
            font-weight: 700;
            margin-bottom: 8px;
            color: #1a1a2e;
        }
        
        .pharmacy-address {
            color: #666;
            font-size: 0.85em;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .distance-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #e0e7ff;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            color: #4c51bf;
            margin-bottom: 15px;
        }
        
        /* Medicine List */
        .medicine-list {
            margin-top: 15px;
            border-top: 1px solid #e0e0e0;
            padding-top: 15px;
        }
        
        .medicine-list > strong {
            display: block;
            margin-bottom: 10px;
            font-size: 0.9em;
            color: #555;
        }
        
        .medicine-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .medicine-item:last-child {
            border-bottom: none;
        }
        
        .medicine-name {
            font-weight: 600;
            font-size: 0.95em;
        }
        
        .medicine-details {
            text-align: right;
        }
        
        .medicine-price {
            font-weight: 700;
            color: #48bb78;
            font-size: 1em;
        }
        
        /* Stock Badges */
        .stock-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.7em;
            font-weight: 600;
            margin-left: 8px;
        }
        
        .stock-high {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .stock-medium {
            background: #feebc8;
            color: #7b341e;
        }
        
        .stock-low {
            background: #fed7d7;
            color: #742a2a;
        }
        
        .prescription-badge {
            display: inline-block;
            background: #e0e7ff;
            color: #4c51bf;
            font-size: 0.65em;
            padding: 2px 8px;
            border-radius: 12px;
            margin-left: 8px;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 18px;
        }
        
        .action-btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 0.85em;
            font-weight: 600;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .chat-btn {
            background: #667eea;
            color: white;
        }
        
        .chat-btn:hover {
            background: #5a67d8;
        }
        
        .directions-btn {
            background: #48bb78;
            color: white;
        }
        
        .directions-btn:hover {
            background: #38a169;
        }
        
        /* ============================================
           LOADING STATE
           ============================================ */
        
        .loading {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 20px;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #e0e0e0;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* ============================================
           NO RESULTS STATE
           ============================================ */
        
        .no-results {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 20px;
        }
        
        .no-results h3 {
            font-size: 1.5em;
            margin-bottom: 10px;
            color: #1a1a2e;
        }
        
        .no-results p {
            color: #666;
        }
        
        /* ============================================
           CHAT MODAL
           ============================================ */
        
        .chat-modal {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 380px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            display: none;
            z-index: 1000;
            animation: slideUp 0.3s ease;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 20px 20px 0 0;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
        }
        
        .chat-messages {
            height: 350px;
            overflow-y: auto;
            padding: 15px;
            background: #f7fafc;
        }
        
        /* Message Bubbles */
        .message {
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
        }
        
        .message.sent {
            align-items: flex-end;
        }
        
        .message.received {
            align-items: flex-start;
        }
        
        .message-bubble {
            max-width: 80%;
            padding: 10px 15px;
            border-radius: 18px;
            word-wrap: break-word;
        }
        
        .message.sent .message-bubble {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom-right-radius: 4px;
        }
        
        .message.received .message-bubble {
            background: white;
            color: #1a1a2e;
            border-bottom-left-radius: 4px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .message-time {
            font-size: 0.65em;
            margin-top: 4px;
            color: #999;
        }
        
        .chat-input-area {
            display: flex;
            padding: 15px;
            border-top: 1px solid #e0e0e0;
            background: white;
            border-radius: 0 0 20px 20px;
            gap: 10px;
        }
        
        .chat-input-area input {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 25px;
            font-family: inherit;
        }
        
        .chat-input-area input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .chat-input-area .btn {
            padding: 12px 20px;
        }
        
        /* ============================================
           RESPONSIVE DESIGN
           ============================================ */
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.5em;
            }
            
            .header p {
                font-size: 0.9em;
            }
            
            .stat-card {
                padding: 10px 20px;
            }
            
            .stat-number {
                font-size: 1.5em;
            }
            
            .pharmacies-grid {
                grid-template-columns: 1fr;
            }
            
            .chat-modal {
                width: 100%;
                right: 0;
                bottom: 0;
                border-radius: 20px 20px 0 0;
            }
            
            .search-input-group {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
            
            .filter-buttons {
                justify-content: center;
            }
            
            .results-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .sort-select {
                width: 100%;
            }
        }
        
        @media (max-width: 480px) {
            .stats {
                gap: 10px;
            }
            
            .stat-card {
                padding: 8px 15px;
            }
            
            .stat-number {
                font-size: 1.2em;
            }
            
            .stat-label {
                font-size: 0.7em;
            }
            
            .pharmacy-info {
                padding: 15px;
            }
            
            .medicine-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            
            .medicine-details {
                text-align: left;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
        
        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>MedFind</h1>
        <p>Real-Time Pharmaceutical Inventory Locator System | Legazpi City</p>
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number" id="pharmacyCount">0</div>
                <div class="stat-label">Partner Pharmacies</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="medicineCount">0</div>
                <div class="stat-label">Medicines Available</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="searchCount">0</div>
                <div class="stat-label">Searches Today</div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="search-box">
            <div class="search-input-group">
                <input type="text" id="searchInput" placeholder="Search for medicine... (e.g., Paracetamol, Amoxicillin, Ibuprofen)">
                <button class="btn btn-primary" onclick="searchMedicine()">Search</button>
                <button class="btn btn-secondary" onclick="getUserLocation()">Near Me</button>
            </div>
            <div class="filter-buttons">
                <button class="filter-btn active" onclick="filterBy('all')">All Pharmacies</button>
                <button class="filter-btn" onclick="filterBy('in-stock')">In Stock Only</button>
                <button class="filter-btn" onclick="filterBy('prescription')">Prescription Meds</button>
            </div>
        </div>
        
        <div class="results-header">
            <div>
                <h2>Available Pharmacies</h2>
                <div class="results-count" id="resultsCount">Showing 0 results</div>
            </div>
            <select class="sort-select" id="sortSelect" onchange="sortResults()">
                <option value="distance">Sort by: Distance (Closest)</option>
                <option value="price">Sort by: Price (Lowest)</option>
                <option value="name">Sort by: Name (A-Z)</option>
            </select>
        </div>
        
        <div id="resultsContainer">
            <div class="loading">
                <div class="spinner"></div>
                <p>Loading pharmacies...</p>
            </div>
        </div>
    </div>
    
    <!-- Chat Modal -->
    <div id="chatModal" class="chat-modal">
        <div class="chat-header" onclick="toggleChat()">
            <span>Chat with Pharmacy</span>
            <span>_</span>
        </div>
        <div id="chatMessages" class="chat-messages">
            <div class="message received">
                <div class="message-bubble">Hello! How can we help you today?</div>
                <div class="message-time">Just now</div>
            </div>
        </div>
        <div class="chat-input-area">
            <input type="text" id="chatInput" placeholder="Type your message...">
            <button class="btn btn-primary" onclick="sendChatMessage()">Send</button>
        </div>
    </div>
    
    <script>
        // ============================================
        // MEDFIND - JAVASCRIPT APPLICATION
        // ============================================
        
        let pharmaciesData = [];
        let currentFilter = 'all';
        let currentSort = 'distance';
        let userLocation = null;
        let currentPharmacy = null;
        let chatOpen = false;
        
        // Load pharmacies on page load
        document.addEventListener('DOMContentLoaded', function() {
            searchMedicine();
            getUserLocation();
            
            // Auto-search as user types
            const searchInput = document.getElementById('searchInput');
            let typingTimer;
            searchInput.addEventListener('input', function() {
                clearTimeout(typingTimer);
                typingTimer = setTimeout(searchMedicine, 500);
            });
        });
        
        // Get user location
        function getUserLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    position => {
                        userLocation = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude
                        };
                        console.log('Location detected:', userLocation);
                        searchMedicine();
                    },
                    error => {
                        console.log('Geolocation error:', error);
                        userLocation = { lat: 13.1486, lng: 123.7412 };
                        searchMedicine();
                    }
                );
            } else {
                userLocation = { lat: 13.1486, lng: 123.7412 };
                searchMedicine();
            }
        }
        
        // Search for medicine
        async function searchMedicine() {
            const query = document.getElementById('searchInput').value;
            
            document.getElementById('resultsContainer').innerHTML = `
                <div class="loading">
                    <div class="spinner"></div>
                    <p>Searching for "${query || 'medicines'}"...</p>
                </div>
            `;
            
            try {
                const response = await fetch(`/api/search?query=${encodeURIComponent(query)}`);
                const result = await response.json();
                
                if (result.success && result.data) {
                    pharmaciesData = result.data;
                    updateStats();
                    filterAndDisplayResults();
                } else {
                    loadDemoData(query);
                }
            } catch (error) {
                console.error('Search error:', error);
                loadDemoData(query);
            }
        }
        
        // Load demo data (fallback)
        function loadDemoData(query) {
            const demoData = [
                {
                    id: 1,
                    name: "Mercury Drug - Legazpi",
                    address: "Rizal St., Legazpi City",
                    latitude: 13.1486,
                    longitude: 123.7412,
                    medicines: [
                        { name: "Paracetamol 500mg", price: 85, stock: 45, requires_prescription: false },
                        { name: "Amoxicillin 500mg", price: 120, stock: 23, requires_prescription: true },
                        { name: "Ibuprofen 200mg", price: 95, stock: 12, requires_prescription: false }
                    ]
                },
                {
                    id: 2,
                    name: "Watsons - Pacific Mall",
                    address: "Pacific Mall, Legazpi City",
                    latitude: 13.1500,
                    longitude: 123.7480,
                    medicines: [
                        { name: "Paracetamol 500mg", price: 92, stock: 28, requires_prescription: false },
                        { name: "Amoxicillin 500mg", price: 135, stock: 8, requires_prescription: true }
                    ]
                },
                {
                    id: 3,
                    name: "South Star Drug",
                    address: "Lapu-Lapu St., Legazpi City",
                    latitude: 13.1550,
                    longitude: 123.7350,
                    medicines: [
                        { name: "Paracetamol 500mg", price: 78, stock: 3, requires_prescription: false },
                        { name: "Ibuprofen 200mg", price: 88, stock: 15, requires_prescription: false }
                    ]
                },
                {
                    id: 4,
                    name: "Generics Pharmacy",
                    address: "Peñaranda St., Legazpi City",
                    latitude: 13.1420,
                    longitude: 123.7300,
                    medicines: [
                        { name: "Paracetamol 500mg", price: 65, stock: 0, requires_prescription: false },
                        { name: "Amoxicillin 500mg", price: 90, stock: 5, requires_prescription: true }
                    ]
                }
            ];
            
            if (query) {
                const filtered = [];
                for (const pharmacy of demoData) {
                    const matchingMeds = pharmacy.medicines.filter(m => 
                        m.name.toLowerCase().includes(query.toLowerCase())
                    );
                    if (matchingMeds.length > 0) {
                        filtered.push({
                            ...pharmacy,
                            medicines: matchingMeds
                        });
                    }
                }
                pharmaciesData = filtered;
            } else {
                pharmaciesData = demoData;
            }
            
            updateStats();
            filterAndDisplayResults();
        }
        
        // Update statistics
        function updateStats() {
            let totalMedicines = 0;
            pharmaciesData.forEach(pharmacy => {
                totalMedicines += pharmacy.medicines.length;
            });
            document.getElementById('pharmacyCount').textContent = pharmaciesData.length;
            document.getElementById('medicineCount').textContent = totalMedicines;
            document.getElementById('searchCount').textContent = Math.floor(Math.random() * 100) + 50;
        }
        
        // Filter results
        function filterBy(filter) {
            currentFilter = filter;
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
                if (btn.textContent.toLowerCase().includes(filter) || 
                    (filter === 'all' && btn.textContent.includes('All'))) {
                    btn.classList.add('active');
                }
            });
            filterAndDisplayResults();
        }
        
        // Sort results
        function sortResults() {
            currentSort = document.getElementById('sortSelect').value;
            filterAndDisplayResults();
        }
        
        // Calculate distance between coordinates
        function calculateDistance(lat1, lon1, lat2, lon2) {
            if (!lat1 || !lon1 || !lat2 || !lon2) return null;
            const R = 6371;
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                      Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                      Math.sin(dLon/2) * Math.sin(dLon/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            return R * c;
        }
        
        // Filter and display results
        function filterAndDisplayResults() {
            let filtered = [...pharmaciesData];
            
            if (currentFilter === 'in-stock') {
                filtered = filtered.map(pharmacy => ({
                    ...pharmacy,
                    medicines: pharmacy.medicines.filter(m => m.stock > 0)
                })).filter(p => p.medicines.length > 0);
            } else if (currentFilter === 'prescription') {
                filtered = filtered.map(pharmacy => ({
                    ...pharmacy,
                    medicines: pharmacy.medicines.filter(m => m.requires_prescription)
                })).filter(p => p.medicines.length > 0);
            }
            
            filtered.forEach(pharmacy => {
                if (userLocation) {
                    pharmacy.distance = calculateDistance(
                        userLocation.lat, userLocation.lng,
                        pharmacy.latitude, pharmacy.longitude
                    );
                } else {
                    pharmacy.distance = null;
                }
            });
            
            if (currentSort === 'distance') {
                filtered.sort((a, b) => (a.distance || 999) - (b.distance || 999));
            } else if (currentSort === 'price') {
                filtered.sort((a, b) => {
                    const minPriceA = Math.min(...a.medicines.map(m => m.price));
                    const minPriceB = Math.min(...b.medicines.map(m => m.price));
                    return minPriceA - minPriceB;
                });
            } else if (currentSort === 'name') {
                filtered.sort((a, b) => a.name.localeCompare(b.name));
            }
            
            displayResults(filtered);
        }
        
        // Display results
        function displayResults(pharmacies) {
            const container = document.getElementById('resultsContainer');
            document.getElementById('resultsCount').textContent = `Showing ${pharmacies.length} pharmacy${pharmacies.length !== 1 ? 's' : ''}`;
            
            if (pharmacies.length === 0) {
                container.innerHTML = `
                    <div class="no-results">
                        <h3>No pharmacies found</h3>
                        <p>Try searching for a different medicine or check back later</p>
                    </div>
                `;
                return;
            }
            
            let html = '<div class="pharmacies-grid">';
            
            for (const pharmacy of pharmacies) {
                const distanceText = pharmacy.distance ? `${pharmacy.distance.toFixed(1)} km away` : 'Distance unavailable';
                
                html += `
                    <div class="pharmacy-card" onclick="openChatWithPharmacy('${pharmacy.name}')">
                        <div class="pharmacy-image"></div>
                        <div class="pharmacy-info">
                            <div class="pharmacy-name">${escapeHtml(pharmacy.name)}</div>
                            <div class="pharmacy-address">${escapeHtml(pharmacy.address)}</div>
                            <div class="distance-badge">${distanceText}</div>
                            <div class="medicine-list">
                                <strong>Available Medicines:</strong>
                `;
                
                for (const medicine of pharmacy.medicines) {
                    let stockClass = medicine.stock > 20 ? 'stock-high' : (medicine.stock > 5 ? 'stock-medium' : 'stock-low');
                    let stockText = medicine.stock > 0 ? `${medicine.stock} in stock` : 'Out of stock';
                    
                    html += `
                        <div class="medicine-item">
                            <div>
                                <span class="medicine-name">${escapeHtml(medicine.name)}</span>
                                ${medicine.requires_prescription ? '<span class="prescription-badge">Rx Required</span>' : ''}
                            </div>
                            <div class="medicine-details">
                                <span class="medicine-price">₱${medicine.price}</span>
                                <span class="stock-badge ${stockClass}">${stockText}</span>
                            </div>
                        </div>
                    `;
                }
                
                html += `
                            </div>
                            <div class="action-buttons">
                                <button class="action-btn chat-btn" onclick="event.stopPropagation(); openChatWithPharmacy('${escapeHtml(pharmacy.name)}')">Chat</button>
                                <button class="action-btn directions-btn" onclick="event.stopPropagation(); getDirections(${pharmacy.latitude}, ${pharmacy.longitude})">Directions</button>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            html += '</div>';
            container.innerHTML = html;
        }
        
        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Get directions
        function getDirections(lat, lng) {
            window.open(`https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`, '_blank');
        }
        
        // Toggle chat modal
        function toggleChat() {
            chatOpen = !chatOpen;
            document.getElementById('chatModal').style.display = chatOpen ? 'block' : 'none';
        }
        
        // Open chat with pharmacy
        function openChatWithPharmacy(pharmacyName) {
            currentPharmacy = pharmacyName;
            const chatMessages = document.getElementById('chatMessages');
            chatMessages.innerHTML = `
                <div class="message received">
                    <div class="message-bubble">Hello! Welcome to ${escapeHtml(pharmacyName)}. How can we help you today?</div>
                    <div class="message-time">Just now</div>
                </div>
            `;
            if (!chatOpen) toggleChat();
        }
        
        // Send chat message
        function sendChatMessage() {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();
            if (!message) return;
            
            const chatMessages = document.getElementById('chatMessages');
            const now = new Date();
            const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            
            chatMessages.innerHTML += `
                <div class="message sent">
                    <div class="message-bubble">${escapeHtml(message)}</div>
                    <div class="message-time">${timeStr}</div>
                </div>
            `;
            input.value = '';
            chatMessages.scrollTop = chatMessages.scrollHeight;
            
            setTimeout(() => {
                const responseTime = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                chatMessages.innerHTML += `
                    <div class="message received">
                        <div class="message-bubble">Thank you for your message. We'll check our inventory and get back to you shortly.</div>
                        <div class="message-time">${responseTime}</div>
                    </div>
                `;
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }, 1500);
        }
    </script>
</body>
</html>