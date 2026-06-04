<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MedFind - Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0f0f1a; }
        
        .admin-container { display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .admin-sidebar {
            width: 280px;
            background: linear-gradient(180deg, #0f0f1a 0%, #1a1a2e 100%);
            color: white;
            padding: 25px;
        }
        
        .admin-sidebar h2 { margin-bottom: 30px; font-size: 1.5em; }
        .admin-sidebar nav a {
            display: block;
            padding: 12px 15px;
            color: #aaa;
            text-decoration: none;
            border-radius: 10px;
            margin-bottom: 5px;
            transition: 0.3s;
        }
        .admin-sidebar nav a:hover, .admin-sidebar nav a.active {
            background: rgba(102,126,234,0.2);
            color: white;
        }
        
        /* Main Content */
        .admin-main {
            flex: 1;
            padding: 25px;
            overflow-x: auto;
        }
        
        .admin-header {
            background: rgba(255,255,255,0.05);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-box {
            background: rgba(255,255,255,0.05);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-box h3 { font-size: 2em; color: #667eea; }
        .stat-box p { color: #aaa; margin-top: 5px; }
        
        .admin-card {
            background: rgba(255,255,255,0.05);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .admin-card h3 { margin-bottom: 15px; color: white; }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            color: #ddd;
        }
        
        th { color: #667eea; }
        
        .btn-edit, .btn-delete, .btn-approve {
            padding: 5px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 0 3px;
        }
        .btn-edit { background: #667eea; color: white; }
        .btn-delete { background: #e53e3e; color: white; }
        .btn-approve { background: #48bb78; color: white; }
        
        .status-active { color: #48bb78; }
        .status-pending { color: #ed8936; }
        .status-banned { color: #e53e3e; }
        
        .log-entry {
            padding: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            font-family: monospace;
        }
        .log-time { color: #667eea; font-size: 0.8em; }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-sidebar">
            <h2>🏥 MedFind Admin</h2>
            <nav>
                <a href="#dashboard" class="active" onclick="showSection('dashboard')">📊 Dashboard</a>
                <a href="#users" onclick="showSection('users')">👥 Manage Users</a>
                <a href="#pharmacies" onclick="showSection('pharmacies')">🏪 Manage Pharmacies</a>
                <a href="#medicines" onclick="showSection('medicines')">💊 Medicines DB</a>
                <a href="#logs" onclick="showSection('logs')">📜 System Logs</a>
                <a href="/logout">🚪 Logout</a>
            </nav>
        </div>
        
        <div class="admin-main">
            <!-- Dashboard Section -->
            <div id="dashboardSection">
                <div class="admin-header">
                    <h1 style="color:white;">System Administrator Dashboard</h1>
                    <p style="color:#aaa;">Welcome back! Here's your platform overview</p>
                </div>
                
                <div class="stats-row">
                    <div class="stat-box"><h3 id="totalUsers">0</h3><p>Total Users</p></div>
                    <div class="stat-box"><h3 id="totalPharmacies">0</h3><p>Registered Pharmacies</p></div>
                    <div class="stat-box"><h3 id="totalMedicines">0</h3><p>Medicine Listings</p></div>
                    <div class="stat-box"><h3 id="searchesToday">0</h3><p>Searches Today</p></div>
                </div>
                
                <div class="admin-card">
                    <h3>📊 Recent Activity</h3>
                    <div id="recentActivity"></div>
                </div>
            </div>
            
            <!-- Users Section -->
            <div id="usersSection" style="display:none;">
                <div class="admin-card">
                    <h3>👥 User Management</h3>
                    <div style="overflow-x: auto;">
                        <table id="usersTable">
                            <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody id="usersBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Pharmacies Section -->
            <div id="pharmaciesSection" style="display:none;">
                <div class="admin-card">
                    <h3>🏪 Pharmacy Management</h3>
                    <button class="btn-approve" onclick="addPharmacy()" style="margin-bottom:15px;">+ Add Pharmacy</button>
                    <div style="overflow-x: auto;">
                        <table id="pharmaciesTable">
                            <thead><tr><th>ID</th><th>Pharmacy Name</th><th>Address</th><th>Contact</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody id="pharmaciesBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Logs Section -->
            <div id="logsSection" style="display:none;">
                <div class="admin-card">
                    <h3>📜 System Logs</h3>
                    <div id="logsContainer"></div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Demo data for admin panel
        let users = [
            { id: 1, name: "John Doe", email: "john@example.com", role: "consumer", status: "active" },
            { id: 2, name: "Mercury Drug", email: "mercury@medfind.com", role: "pharmacy_operator", status: "active" },
            { id: 3, name: "Jane Smith", email: "jane@example.com", role: "consumer", status: "active" },
        ];
        
        let pharmacies = [
            { id: 1, name: "Mercury Drug - Legazpi", address: "Rizal St.", contact: "052-123-4567", status: "approved" },
            { id: 2, name: "Watsons - Pacific Mall", address: "Pacific Mall", contact: "052-123-4568", status: "pending" },
        ];
        
        let systemLogs = [
            { time: "2026-05-14 09:23:45", action: "User John Doe searched for 'Paracetamol'", type: "search" },
            { time: "2026-05-14 08:15:22", action: "Pharmacy Mercury Drug updated inventory", type: "update" },
            { time: "2026-05-14 07:45:10", action: "Admin logged in", type: "auth" },
        ];
        
        function updateStats() {
            document.getElementById('totalUsers').textContent = users.length;
            document.getElementById('totalPharmacies').textContent = pharmacies.length;
            document.getElementById('totalMedicines').textContent = '24';
            document.getElementById('searchesToday').textContent = '156';
        }
        
        function renderUsers() {
            const tbody = document.getElementById('usersBody');
            tbody.innerHTML = users.map(user => `
                <tr>
                    <td>${user.id}</td>
                    <td>${user.name}</td>
                    <td>${user.email}</td>
                    <td>${user.role}</td>
                    <td class="status-${user.status}">${user.status}</td>
                    <td>
                        <button class="btn-edit" onclick="editUser(${user.id})">Edit</button>
                        <button class="btn-delete" onclick="deleteUser(${user.id})">Delete</button>
                    </td>
                </tr>
            `).join('');
        }
        
        function renderPharmacies() {
            const tbody = document.getElementById('pharmaciesBody');
            tbody.innerHTML = pharmacies.map(pharmacy => `
                <tr>
                    <td>${pharmacy.id}</td>
                    <td>${pharmacy.name}</td>
                    <td>${pharmacy.address}</td>
                    <td>${pharmacy.contact}</td>
                    <td class="status-${pharmacy.status}">${pharmacy.status}</td>
                    <td>
                        ${pharmacy.status === 'pending' ? `<button class="btn-approve" onclick="approvePharmacy(${pharmacy.id})">Approve</button>` : ''}
                        <button class="btn-edit" onclick="editPharmacy(${pharmacy.id})">Edit</button>
                        <button class="btn-delete" onclick="deletePharmacy(${pharmacy.id})">Delete</button>
                    </td>
                </tr>
            `).join('');
        }
        
        function renderLogs() {
            const container = document.getElementById('logsContainer');
            container.innerHTML = systemLogs.map(log => `
                <div class="log-entry">
                    <span class="log-time">[${log.time}]</span>
                    <span> ${log.action}</span>
                </div>
            `).join('');
        }
        
        function renderRecentActivity() {
            const container = document.getElementById('recentActivity');
            container.innerHTML = systemLogs.slice(0, 5).map(log => `
                <div class="log-entry">${log.action} - ${log.time}</div>
            `).join('');
        }
        
        function showSection(section) {
            document.getElementById('dashboardSection').style.display = section === 'dashboard' ? 'block' : 'none';
            document.getElementById('usersSection').style.display = section === 'users' ? 'block' : 'none';
            document.getElementById('pharmaciesSection').style.display = section === 'pharmacies' ? 'block' : 'none';
            document.getElementById('logsSection').style.display = section === 'logs' ? 'block' : 'none';
            
            document.querySelectorAll('.admin-sidebar nav a').forEach(a => a.classList.remove('active'));
            event.target.classList.add('active');
            
            if (section === 'users') renderUsers();
            if (section === 'pharmacies') renderPharmacies();
            if (section === 'logs') renderLogs();
        }
        
        function editUser(id) { alert(`Edit user ${id}`); }
        function deleteUser(id) { users = users.filter(u => u.id !== id); renderUsers(); updateStats(); }
        function approvePharmacy(id) { alert(`Pharmacy ${id} approved!`); }
        function editPharmacy(id) { alert(`Edit pharmacy ${id}`); }
        function deletePharmacy(id) { pharmacies = pharmacies.filter(p => p.id !== id); renderPharmacies(); updateStats(); }
        function addPharmacy() { alert("Add new pharmacy form"); }
        
        // Initialize
        updateStats();
        renderRecentActivity();
        
        // Auto-refresh every 30 seconds
        setInterval(() => { updateStats(); renderRecentActivity(); }, 30000);
    </script>
</body>
</html>