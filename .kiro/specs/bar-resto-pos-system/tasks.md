# Implementation Plan: Bar/Resto POS System

## Overview

Implementasi sistem POS berbasis web untuk Bar/Resto UMKM menggunakan Laravel 11 (API backend) + React.js + Tailwind CSS (frontend), dengan MySQL sebagai database. Stok dikelola di level bahan baku via resep (BOM), checkout bersifat atomik, dan mendukung pemesanan hybrid (self-order via QR meja & kasir).

## Tasks

- [x] 1. Setup project dan konfigurasi awal
  - [x] 1.1 Inisialisasi project Laravel 11 dan install dependencies backend
    - Install Laravel 11 via Composer
    - Install packages: `laravel/sanctum`, `barryvdh/laravel-dompdf`, `maatwebsite/excel`, `simplesoftwareio/simple-qrcode`, `giorgiosironi/eris` (dev)
    - Konfigurasi `.env`: DB, APP_URL, Sanctum
    - Publish Sanctum config dan jalankan migrasi Sanctum
    - _Requirements: 11.12_

  - [x] 1.2 Inisialisasi project React.js dan install dependencies frontend
    - Buat project React dengan Vite
    - Install: `tailwindcss`, `axios`, `react-router-dom`, `zustand`, `recharts`, `fast-check` (dev)
    - Konfigurasi Tailwind CSS dan setup folder struktur sesuai design (`pages/`, `components/`, `services/`, `stores/`, `hooks/`)
    - _Requirements: 11.12_


- [x] 2. Database migrations
  - [x] 2.1 Buat migration untuk tabel `categories`, `users`, dan `products`
    - `categories`: id, name, type (enum: product, expense), timestamps
    - `users`: id, name, email (unique), password, role (enum: pelanggan, kasir, finance, head_manager), is_active, timestamps
    - `products`: id, sku (unique), name, description, category_id (FK), unit, buy_price, sell_price, stock, low_stock_threshold, is_available, image_path, timestamps
    - Tambahkan indexes: `idx_products_sku`, `idx_products_is_available`, `idx_products_category`
    - _Requirements: 10.1, 10.2, 10.10, 10.11_

  - [x] 2.2 Buat migration untuk tabel `recipes` dan `stock_movements`
    - `recipes`: id, menu_product_id (FK products), raw_material_id (FK products), quantity_required, unit, timestamps
    - `stock_movements`: id, product_id (FK), type (enum: in, out), quantity, stock_before, stock_after, reference_type, reference_id, notes, created_by (FK users), timestamps
    - Tambahkan indexes: `idx_stock_movements_product`, `idx_stock_movements_created_at`, `idx_stock_movements_reference`
    - _Requirements: 10.3, 10.11_

  - [x] 2.3 Buat migration untuk tabel `tables`, `orders`, dan `order_items`
    - `tables`: id, table_number (unique), name, qr_code (unique), capacity, status (enum: available, occupied, reserved), timestamps
    - `orders`: id, order_number (unique), order_code (unique), user_id (FK nullable), table_id (FK nullable), created_by (FK users), order_type (enum: self_order, take_away, dine_in), status (enum: pending, confirmed, preparing, ready, completed, cancelled), notes, cancellation_reason, timestamps
    - `order_items`: id, order_id (FK), product_id (FK), quantity, unit_price, subtotal, timestamps
    - Tambahkan indexes: `idx_orders_status`, `idx_orders_table`, `idx_orders_created_at`, `idx_orders_order_code`
    - _Requirements: 10.4, 10.5, 10.6, 10.11_

  - [x] 2.4 Buat migration untuk tabel `transactions`, `income_entries`, dan `expense_entries`
    - `transactions`: id, transaction_number (unique), order_id (FK), payment_method (enum: cash, card, qris), total_amount, paid_amount, change_amount, status (enum: success, failed, refunded), processed_by (FK users), timestamps
    - `income_entries`: id, transaction_id (FK nullable), date, amount, category, description, source (enum: pos, manual), status (enum: pending, validated), created_by (FK users), timestamps
    - `expense_entries`: id, date, amount, category, description, receipt_path, created_by (FK users), timestamps
    - Tambahkan indexes: `idx_transactions_order`, `idx_transactions_created_at`, `idx_income_date`, `idx_expense_date`
    - _Requirements: 10.7, 10.8, 10.9, 10.11_


- [x] 3. Eloquent Models dan Relationships
  - [x] 3.1 Buat Models: `User`, `Category`, `Product`, `Recipe`
    - `User`: fillable, hidden (password), casts (role enum, is_active bool), relasi `hasMany` ke orders, stock_movements, transactions, income_entries, expense_entries
    - `Category`: fillable, relasi `hasMany` products
    - `Product`: fillable, casts (buy_price, sell_price, stock decimal), relasi `belongsTo` category, `hasMany` stock_movements, order_items; `hasMany` recipes sebagai menu (`menuRecipes`) dan sebagai bahan baku (`rawMaterialRecipes`)
    - `Recipe`: fillable, relasi `belongsTo` Product (menu_product_id) dan Product (raw_material_id)
    - _Requirements: 10.1, 10.2, 10.11_

  - [x] 3.2 Buat Models: `StockMovement`, `Table`, `Order`, `OrderItem`
    - `StockMovement`: fillable, relasi `belongsTo` Product, User (created_by)
    - `Table`: fillable, casts (status enum), relasi `hasMany` orders
    - `Order`: fillable, casts (order_type, status enum), relasi `belongsTo` Table, User (user_id), User (created_by); `hasMany` order_items; `hasOne` transaction
    - `OrderItem`: fillable, casts (unit_price, subtotal decimal), relasi `belongsTo` Order, Product
    - _Requirements: 10.3, 10.4, 10.5, 10.6_

  - [x] 3.3 Buat Models: `Transaction`, `IncomeEntry`, `ExpenseEntry`
    - `Transaction`: fillable, casts (payment_method, status enum, total_amount, paid_amount, change_amount decimal), relasi `belongsTo` Order, User (processed_by); `hasOne` income_entry
    - `IncomeEntry`: fillable, casts (source, status enum, amount decimal), relasi `belongsTo` Transaction, User (created_by)
    - `ExpenseEntry`: fillable, casts (amount decimal), relasi `belongsTo` User (created_by)
    - _Requirements: 10.7, 10.8, 10.9_


- [x] 4. Autentikasi & Middleware
  - [x] 4.1 Implementasi `AuthService` dan `AuthController`
    - `AuthService::login(array $credentials): array` — validasi kredensial, buat Sanctum token, return token + user data + role
    - `AuthService::logout(User $user): void` — revoke current token
    - `AuthController`: endpoint `POST /api/v1/auth/login`, `POST /api/v1/auth/logout`, `GET /api/v1/auth/me`
    - Buat `LoginRequest` dengan validasi email + password
    - Buat `UserResource` untuk format response user
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 11.2_

  - [x] 4.2 Implementasi `RoleMiddleware` dan user management
    - Buat `app/Http/Middleware/RoleMiddleware.php` — cek role user dari token, return 403 jika tidak sesuai
    - Daftarkan middleware di `bootstrap/app.php` sebagai alias `role`
    - Buat endpoint user management di `AuthController`: `GET /api/v1/users`, `POST /api/v1/users`, `PUT /api/v1/users/{id}`, `PUT /api/v1/users/{id}/deactivate`
    - `deactivate`: set `is_active = false`, revoke semua token user tersebut via `$user->tokens()->delete()`
    - Password di-hash dengan `bcrypt()` saat create/update
    - _Requirements: 1.6, 1.7, 1.8_

  - [x] 4.3 Tulis property test untuk autentikasi (P1, P2, P3, P4)
    - **Property 1: Valid Login Returns Token dan Role** — for any valid credentials, login returns token + role
    - **Property 2: Role-Based Access Control** — for any protected endpoint, access granted iff role is allowed
    - **Property 3: Password Tersimpan Sebagai Bcrypt Hash** — password column never stores plaintext
    - **Property 4: Deaktivasi User Mencabut Semua Token** — deactivation invalidates all active tokens
    - File: `tests/Property/AuthPropertyTest.php`, `tests/Property/RoleAccessPropertyTest.php`, `tests/Property/PasswordHashPropertyTest.php`, `tests/Property/UserDeactivationPropertyTest.php`
    - **Validates: Requirements 1.2, 1.6, 1.7, 1.8**

- [x] 5. Checkpoint — Pastikan semua tests auth pass
  - Pastikan semua tests pass, tanyakan ke user jika ada pertanyaan.


- [x] 6. Inventory Module (Bahan Baku & Stok)
  - [x] 6.1 Implementasi `InventoryService` dan `ProductRepository`
    - `ProductRepository`: `findBySku`, `findLowStock`, `findById`, `create`, `update`, `delete`
    - `InventoryService::addStock(Product $product, int $quantity, string $reference): StockMovement` — tambah stok, catat stock_movement dengan stock_before/after
    - `InventoryService::deductStock(Product $product, int $quantity, string $refType, int $refId): StockMovement` — kurangi stok, catat stock_movement
    - `InventoryService::getLowStockItems(): Collection` — return items dengan stock ≤ low_stock_threshold
    - `InventoryService::checkStockSufficiency(int $productId, int $requiredQty): bool`
    - _Requirements: 2.1, 2.4, 2.5, 2.6, 2.7, 2.9_

  - [x] 6.2 Implementasi `ProductController` dan `StockMovementController`
    - `ProductController`: CRUD endpoints `GET/POST /api/v1/products`, `GET/PUT/DELETE /api/v1/products/{id}`, `GET /api/v1/products/low-stock`
    - Validasi SKU unik di `CreateProductRequest` (rule `unique:products,sku`)
    - Return 409 jika SKU duplikat
    - `StockMovementController`: `POST /api/v1/stock-movements` (catat pergerakan manual), `GET /api/v1/stock-movements` (riwayat dengan filter)
    - Buat `ProductResource`, `StockMovementResource`
    - _Requirements: 2.2, 2.3, 2.8, 11.3, 11.4_

  - [x] 6.3 Tulis property test untuk inventory (P5, P6, P7)
    - **Property 5: SKU Bersifat Unik** — duplicate SKU insert must be rejected
    - **Property 6: Stock Movement Round-Trip** — stock_after = stock_before ± quantity, consistent record
    - **Property 7: Low Stock Threshold Detection** — item appears in low-stock list iff stock ≤ threshold
    - File: `tests/Property/SkuUniquenessPropertyTest.php`, `tests/Property/StockMovementPropertyTest.php`, `tests/Property/LowStockPropertyTest.php`
    - **Validates: Requirements 2.2, 2.4, 2.5, 2.6, 2.7, 2.8, 2.9**


- [x] 7. Menu Module (Produk Menu & Resep/BOM)
  - [x] 7.1 Implementasi endpoint menu dan recipe management
    - Tambahkan endpoint di `ProductController` untuk filter menu berdasarkan kategori: `GET /api/v1/products?category_id=X&type=menu`
    - Buat `RecipeController`: `GET /api/v1/products/{id}/recipes`, `POST /api/v1/products/{id}/recipes`, `PUT /api/v1/recipes/{id}`, `DELETE /api/v1/recipes/{id}`
    - Endpoint public `GET /api/v1/tables/{uuid}/menu` — return menu dengan `is_available = true` tanpa auth
    - Saat update harga menu (`PUT /api/v1/products/{id}`), simpan harga baru; harga lama di order_items yang sudah ada tidak berubah (snapshot di `unit_price`)
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

  - [x] 7.2 Tulis property test untuk menu (P8, P9, P10)
    - **Property 8: Menu Visibility Berdasarkan Ketersediaan** — is_available=false hides menu from public endpoint
    - **Property 9: Harga Menu Terbaru Digunakan di Order Baru** — new orders use updated price as unit_price
    - **Property 10: Filter Menu Berdasarkan Kategori** — filter returns only items matching category_id
    - File: `tests/Property/MenuVisibilityPropertyTest.php`, `tests/Property/MenuPricePropertyTest.php`, `tests/Property/MenuFilterPropertyTest.php`
    - **Validates: Requirements 3.2, 3.3, 3.5**


- [x] 8. Table Management & QR Code
  - [x] 8.1 Implementasi `QRCodeService` dan `TableService`
    - `QRCodeService::generateTableQR(Table $table): string` — generate QR code SVG/PNG dengan payload `APP_URL/order?table={table.uuid}`, idempotent (selalu hasilkan URL yang sama untuk meja yang sama)
    - `TableService::create(array $data): Table` — buat meja, auto-generate QR code, simpan ke kolom `qr_code`
    - `TableService::updateStatus(Table $table, string $status): Table`
    - Validasi `table_number` unik di `CreateTableRequest`
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7_

  - [x] 8.2 Implementasi `TableController`
    - CRUD endpoints: `GET/POST /api/v1/tables`, `GET/PUT /api/v1/tables/{id}`
    - `GET /api/v1/tables/{id}/qr` — return QR code image (SVG/PNG) untuk meja
    - Return 404 jika meja tidak ditemukan, 409 jika table_number duplikat
    - Buat `TableResource`
    - _Requirements: 4.8, 4.9, 11.5, 11.6_

  - [x] 8.3 Tulis property test untuk table management (P11, P12, P13)
    - **Property 11: Table Number Bersifat Unik** — duplicate table_number insert must be rejected
    - **Property 12: QR Code Di-generate Otomatis Saat Meja Dibuat** — qr_code column is non-null after create
    - **Property 13: QR Code Generation Bersifat Idempotent** — repeated QR generation yields same URL/payload
    - File: `tests/Property/TableUniquenessPropertyTest.php`, `tests/Property/QrCodeGenerationPropertyTest.php`, `tests/Property/QrCodeIdempotencyPropertyTest.php`
    - **Validates: Requirements 4.2, 4.4, 4.10**

- [x] 9. Checkpoint — Pastikan semua tests inventory, menu, dan table pass
  - Pastikan semua tests pass, tanyakan ke user jika ada pertanyaan.


- [x] 10. Order Module (Pemesanan Hybrid)
  - [x] 10.1 Implementasi `OrderService` dan `OrderRepository`
    - `OrderRepository`: `findByCode`, `findActiveOrders`, `findWithItems`
    - `OrderService::createSelfOrder(array $items, string $tableUuid): Order` — buat order tipe `self_order`, generate `order_code` unik (format: `ORD-XXXX`), set table status → occupied
    - `OrderService::createCashierOrder(array $items, string $type, ?int $tableId): Order` — buat order tipe `take_away` atau `dine_in`; untuk `dine_in` validasi meja berstatus `available`
    - `OrderService::updateStatus(Order $order, string $status, ?string $reason = null): Order` — update status + timestamp; jika `completed`/`cancelled` dan ada table_id, set table status → available
    - `OrderService::findByCode(string $orderCode): Order`
    - Validasi ketersediaan menu (`is_available = true`) sebelum membuat order
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 5.8, 5.9, 5.10, 5.11, 5.12_

  - [x] 10.2 Implementasi `OrderController`
    - `GET /api/v1/orders` — list order aktif, support filter by status
    - `POST /api/v1/orders` — buat order (self_order tanpa auth, kasir dengan auth)
    - `GET /api/v1/orders/{id}` — detail order
    - `PUT /api/v1/orders/{id}/status` — update status order (Kasir)
    - `GET /api/v1/orders/by-code/{code}` — lookup order by Order_Code
    - Buat `CreateOrderRequest`, `OrderResource`, `OrderItemResource`
    - Return 409 jika meja tidak available untuk dine_in, 422 jika item tidak tersedia
    - _Requirements: 5.13, 11.7, 11.8_

  - [x] 10.3 Tulis property test untuk order module (P14, P15, P16)
    - **Property 14: Self-Order Menghasilkan Order Code Unik** — no two orders share the same order_code
    - **Property 15: Dine-In Memerlukan Meja Berstatus Available** — dine_in rejected if table not available
    - **Property 16: Table Status Lifecycle** — table becomes occupied on order create, available on complete/cancel
    - File: `tests/Property/OrderCodeUniquenessPropertyTest.php`, `tests/Property/DineInTablePropertyTest.php`, `tests/Property/TableStatusLifecyclePropertyTest.php`
    - **Validates: Requirements 5.2, 5.4, 5.5, 5.6, 5.9**


- [x] 11. Checkout / POS (Atomik)
  - [x] 11.1 Implementasi Custom Exceptions
    - Buat `app/Exceptions/InsufficientStockException.php` — HTTP 422, berisi daftar item kekurangan stok
    - Buat `app/Exceptions/InvalidPaymentException.php` — HTTP 422, paid_amount < total_amount
    - Buat `app/Exceptions/TableNotAvailableException.php` — HTTP 409, nomor meja + status saat ini
    - Buat `app/Exceptions/OrderAlreadyProcessedException.php` — HTTP 409
    - Daftarkan exception handler di `bootstrap/app.php` untuk return JSON response yang konsisten
    - _Requirements: 6.2, 6.3, 6.5_

  - [x] 11.2 Implementasi `CheckoutService`
    - `CheckoutService::validateStock(Order $order): void` — iterasi order_items → recipes → cek stock bahan baku; throw `InsufficientStockException` jika tidak cukup
    - `CheckoutService::processCheckout(Order $order, array $paymentData): Transaction` — dalam `DB::transaction()`: (1) buat transaction record, (2) `deductRawMaterialStock`, (3) update order status → completed, (4) update table status → available jika dine_in/self_order, (5) buat income_entry
    - `deductRawMaterialStock`: gunakan `lockForUpdate()` pada raw material, catat StockMovement per bahan baku
    - Validasi `paid_amount >= total_amount`, throw `InvalidPaymentException` jika tidak
    - _Requirements: 6.3, 6.4, 6.5, 6.7, 6.8, 6.10_

  - [x] 11.3 Implementasi `TransactionController` dan `ReceiptResource`
    - `POST /api/v1/transactions/checkout` — panggil `CheckoutService::processCheckout`, return `ReceiptResource`
    - `GET /api/v1/transactions/{id}/receipt` — return data struk untuk cetak ulang
    - `ReceiptResource`: transaction_number, datetime, table_number (jika ada), items (nama, qty, unit_price, subtotal), total_amount, paid_amount, change_amount, payment_method
    - Buat `CheckoutRequest` dengan validasi order_code, payment_method, paid_amount
    - _Requirements: 6.1, 6.6, 6.9, 11.9_

  - [x] 11.4 Tulis property test untuk checkout (P17, P18, P19, P20)
    - **Property 17: Validasi Jumlah Pembayaran** — checkout rejected if paid_amount < total_amount
    - **Property 18: Atomisitas Checkout** — all 3 conditions (stock deducted, transaction saved, order completed) happen together or not at all
    - **Property 19: Struk Mengandung Semua Field yang Diperlukan** — receipt contains all required fields
    - **Property 20: Kalkulasi Kembalian yang Benar** — change_amount = paid_amount - total_amount always
    - File: `tests/Property/PaymentValidationPropertyTest.php`, `tests/Property/CheckoutAtomicityPropertyTest.php`, `tests/Property/ReceiptCompletenessPropertyTest.php`; `src/__tests__/changeCalculation.property.test.js` (fast-check)
    - **Validates: Requirements 6.3, 6.4, 6.5, 6.6, 6.8**

  - [x] 11.5 Tulis feature tests untuk checkout flows
    - Test: checkout berhasil → stok berkurang, transaksi tersimpan, order completed, income_entry dibuat
    - Test: checkout gagal karena stok tidak cukup → rollback penuh (tidak ada transaksi, stok tidak berubah)
    - Test: checkout gagal karena paid_amount kurang → return 422
    - Test: lookup order by code yang sudah diproses → return 409
    - File: `tests/Feature/CheckoutTest.php`
    - _Requirements: 6.4, 6.5_

- [x] 12. Checkpoint — Pastikan semua tests order dan checkout pass
  - Pastikan semua tests pass, tanyakan ke user jika ada pertanyaan.


- [x] 13. Finance Module
  - [x] 13.1 Implementasi `FinanceService` dan `FinanceController`
    - `FinanceService::getSummary(string $period, ?string $startDate, ?string $endDate): array` — hitung total_income, total_expense, net_profit; tandai "rugi" jika net_profit < 0
    - `FinanceController`:
      - `GET /api/v1/expenses` — list pengeluaran dengan filter tanggal
      - `POST /api/v1/expenses` — tambah pengeluaran baru (Finance role)
      - `GET /api/v1/income` — list pemasukan dengan filter tanggal
      - `PUT /api/v1/income/{id}/validate` — update status income_entry → validated
      - `GET /api/v1/finance/summary` — rekap keuangan (daily/weekly/monthly via query param)
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 7.7, 11.10_

  - [x] 13.2 Tulis property test untuk finance (P21)
    - **Property 21: Kalkulasi Summary Keuangan** — net_profit = total_income - total_expense always
    - File: `tests/Property/FinanceSummaryPropertyTest.php`
    - **Validates: Requirements 7.5**


- [x] 14. Reporting Module (PDF & Excel)
  - [x] 14.1 Implementasi `ReportService` dan template laporan
    - `ReportService::generateStockReport(string $format, ?string $startDate, ?string $endDate)` — semua produk + stock, stock_value (buy_price × stock), status low_stock
    - `ReportService::generateStockMovementReport(string $format, string $startDate, string $endDate)` — riwayat stock_movements dalam rentang tanggal
    - `ReportService::generateProfitLossReport(string $format, string $startDate, string $endDate)` — total_income, total_expense, net_profit
    - Buat Blade templates untuk PDF: `resources/views/reports/stock.blade.php`, `stock-movement.blade.php`, `profit-loss.blade.php`
    - Buat Excel export classes: `app/Exports/StockExport.php`, `StockMovementExport.php`, `ProfitLossExport.php`
    - Validasi `start_date <= end_date` di `ReportRequest`, return 400 jika tidak valid
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6, 8.7, 8.8_

  - [x] 14.2 Implementasi `ReportController`
    - `GET /api/v1/reports/stock?format=pdf|excel&start_date=&end_date=`
    - `GET /api/v1/reports/stock-movement?format=pdf|excel&start_date=&end_date=`
    - `GET /api/v1/reports/profit-loss?format=pdf|excel&start_date=&end_date=`
    - Return file download response (Content-Disposition: attachment)
    - _Requirements: 11.11_

  - [x] 14.3 Tulis property test untuk reporting (P22, P23)
    - **Property 22: Kelengkapan Data Laporan Stok** — report contains all products with accurate stock_value and low_stock status
    - **Property 23: Round-Trip Data Numerik PDF/Excel** — numeric values in PDF export identical to Excel export for same data/period
    - File: `tests/Property/ReportCompletenessPropertyTest.php`, `tests/Property/ReportRoundTripPropertyTest.php`
    - **Validates: Requirements 8.1, 8.9**


- [x] 15. Dashboard API
  - [x] 15.1 Implementasi `DashboardController`
    - `GET /api/v1/dashboard` — return: total_sales_today (sum transactions hari ini), total_transactions_today (count), critical_stock_items (list produk dengan stock ≤ threshold), sales_chart_7days (array {date, total} 7 hari terakhir), occupied_tables_count
    - Query harus efisien (gunakan index yang sudah dibuat), response dalam < 5 detik
    - _Requirements: 9.1, 9.2, 9.3, 9.5_

  - [x] 15.2 Tulis property test untuk database constraints (P24)
    - **Property 24: Database Constraint Enforcement** — FK and unique constraint violations are rejected by DB and system returns appropriate error
    - File: `tests/Property/DatabaseConstraintPropertyTest.php`
    - **Validates: Requirements 10.11**

- [x] 16. Checkpoint — Pastikan semua backend tests pass
  - Pastikan semua tests pass, tanyakan ke user jika ada pertanyaan.


- [x] 17. Frontend: Setup, Auth, dan Layout
  - [x] 17.1 Setup Axios instance, auth store, dan routing
    - Buat `src/services/api.js` — Axios instance dengan `baseURL=/api/v1/`, request interceptor (attach Bearer token), response interceptor (redirect ke login jika 401)
    - Buat `src/stores/authStore.js` (Zustand) — state: user, token, role; actions: login, logout, setUser
    - Buat `src/hooks/useAuth.js` — wrapper untuk authStore
    - Setup React Router: route guard `ProtectedRoute` berdasarkan role, redirect unauthenticated ke `/login`
    - _Requirements: 1.1, 1.4, 1.5_

  - [x] 17.2 Implementasi halaman Login dan layout utama
    - Buat `src/pages/auth/Login.jsx` — form email + password, call `POST /api/v1/auth/login`, simpan token ke authStore, redirect berdasarkan role
    - Buat layout komponen: `Sidebar.jsx` (navigasi berdasarkan role), `Header.jsx`, `MainLayout.jsx`
    - Buat UI components: `src/components/ui/Button.jsx`, `Modal.jsx`, `Table.jsx`, `Badge.jsx`
    - _Requirements: 1.2, 1.3_


- [x] 18. Frontend: Dashboard Manager
  - [x] 18.1 Implementasi `src/pages/manager/Dashboard.jsx`
    - Fetch `GET /api/v1/dashboard` saat mount dan setiap 60 detik (setInterval)
    - Tampilkan StatCards: total penjualan hari ini, total transaksi, stok kritis, meja terisi
    - Buat `src/components/charts/SalesChart.jsx` menggunakan Recharts — bar/line chart penjualan 7 hari
    - Tampilkan daftar stok kritis (nama produk, stok saat ini, threshold)
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5_


- [x] 19. Frontend: Inventory, Menu, dan Table Management
  - [x] 19.1 Implementasi halaman Products/Inventory (`src/pages/manager/Products.jsx`)
    - List produk dengan kolom: SKU, nama, kategori, stok, threshold, harga beli, harga jual, status
    - Form modal untuk tambah/edit produk (validasi SKU unik di frontend)
    - Tombol tambah/kurangi stok manual (call `POST /api/v1/stock-movements`)
    - Tab atau filter untuk melihat daftar stok kritis
    - _Requirements: 2.1, 2.2, 2.3, 2.6, 2.7, 2.8_

  - [x] 19.2 Implementasi halaman Recipe/BOM management
    - Di halaman detail produk (menu), tampilkan daftar resep (bahan baku + qty)
    - Form untuk tambah/edit/hapus recipe item
    - _Requirements: 3.1_

  - [x] 19.3 Implementasi halaman Tables (`src/pages/manager/Tables.jsx`)
    - List meja dengan status badge (available/occupied/reserved)
    - Form modal untuk tambah meja baru
    - Tombol "Lihat QR" — fetch `GET /api/v1/tables/{id}/qr` dan tampilkan QR code image dalam modal
    - Tombol update status meja manual
    - _Requirements: 4.1, 4.2, 4.4, 4.5, 4.6, 4.7, 4.9_


- [x] 20. Frontend: POS/Kasir Interface
  - [x] 20.1 Implementasi halaman Orders Kasir (`src/pages/cashier/Orders.jsx`)
    - List order aktif dengan filter by status (pending, confirmed, preparing, ready)
    - Tombol update status order per item
    - Badge warna berdasarkan status order
    - _Requirements: 5.13_

  - [x] 20.2 Implementasi halaman Checkout Kasir (`src/pages/cashier/Checkout.jsx`)
    - Buat `src/components/pos/OrderCodeInput.jsx` — input field untuk scan/ketik Order_Code, call `GET /api/v1/orders/by-code/{code}`, tampilkan detail order
    - Buat `src/components/pos/PaymentForm.jsx` — pilih metode bayar (cash/card/qris), input paid_amount, tampilkan kembalian real-time (calculated di frontend)
    - Buat `src/components/pos/ReceiptModal.jsx` — tampilkan struk setelah checkout berhasil, tombol cetak (window.print)
    - Call `POST /api/v1/transactions/checkout` saat konfirmasi bayar
    - _Requirements: 6.1, 6.2, 6.3, 6.6, 6.7, 6.8_

  - [x] 20.3 Implementasi halaman Self-Order Pelanggan (`src/pages/customer/SelfOrder.jsx`)
    - Baca `table` UUID dari query param (`/order?table=UUID`)
    - Fetch `GET /api/v1/tables/{uuid}/menu` — tampilkan info meja + daftar menu
    - UI pilih menu + qty, tambah ke cart (state lokal)
    - Submit order: call `POST /api/v1/orders` dengan tipe `self_order`
    - Tampilkan `order_code` yang dikembalikan API dalam format yang jelas (besar, mudah dibaca)
    - _Requirements: 5.1, 5.2, 5.7, 5.8_


- [x] 21. Frontend: Finance dan Reports
  - [x] 21.1 Implementasi halaman Expenses (`src/pages/finance/Expenses.jsx`)
    - List pengeluaran dengan filter tanggal
    - Form modal tambah pengeluaran baru (tanggal, jumlah, kategori, keterangan, upload bukti)
    - _Requirements: 7.2, 7.3_

  - [x] 21.2 Implementasi halaman Finance Summary (`src/pages/finance/Summary.jsx`)
    - Tampilkan rekap keuangan: total pemasukan, total pengeluaran, laba/rugi bersih
    - Toggle period: daily/weekly/monthly
    - Tandai periode "rugi" dengan warna merah
    - List income_entries dengan tombol validasi
    - _Requirements: 7.4, 7.5, 7.6, 7.7_

  - [x] 21.3 Implementasi halaman Reports (`src/pages/manager/Reports.jsx`)
    - Form pilih jenis laporan (stok / arus barang / laba-rugi), format (PDF/Excel), rentang tanggal
    - Validasi start_date ≤ end_date di frontend sebelum submit
    - Tombol download — call endpoint report dengan `format=pdf` atau `format=excel`, trigger file download via Blob URL
    - _Requirements: 8.1, 8.2, 8.3, 8.6, 8.7_

- [x] 22. Checkpoint — Pastikan semua frontend berfungsi dan tests pass
  - Pastikan semua tests pass, tanyakan ke user jika ada pertanyaan.


- [x] 23. Feature Tests Backend
  - [x] 23.1 Tulis feature tests untuk Auth module
    - Test: login dengan kredensial valid → return token + role
    - Test: login dengan kredensial invalid → return 401
    - Test: akses endpoint protected tanpa token → return 401
    - Test: akses endpoint dengan role yang salah → return 403
    - Test: deaktivasi user → semua token dicabut
    - File: `tests/Feature/AuthTest.php`
    - _Requirements: 1.2, 1.3, 1.5, 1.6, 1.8_

  - [x] 23.2 Tulis feature tests untuk Inventory module
    - Test: tambah produk dengan SKU duplikat → return 409
    - Test: tambah stok → stock bertambah + stock_movement tercatat
    - Test: kurangi stok → stock berkurang + stock_movement tercatat
    - Test: produk dengan stock ≤ threshold muncul di low-stock list
    - File: `tests/Feature/InventoryTest.php`
    - _Requirements: 2.2, 2.3, 2.4, 2.5, 2.6, 2.9_

  - [x] 23.3 Tulis feature tests untuk Order dan Table module
    - Test: self-order via QR → order dibuat, table status → occupied, order_code dikembalikan
    - Test: dine_in dengan meja occupied → return 409
    - Test: order completed → table status → available
    - Test: order cancelled dengan alasan → status cancelled + cancellation_reason tersimpan
    - Test: tambah meja dengan table_number duplikat → return 409
    - File: `tests/Feature/OrderTest.php`, `tests/Feature/TableTest.php`
    - _Requirements: 5.2, 5.4, 5.5, 5.6, 5.9, 5.12, 4.2_

  - [x] 23.4 Tulis feature tests untuk Finance dan Report module
    - Test: tambah expense → total_expense harian bertambah
    - Test: finance summary menghitung net_profit dengan benar
    - Test: generate laporan stok PDF → return file PDF
    - Test: generate laporan dengan start_date > end_date → return 400
    - File: `tests/Feature/FinanceTest.php`, `tests/Feature/ReportTest.php`
    - _Requirements: 7.3, 7.5, 8.4, 8.7_

- [x] 24. Final Checkpoint — Semua tests pass
  - Pastikan semua tests pass (backend + frontend), tanyakan ke user jika ada pertanyaan.

## Notes

- Tasks bertanda `*` bersifat opsional dan dapat dilewati untuk MVP yang lebih cepat
- Setiap task mereferensikan requirements spesifik untuk traceability
- Checkout atomik (Task 11) adalah inti sistem — pastikan `DB::transaction()` dan `lockForUpdate()` diimplementasikan dengan benar
- Property tests menggunakan `giorgiosironi/eris` (PHP) dan `fast-check` (JavaScript), minimum 100 iterasi per property
- Semua 24 correctness properties dari design document dicakup oleh property tests di tasks 4.3, 6.3, 7.2, 8.3, 10.3, 11.4, 13.2, 14.3, 15.2
