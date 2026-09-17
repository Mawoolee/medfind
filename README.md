# 🏥 MedFind - Real-Time Pharmacy Locator & Inventory Management System

[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat&logo=laravel)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat&logo=php)](https://www.php.net/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-17+-336791?style=flat&logo=postgresql)](https://www.postgresql.org/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

**MedFind** is a comprehensive web-based platform that connects consumers with nearby pharmacies in real-time. Built with Laravel 12, it provides live inventory tracking, batch management, secure messaging, and WebSocket-powered real-time updates.

---

## 🌟 Key Features

### For Consumers
- 🗺️ **Interactive Map** - Find nearby pharmacies using Leaflet with real-time location
- 🔍 **Medicine Search** - Autocomplete search with live stock availability
- 💬 **Secure Messaging** - Contact pharmacies directly with prescription upload (encrypted)
- 📱 **Real-Time Updates** - Live stock changes via WebSocket (Laravel Reverb)
- 🧭 **Directions** - Turn-by-turn routing to selected pharmacy

### For Pharmacies
- 📦 **Batch Inventory Management** - Track stock by batch, lot number, expiration date
- 🏪 **Receiving Module** - Record incoming stock from suppliers
- 💰 **Basic Sales** - FEFO (First Expired, First Out) stock allocation
- 📊 **ABC/VED Analysis** - Classify medicines by value and criticality
- 🔄 **Cycle Counting** - Physical inventory verification
- 📝 **Controlled Substance Log** - Regulatory compliance for dangerous drugs
- 🔔 **Real-Time Notifications** - Instant alerts for consumer messages
- 📈 **Audit Trail** - Complete history of inventory changes

### For Administrators
- ✅ **Pharmacy Approval** - Review and approve pharmacy registrations
- 👥 **User Management** - Manage consumers and pharmacy operators
- 📋 **Requirements Verification** - Validate LTO, Mayor's Permit, FDA License
- 📊 **System Monitoring** - Activity logs and search analytics

---

## 🏗️ Architecture

### Tech Stack
- **Backend:** Laravel 12 (PHP 8.2+)
- **Database:** PostgreSQL 17+
- **Frontend:** Blade Templates, Alpine.js, TailwindCSS 3
- **Maps:** Leaflet 1.9.4 with Leaflet Routing Machine
- **Real-Time:** Laravel Reverb (WebSocket server)
- **Storage:** Cloudflare R2 (S3-compatible) for pharmacy logos/documents
- **Email:** Resend API (HTTP-based, Railway-compatible)

### Domain Architecture
```
app/Domain/Inventory/
├── AggregateSynchronizer.php      # Sync batch & aggregate tables
├── BatchStockService.php          # Core batch CRUD operations
├── BasicSaleService.php           # Sales with FEFO allocation
├── FEFOAllocator.php              # First Expired, First Out logic
├── InventoryBatchService.php      # Batch receipt & adjustments
├── StockOperationRecorder.php     # Event sourcing for stock changes
└── Data/                          # DTOs and result objects
```

### Database Design
- **Dual-layer inventory**: `inventory_batches` (source of truth) + `inventories` (aggregate)
- **Cryptographic identity**: Batches use SHA-256 hash of `(pharmacy_id, medicine_id, lot_number, expiration_date)`
- **Event-driven sync**: Stock changes fire `InventoryUpdated` events
- **Audit trail**: All operations logged in `audits` table

---

## 🚀 Getting Started

### Prerequisites
- **PHP** 8.2 or higher
- **Composer** 2.x
- **Node.js** 20.x and npm
- **PostgreSQL** 17+ (or 15+ compatible)
- **Git**

### Installation

#### 1️⃣ Clone the Repository
```bash
git clone <repository-url>
cd medfind
```

#### 2️⃣ Install Dependencies
```bash
# Backend dependencies
composer install

# Frontend dependencies
npm install
```

#### 3️⃣ Environment Configuration
```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

#### 4️⃣ Configure `.env`
```ini
APP_NAME=MedFind
APP_URL=http://127.0.0.1:8000

# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=medfind
DB_USERNAME=postgres
DB_PASSWORD=your_password

# Mail (Resend)
MAIL_MAILER=resend
RESEND_API_KEY=your_resend_key
MAIL_FROM_ADDRESS=noreply@yourdomain.com

# Reverb WebSocket
REVERB_APP_ID=medfind-app
REVERB_APP_KEY=medfind-key-local
REVERB_APP_SECRET=medfind-secret-local
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

# Google Maps
GOOGLE_MAPS_API_KEY=your_api_key

# Cloudflare R2 (Optional - local uses 'public' disk)
FILESYSTEM_LOGO_DISK=public
```

#### 5️⃣ Database Setup
```bash
# Create PostgreSQL database
createdb medfind

# Run migrations
php artisan migrate

# (Optional) Seed sample data
php artisan db:seed
```

#### 6️⃣ Storage Link
```bash
php artisan storage:link
```

#### 7️⃣ Build Frontend Assets
```bash
# Development (with hot reload)
npm run dev

# Production
npm run build
```

---

## 🎯 Running the Application

### Development Mode

Open **3 terminals** and run:

#### Terminal 1: Laravel App
```bash
php artisan serve --host=127.0.0.1 --port=8000
```

#### Terminal 2: Reverb WebSocket Server
```bash
php artisan reverb:start --host=127.0.0.1 --port=8080
```

#### Terminal 3: Frontend Assets (if editing CSS/JS)
```bash
npm run dev
```

### Optional: Queue Worker
For background jobs (email notifications, broadcasts):
```bash
php artisan queue:work
```

### Access the Application
- **Consumer Interface:** http://127.0.0.1:8000
- **Login:** http://127.0.0.1:8000/login
- **Pharmacy Registration:** http://127.0.0.1:8000/pharmacy/register

---

## 👥 Default Accounts

### Localhost Test Accounts
| Role | Email | Password |
|------|-------|----------|
| Admin | `admin@medfind.com` | `password` |
| Pharmacy | `albaymedicalcenter@medfind.com` | `password123` |
| Consumer | `consumer@medfind.com` | `password` |

> ⚠️ **Security Note:** Change default passwords in production. See `docs/internal/SECURITY_AUDIT.md` for credential management.

---

## 📦 Project Structure

```
medfind/
├── app/
│   ├── Domain/Inventory/          # Business logic (DDD approach)
│   ├── Http/Controllers/          # Web controllers
│   ├── Events/                    # Broadcast events
│   └── Database/Migration/        # One-time migration utilities
├── database/
│   ├── migrations/                # Schema migrations
│   └── seeders/                   # Data seeders
├── resources/
│   ├── views/                     # Blade templates
│   │   ├── consumer/              # Consumer-facing pages
│   │   ├── pharmacy/              # Pharmacy dashboard
│   │   └── admin/                 # Admin panel
│   └── js/                        # Frontend JS modules
│       ├── app.js                 # Entry point
│       ├── echo.js                # WebSocket configuration
│       └── bootstrap.js           # Axios, CSRF setup
├── public/
│   └── js/medfind.js             # Compiled frontend logic
├── routes/
│   ├── web.php                    # HTTP routes
│   └── channels.php               # Broadcast channels
├── docs/
│   └── internal/                  # Internal documentation
│       ├── SETUP.md               # Detailed setup guide
│       └── SECURITY_AUDIT.md      # Security checklist
└── .env                           # Environment configuration
```

---

## 🔐 Security Features

### Authentication & Authorization
- **Multi-role system:** Admin, Pharmacy, Pharmacy Operator, Consumer
- **Email verification** required for registration
- **Middleware protection:** `CheckRole`, `PharmacyPendingMiddleware`
- **CSRF protection** on all forms

### Data Protection
- **Encrypted prescription images** - Never stored in plaintext
- **Secure file uploads** - Validated types and sizes
- **SQL injection prevention** - Eloquent ORM with parameter binding
- **XSS protection** - Blade template escaping

### Compliance
- **Controlled substance log** - Tracks dangerous drugs per DOH regulations
- **Audit trail** - All inventory changes logged with user attribution
- **Data retention** - Soft deletes for regulatory compliance

---

## 🔄 Real-Time Features

### WebSocket Channels
| Channel | Purpose | Listeners |
|---------|---------|-----------|
| `inventory` | Public stock updates | All consumers |
| `inventory.{pharmacyId}` | Pharmacy-specific updates | Pharmacy dashboard |
| `pharmacy.{pharmacyId}` | Message notifications | Pharmacy staff |
| `consumer.{consumerId}` | Pharmacy replies | Consumer chat |

### Event Broadcasting
- **InventoryUpdated** - Fired on stock changes (create, update, delete, receive, sale)
- **MessageSent** - Fired when consumer sends message or pharmacy replies

---

## 📊 Testing

### Run Tests
```bash
# All tests
php artisan test

# Specific test
php artisan test --filter=SourcePreparationTest

# With coverage
php artisan test --coverage
```

### Property-Based Testing
Uses `giorgiosironi/eris` for fuzzing batch identity hashing and FEFO allocation.

---

## 🚢 Deployment

### Railway Deployment
See `RAILWAY_ENV_CHECKLIST.md` for environment variable checklist.

#### Key Configuration
```bash
# Set in Railway environment
FILESYSTEM_LOGO_DISK=r2               # Use R2 for file storage
APP_ENV=production
APP_DEBUG=false
REVERB_SCHEME=https                   # Enable TLS
```

#### Build Command
```bash
composer install --no-dev --optimize-autoloader && npm ci && npm run build && php artisan config:cache && php artisan route:cache && php artisan view:cache
```

#### Start Command
```bash
php artisan migrate --force && php artisan reverb:start --host=0.0.0.0 --port=${PORT:-8080} & php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
```

---

## 🛠️ Maintenance

### Clear Cache
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Database Backup
```bash
pg_dump -U postgres -d medfind > backup_$(date +%Y%m%d).sql
```

### Log Monitoring
```bash
# Tail logs
php artisan pail

# Or manually
tail -f storage/logs/laravel.log
```

---

## 📝 API Endpoints

### Consumer API
- `GET /` - Home page with map
- `GET /consumer/search` - Medicine search results
- `GET /consumer/pharmacy/{id}` - Pharmacy details
- `POST /consumer/message/send` - Send message to pharmacy

### Pharmacy API
- `GET /pharmacy/dashboard` - Pharmacy dashboard
- `GET /pharmacy/inventory` - Inventory list
- `POST /pharmacy/receiving` - Record incoming stock
- `POST /pharmacy/sales` - Record sale
- `GET /pharmacy/messages` - Message inbox

### Admin API
- `GET /admin/pharmacies` - Pharmacy list with approval
- `POST /admin/pharmacies/{id}/approve` - Approve pharmacy
- `GET /admin/users` - User management

---

## 🤝 Contributing

### Code Style
- Follow **PSR-12** coding standard
- Run `php artisan pint` before committing
- Write tests for new features

### Git Workflow
1. Create feature branch: `git checkout -b feature/your-feature`
2. Commit changes: `git commit -am 'Add feature'`
3. Push branch: `git push origin feature/your-feature`
4. Create Pull Request

---

## 📄 License

This project is licensed under the **MIT License**.

---

## 📧 Support

For issues, questions, or contributions:
- **Email:** support@medfind.com
- **Documentation:** See `/docs/internal/` for detailed guides
- **Security Issues:** Report privately to security@medfind.com

---

## 🙏 Acknowledgments

- **Laravel Framework** - Elegant PHP framework
- **Leaflet** - Mobile-friendly interactive maps
- **TailwindCSS** - Utility-first CSS framework
- **PostgreSQL** - Advanced open-source database
- **Cloudflare R2** - Cost-effective object storage
- **Railway** - Modern cloud deployment platform

---

**Built with ❤️ for improving healthcare accessibility in the Philippines**
