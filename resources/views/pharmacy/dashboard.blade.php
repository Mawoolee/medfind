<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MedFind - Pharmacy Operator Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 260px;
            height: 100%;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            padding: 20px 0;
        }
        
        .sidebar-logo {
            text-align: center;
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        
        .sidebar-logo h2 { font-size: 1.5em; }
        .sidebar-logo p { font-size: 0.8em; opacity: 0.7; }
        
        .sidebar-menu { list-style: none; }
        .sidebar-menu li { padding: 12px 25px; cursor: pointer; transition: 0.3s; }
        .sidebar-menu li:hover, .sidebar-menu li.active { background: rgba(102,126,234,0.3); border-left: 4px solid #667eea; }
        .sidebar-menu li a { color: white; text-decoration: none; display: block; }
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 15px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .stat-card h3 { font-size: 2em; color: #667eea; }
        .stat-card p { color: #666; margin-top: 5px; }
        
        /* Inventory Table */
        .inventory-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .inventory-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .search-inventory {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 10px;
            width: 250px;
        }
        
        .add-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        th { background: #f8f9fa; font-weight: 600; }
        
        .stock-input {
            width: 80px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 8px;
            text-align: center;
        }
        
        .update-btn {
            background: #48bb78;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75em;
            font-weight: 600;
        }
        
        .status-in-stock { background: #c6f6d5; color: #22543d; }
        .status-low-stock { background: #feebc8; color: #7b341e; }
        .status-out-of-stock { background: #fed7d7; color: #742a2a; }
        
        /* Chat Section */
        .chat-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
        }
        
        .chat-messages {
            height: 300px;
            overflow-y: auto;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .chat-message {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 10px;
        }
        
        .chat-message.customer { background: #e0e7ff; }
        .chat-message.pharmacy { background: #c6f6d5; text-align: right; }
        
        .chat-input-area {
            display: flex;
            gap: 10px;
        }
        
        .chat-input-area input {
            flex: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 10px;
        }
        
        .prescription-img {
            max-width: 150px;
            margin-top: 5px;
            border-radius: 8px;
        }
        
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-logo">
            <h2>🏥 MedFind</h2>
            <p>Pharmacy Portal</p>
        </div>
        <ul class="sidebar-menu">
            <li class="active"><a href="#dashboard">📊 Dashboard</a></li>
            <li><a href="#inventory">💊 Inventory</a></li>
            <li><a href="#messages">💬 Messages</a></li>
            <li><a href="#settings">⚙️ Settings</a></li>
            <li><a href="/logout">🚪 Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1>Pharmacy Operator Dashboard</h1>
            <div id="currentDateTime"></div>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card">
                <h3 id="totalMedicines">0</h3>
                <p>Total Medicines</p>
            </div>
            <div class="stat-card">
                <h3 id="inStockCount">0</h3>
                <p>In Stock</p>
            </div>
            <div class="stat-card">
                <h3 id="lowStockCount">0</h3>
                <p>Low Stock</p>
            </div>
            <div class="stat-card">
                <h3 id="pendingMessages">0</h3>
                <p>Unread Messages</p>
            </div>
        </div>
        
        <!-- Inventory Section -->
        <div id="inventory" class="inventory-section">
            <div class="inventory-header">
                <h2>💊 Medicine Inventory</h2>
                <div>
                    <input type="text" id="searchInventory" class="search-inventory" placeholder="Search medicine...">
                    <button class="add-btn" onclick="showAddMedicineModal()">+ Add Medicine</button>
                </div>
            </div>
            <div style="overflow-x: auto;">
                <table id="inventoryTable">
                    <thead>
                        <tr><th>Medicine Name</th><th>Dosage</th><th>Stock</th><th>Price</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody id="inventoryBody">
                        <tr><td colspan="6" style="text-align:center;">Loading inventory...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Messages Section -->
        <div id="messages" class="chat-section" style="display:none;">
            <h2>💬 Customer Messages</h2>
            <div id="conversationList" style="border-bottom:1px solid #ddd; margin-bottom:15px;">
                <select id="conversationSelect" style="width:100%; padding:10px;">
                    <option>Select a conversation...</option>
                </select>
            </div>
            <div id="chatMessages" class="chat-messages">
                <p style="color:#999; text-align:center;">Select a conversation to start chatting</p>
            </div>
            <div class="chat-input-area">
                <input type="text" id="messageInput" placeholder="Type your reply...">
                <button class="add-btn" onclick="sendReply()">Send Reply</button>
            </div>
        </div>
    </div>
    
    <script>
        let currentInventory = [];
        let currentConversation = null;
        
        // Load pharmacy data
        async function loadInventory() {
            try {
                const response = await fetch('/api/pharmacy/inventory');
                const data = await response.json();
                currentInventory = data;
                updateStats();
                renderInventory();
            } catch (error) {
                console.error('Error loading inventory:', error);
                loadDemoInventory();
            }
        }
        
        function loadDemoInventory() {
            currentInventory = [
                { id: 1, medicine_name: "Paracetamol", dosage: "500mg", stock: 45, price: 85, status: "in_stock" },
                { id: 2, medicine_name: "Amoxicillin", dosage: "500mg", stock: 23, price: 120, status: "in_stock" },
                { id: 3, medicine_name: "Ibuprofen", dosage: "200mg", stock: 8, price: 95, status: "low_stock" },
                { id: 4, medicine_name: "Loperamide", dosage: "2mg", stock: 0, price: 45, status: "out_of_stock" },
            ];
            updateStats();
            renderInventory();
        }
        
        function updateStats() {
            const total = currentInventory.length;
            const inStock = currentInventory.filter(m => m.stock > 10).length;
            const lowStock = currentInventory.filter(m => m.stock > 0 && m.stock <= 10).length;
            document.getElementById('totalMedicines').textContent = total;
            document.getElementById('inStockCount').textContent = inStock;
            document.getElementById('lowStockCount').textContent = lowStock;
        }
        
        function renderInventory() {
            const tbody = document.getElementById('inventoryBody');
            if (currentInventory.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No medicines found</td></tr>';
                return;
            }
            
            tbody.innerHTML = currentInventory.map(item => {
                let statusClass = item.stock > 10 ? 'status-in-stock' : (item.stock > 0 ? 'status-low-stock' : 'status-out-of-stock');
                let statusText = item.stock > 10 ? 'In Stock' : (item.stock > 0 ? 'Low Stock' : 'Out of Stock');
                
                return `
                    <tr>
                        <td>${item.medicine_name}</td>
                        <td>${item.dosage}</td>
                        <td>
                            <input type="number" id="stock_${item.id}" value="${item.stock}" class="stock-input" min="0">
                        </td>
                        <td>
                            <input type="number" id="price_${item.id}" value="${item.price}" class="stock-input" step="0.01">
                        </td>
                        <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                        <td>
                            <button class="update-btn" onclick="updateStock(${item.id})">Update</button>
                        </td>
                    </tr>
                `;
            }).join('');
        }
        
        async function updateStock(medicineId) {
            const stockInput = document.getElementById(`stock_${medicineId}`);
            const priceInput = document.getElementById(`price_${medicineId}`);
            const newStock = parseInt(stockInput.value);
            const newPrice = parseFloat(priceInput.value);
            
            try {
                const response = await fetch('/api/pharmacy/update-stock', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        medicine_id: medicineId,
                        stock_quantity: newStock,
                        price: newPrice
                    })
                });
                
                if (response.ok) {
                    alert('Stock updated successfully! Real-time update sent to customers.');
                    loadInventory();
                }
            } catch (error) {
                // Update locally for demo
                const item = currentInventory.find(m => m.id === medicineId);
                if (item) {
                    item.stock = newStock;
                    item.price = newPrice;
                    renderInventory();
                    updateStats();
                    alert('Stock updated! (Demo mode)');
                }
            }
        }
        
        function showAddMedicineModal() {
            const name = prompt("Enter medicine name:");
            if (!name) return;
            const dosage = prompt("Enter dosage (e.g., 500mg):");
            const price = parseFloat(prompt("Enter price:"));
            
            const newId = Math.max(...currentInventory.map(m => m.id), 0) + 1;
            currentInventory.push({
                id: newId,
                medicine_name: name,
                dosage: dosage,
                stock: 0,
                price: price,
                status: "out_of_stock"
            });
            renderInventory();
            updateStats();
            alert('Medicine added!');
        }
        
        // Navigation
        document.querySelectorAll('.sidebar-menu li').forEach(li => {
            li.addEventListener('click', () => {
                document.querySelectorAll('.sidebar-menu li').forEach(l => l.classList.remove('active'));
                li.classList.add('active');
                
                const section = li.querySelector('a').getAttribute('href').substring(1);
                document.getElementById('inventory').style.display = section === 'inventory' || section === 'dashboard' ? 'block' : 'none';
                document.getElementById('messages').style.display = section === 'messages' ? 'block' : 'none';
            });
        });
        
        // Search filter
        document.getElementById('searchInventory')?.addEventListener('input', (e) => {
            const search = e.target.value.toLowerCase();
            const filtered = currentInventory.filter(m => m.medicine_name.toLowerCase().includes(search));
            const tbody = document.getElementById('inventoryBody');
            if (filtered.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No matching medicines</td></tr>';
            } else {
                // Re-render filtered
                tbody.innerHTML = filtered.map(item => {
                    let statusClass = item.stock > 10 ? 'status-in-stock' : (item.stock > 0 ? 'status-low-stock' : 'status-out-of-stock');
                    return `<tr><td>${item.medicine_name}</td><td>${item.dosage}</td><td><input type="number" id="stock_${item.id}" value="${item.stock}" class="stock-input"></td><td><input type="number" id="price_${item.id}" value="${item.price}" class="stock-input"></td><td><span class="status-badge ${statusClass}">${item.stock > 10 ? 'In Stock' : (item.stock > 0 ? 'Low Stock' : 'Out of Stock')}</span></td><td><button class="update-btn" onclick="updateStock(${item.id})">Update</button></td></tr>`;
                }).join('');
            }
        });
        
        // DateTime
        function updateDateTime() {
            const now = new Date();
            document.getElementById('currentDateTime').innerHTML = now.toLocaleString();
        }
        updateDateTime();
        setInterval(updateDateTime, 1000);
        
        // Load data
        loadInventory();
        
        // Chat functions
        async function loadConversations() {
            // Demo conversations
            const select = document.getElementById('conversationSelect');
            select.innerHTML = '<option value="1">Customer: John - Paracetamol inquiry</option><option value="2">Customer: Maria - Prescription upload</option>';
        }
        
        function sendReply() {
            const input = document.getElementById('messageInput');
            if (!input.value.trim()) return;
            const chatDiv = document.getElementById('chatMessages');
            chatDiv.innerHTML += `<div class="chat-message pharmacy"><strong>You:</strong> ${input.value}</div>`;
            input.value = '';
            chatDiv.scrollTop = chatDiv.scrollHeight;
        }
        
        loadConversations();
    </script>
</body>
</html>