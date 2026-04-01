# Requirements Document

## Introduction

Sistem Manajemen Inventaris & POS (Point of Sales) untuk Bar/Resto UMKM berbasis web. Sistem ini mencakup manajemen stok bahan baku dan produk, pemesanan hybrid (self-order pelanggan & kasir), transaksi POS, pencatatan keuangan harian, dan pelaporan dalam format PDF/Excel. Dibangun dengan Laravel 11 (API), MySQL, React.js/Vue.js + Tailwind CSS.

## Glossary

- **System**: Aplikasi Sistem Manajemen Inventaris & POS Bar/Resto UMKM secara keseluruhan
- **Inventory_Manager**: Modul yang mengelola stok bahan baku dan produk jadi
- **Order_Manager**: Modul yang mengelola pemesanan dari pelanggan maupun kasir
- **POS**: Modul kasir untuk transaksi penjualan dan pembayaran
- **Finance_Manager**: Modul pencatatan pemasukan dan pengeluaran keuangan
- **Report_Generator**: Modul yang menghasilkan laporan dalam format PDF dan Excel
- **Auth_Service**: Layanan autentikasi dan otorisasi berbasis Sanctum/JWT
- **Pelanggan**: Pengguna akhir yang melihat menu dan melakukan pemesanan via web
- **Kasir**: Staf yang mengelola pesanan di tempat dan memproses pembayaran
- **Finance**: Staf keuangan yang mengelola pengeluaran dan laporan keuangan
- **Head_Manager**: Pengelola utama dengan akses penuh ke semua fitur dan laporan
- **SKU**: Stock Keeping Unit, kode unik identifikasi produk/bahan baku
- **Low_Stock_Threshold**: Batas minimum stok yang memicu notifikasi peringatan
- **Struk**: Bukti transaksi yang dicetak atau ditampilkan setelah pembayaran
- **Pretty_Printer**: Komponen yang memformat data menjadi output yang dapat dibaca manusia

---

## Requirements

### Requirement 1: Autentikasi & Manajemen Pengguna

**User Story:** Sebagai Head_Manager, saya ingin mengelola akun pengguna dengan role berbeda, agar setiap staf hanya dapat mengakses fitur sesuai tanggung jawabnya.

#### Acceptance Criteria

1. THE Auth_Service SHALL mendukung empat role pengguna: Pelanggan, Kasir, Finance, dan Head_Manager
2. WHEN pengguna mengirimkan kredensial yang valid, THE Auth_Service SHALL mengembalikan token autentikasi (Sanctum/JWT) beserta data role pengguna
3. IF pengguna mengirimkan kredensial yang tidak valid, THEN THE Auth_Service SHALL mengembalikan respons error dengan kode HTTP 401 dan pesan deskriptif
4. WHILE pengguna memiliki sesi aktif, THE Auth_Service SHALL memvalidasi token pada setiap permintaan ke endpoint yang dilindungi
5. IF token autentikasi kedaluwarsa atau tidak valid, THEN THE Auth_Service SHALL mengembalikan respons error dengan kode HTTP 401
6. THE Auth_Service SHALL menerapkan middleware otorisasi berbasis role sehingga setiap endpoint hanya dapat diakses oleh role yang diizinkan
7. WHEN Head_Manager membuat akun pengguna baru, THE Auth_Service SHALL menyimpan data pengguna dengan password yang di-hash menggunakan bcrypt
8. WHEN Head_Manager menonaktifkan akun pengguna, THE Auth_Service SHALL mencabut semua token aktif milik pengguna tersebut

### Requirement 2: Manajemen Stok (Inventory)

**User Story:** Sebagai Head_Manager atau Kasir, saya ingin mencatat dan memantau stok bahan baku serta produk, agar operasional bar/resto tidak terganggu akibat kehabisan stok.

#### Acceptance Criteria

1. THE Inventory_Manager SHALL menyimpan data setiap item stok dengan atribut: SKU, nama, kategori, satuan, harga beli, harga jual, dan jumlah stok saat ini
2. WHEN staf yang berwenang menambahkan item stok baru, THE Inventory_Manager SHALL memvalidasi bahwa SKU bersifat unik sebelum menyimpan data
3. IF SKU yang dimasukkan sudah ada, THEN THE Inventory_Manager SHALL mengembalikan pesan error yang menyebutkan SKU yang duplikat
4. WHEN terjadi transaksi barang masuk (pembelian/penerimaan), THE Inventory_Manager SHALL menambah jumlah stok item yang bersangkutan dan mencatat riwayat pergerakan stok
5. WHEN terjadi transaksi barang keluar (penjualan/pemakaian), THE Inventory_Manager SHALL mengurangi jumlah stok item yang bersangkutan dan mencatat riwayat pergerakan stok
6. IF jumlah stok item mencapai atau di bawah Low_Stock_Threshold yang telah ditetapkan, THEN THE Inventory_Manager SHALL menghasilkan notifikasi low stock yang dapat diakses oleh Kasir dan Head_Manager
7. THE Inventory_Manager SHALL menyediakan daftar semua item dengan stok di bawah Low_Stock_Threshold
8. WHEN staf yang berwenang memperbarui Low_Stock_Threshold suatu item, THE Inventory_Manager SHALL menyimpan nilai baru dan mengevaluasi ulang status low stock item tersebut
9. THE Inventory_Manager SHALL mencatat setiap pergerakan stok dengan atribut: tanggal, jenis transaksi, jumlah, stok sebelum, stok sesudah, dan referensi transaksi

### Requirement 3: Manajemen Menu

**User Story:** Sebagai Head_Manager, saya ingin mengelola daftar menu yang ditampilkan kepada pelanggan, agar informasi produk selalu akurat dan terkini.

#### Acceptance Criteria

1. THE System SHALL menyimpan data menu dengan atribut: nama, deskripsi, harga jual, kategori, gambar, dan status ketersediaan
2. WHEN Head_Manager mengubah status ketersediaan menu menjadi tidak tersedia, THE System SHALL menyembunyikan menu tersebut dari tampilan Pelanggan
3. WHEN Head_Manager memperbarui harga menu, THE System SHALL menyimpan harga baru dan menggunakan harga baru untuk semua transaksi berikutnya
4. THE System SHALL menampilkan daftar menu yang tersedia kepada Pelanggan tanpa memerlukan autentikasi
5. WHEN Pelanggan memfilter menu berdasarkan kategori, THE System SHALL mengembalikan hanya item menu yang sesuai dengan kategori yang dipilih

### Requirement 4: Sistem Pemesanan Hybrid

**User Story:** Sebagai Pelanggan, saya ingin memesan menu melalui website secara mandiri, agar saya tidak perlu menunggu staf untuk mencatat pesanan saya.

**User Story:** Sebagai Kasir, saya ingin membuat pesanan atas nama pelanggan di tempat, agar proses pemesanan dapat dilakukan dengan cepat.

#### Acceptance Criteria

1. WHEN Pelanggan mengirimkan pesanan melalui website, THE Order_Manager SHALL membuat order baru dengan status "pending" dan mengembalikan nomor order unik
2. WHEN Kasir membuat pesanan baru melalui antarmuka POS, THE Order_Manager SHALL membuat order baru dengan status "pending" dan menandai order sebagai "kasir-order"
3. THE Order_Manager SHALL memvalidasi ketersediaan setiap item yang dipesan sebelum mengkonfirmasi order
4. IF item yang dipesan tidak tersedia, THEN THE Order_Manager SHALL mengembalikan pesan error yang menyebutkan item yang tidak tersedia
5. WHEN status order diperbarui oleh Kasir, THE Order_Manager SHALL menyimpan perubahan status dan mencatat timestamp perubahan
6. THE Order_Manager SHALL mendukung status order: pending, confirmed, preparing, ready, completed, dan cancelled
7. WHEN order dibatalkan, THE Order_Manager SHALL mencatat alasan pembatalan dan memperbarui status menjadi "cancelled"
8. THE Order_Manager SHALL menyediakan daftar order aktif yang dapat difilter berdasarkan status untuk diakses oleh Kasir

### Requirement 5: Kasir & Proses Checkout (POS)

**User Story:** Sebagai Kasir, saya ingin memproses pembayaran dan mencetak struk secara otomatis, agar transaksi tercatat dengan akurat dan stok berkurang sesuai item yang terjual.

#### Acceptance Criteria

1. WHEN Kasir memproses pembayaran untuk suatu order, THE POS SHALL memvalidasi bahwa total pembayaran yang diterima lebih besar atau sama dengan total harga order
2. WHEN pembayaran berhasil diproses, THE POS SHALL secara atomik: mengurangi stok setiap item yang terjual, mencatat transaksi penjualan, dan memperbarui status order menjadi "completed"
3. IF stok item tidak mencukupi saat checkout, THEN THE POS SHALL membatalkan proses checkout dan mengembalikan pesan error yang menyebutkan item dan jumlah stok yang tersedia
4. WHEN pembayaran berhasil diproses, THE POS SHALL menghasilkan data Struk yang berisi: nomor transaksi, tanggal/waktu, daftar item, subtotal, pajak (jika ada), total, jumlah bayar, dan kembalian
5. THE POS SHALL mendukung metode pembayaran: tunai, kartu debit/kredit, dan dompet digital (QRIS)
6. WHEN Kasir memilih metode pembayaran tunai, THE POS SHALL menghitung dan menampilkan jumlah kembalian
7. THE POS SHALL menyediakan endpoint untuk mengambil data Struk berdasarkan nomor transaksi untuk keperluan cetak ulang
8. WHEN transaksi penjualan berhasil dicatat, THE POS SHALL secara otomatis membuat entri pemasukan di Finance_Manager dengan referensi nomor transaksi

### Requirement 6: Manajemen Keuangan (Finance)

**User Story:** Sebagai Finance, saya ingin mencatat semua pemasukan dan pengeluaran harian, agar laporan keuangan bar/resto dapat disusun dengan akurat.

#### Acceptance Criteria

1. THE Finance_Manager SHALL mencatat setiap entri pemasukan dengan atribut: tanggal, jumlah, sumber (referensi transaksi POS atau manual), kategori, dan keterangan
2. THE Finance_Manager SHALL mencatat setiap entri pengeluaran dengan atribut: tanggal, jumlah, kategori, keterangan, dan bukti pengeluaran (opsional)
3. WHEN Finance menambahkan entri pengeluaran baru, THE Finance_Manager SHALL menyimpan data dan memperbarui total pengeluaran harian
4. WHEN Finance memvalidasi pemasukan dari transaksi POS, THE Finance_Manager SHALL memperbarui status entri pemasukan menjadi "validated"
5. THE Finance_Manager SHALL menghitung total pemasukan, total pengeluaran, dan laba bersih untuk periode harian, mingguan, dan bulanan
6. IF total pengeluaran melebihi total pemasukan pada suatu periode, THEN THE Finance_Manager SHALL menandai periode tersebut dengan status "rugi" pada rekap keuangan
7. THE Finance_Manager SHALL menyediakan rekap keuangan mingguan dan bulanan yang dapat diakses oleh Finance dan Head_Manager

### Requirement 7: Pelaporan (Reporting)

**User Story:** Sebagai Head_Manager atau Finance, saya ingin mengunduh laporan stok, arus barang, dan laba-rugi dalam format PDF dan Excel, agar data dapat dianalisis dan diarsipkan dengan mudah.

#### Acceptance Criteria

1. THE Report_Generator SHALL menghasilkan laporan stok yang memuat: daftar semua item, jumlah stok saat ini, nilai stok (harga beli × jumlah), dan status low stock
2. THE Report_Generator SHALL menghasilkan laporan arus barang yang memuat: riwayat semua pergerakan stok dalam rentang tanggal yang ditentukan
3. THE Report_Generator SHALL menghasilkan laporan laba-rugi yang memuat: total pemasukan, total pengeluaran, dan laba/rugi bersih untuk periode yang ditentukan
4. WHEN pengguna yang berwenang meminta laporan, THE Report_Generator SHALL menghasilkan file PDF menggunakan DomPDF dalam waktu tidak lebih dari 30 detik
5. WHEN pengguna yang berwenang meminta laporan, THE Report_Generator SHALL menghasilkan file Excel menggunakan Laravel Excel dalam waktu tidak lebih dari 30 detik
6. THE Report_Generator SHALL menerima parameter rentang tanggal (start_date dan end_date) untuk semua jenis laporan
7. IF rentang tanggal yang diberikan tidak valid (start_date lebih besar dari end_date), THEN THE Report_Generator SHALL mengembalikan pesan error yang deskriptif
8. THE Pretty_Printer SHALL memformat data laporan ke dalam template yang konsisten sebelum dirender ke PDF atau Excel
9. FOR ALL data laporan yang valid, mengekspor ke PDF kemudian mengekspor ke Excel SHALL menghasilkan data numerik yang identik (round-trip property pada nilai keuangan)

### Requirement 8: Dashboard & Analytics

**User Story:** Sebagai Head_Manager, saya ingin melihat ringkasan performa bisnis di dashboard, agar saya dapat mengambil keputusan operasional dengan cepat.

#### Acceptance Criteria

1. THE System SHALL menampilkan dashboard yang memuat: total penjualan hari ini, total transaksi hari ini, item dengan stok kritis, dan grafik penjualan 7 hari terakhir
2. WHEN Head_Manager mengakses dashboard, THE System SHALL mengembalikan data ringkasan yang diperbarui dalam waktu tidak lebih dari 5 detik
3. THE System SHALL menampilkan daftar item dengan stok di bawah Low_Stock_Threshold secara real-time di dashboard
4. WHILE Head_Manager berada di halaman dashboard, THE System SHALL memperbarui data ringkasan setiap 60 detik secara otomatis
5. THE System SHALL menyediakan data grafik penjualan harian dalam 7 hari terakhir untuk ditampilkan di dashboard

### Requirement 9: Skema Database (ERD)

**User Story:** Sebagai developer, saya ingin skema database yang efisien dan ternormalisasi, agar performa query optimal dan integritas data terjaga.

#### Acceptance Criteria

1. THE System SHALL mengimplementasikan tabel `users` dengan kolom: id, name, email, password, role (enum: pelanggan, kasir, finance, head_manager), is_active, timestamps
2. THE System SHALL mengimplementasikan tabel `products` dengan kolom: id, sku, name, description, category_id, unit, buy_price, sell_price, stock, low_stock_threshold, is_available, image_path, timestamps
3. THE System SHALL mengimplementasikan tabel `stock_movements` dengan kolom: id, product_id (FK), type (enum: in, out), quantity, stock_before, stock_after, reference_type, reference_id, notes, created_by (FK users), timestamps
4. THE System SHALL mengimplementasikan tabel `orders` dengan kolom: id, order_number (unique), user_id (FK, nullable untuk walk-in), created_by (FK users), type (enum: self-order, kasir), status (enum: pending, confirmed, preparing, ready, completed, cancelled), notes, timestamps
5. THE System SHALL mengimplementasikan tabel `order_items` dengan kolom: id, order_id (FK), product_id (FK), quantity, unit_price, subtotal, timestamps
6. THE System SHALL mengimplementasikan tabel `transactions` dengan kolom: id, transaction_number (unique), order_id (FK), payment_method (enum: cash, card, qris), total_amount, paid_amount, change_amount, status, processed_by (FK users), timestamps
7. THE System SHALL mengimplementasikan tabel `income_entries` dengan kolom: id, transaction_id (FK, nullable), date, amount, category, description, source (enum: pos, manual), status (enum: pending, validated), created_by (FK users), timestamps
8. THE System SHALL mengimplementasikan tabel `expense_entries` dengan kolom: id, date, amount, category, description, receipt_path, created_by (FK users), timestamps
9. THE System SHALL mengimplementasikan tabel `categories` dengan kolom: id, name, type (enum: product, expense), timestamps
10. THE System SHALL mendefinisikan foreign key constraints dan index pada kolom yang sering digunakan dalam query (product_id, order_id, date, status)

### Requirement 10: API Endpoint & Struktur Proyek

**User Story:** Sebagai developer, saya ingin daftar API endpoint yang lengkap dan struktur folder yang standar, agar pengembangan frontend dan backend dapat berjalan paralel dengan kontrak yang jelas.

#### Acceptance Criteria

1. THE System SHALL mengekspos semua endpoint API dengan prefix `/api/v1/`
2. THE Auth_Service SHALL menyediakan endpoint: `POST /api/v1/auth/login`, `POST /api/v1/auth/logout`, `GET /api/v1/auth/me`
3. THE Inventory_Manager SHALL menyediakan endpoint CRUD untuk produk: `GET/POST /api/v1/products`, `GET/PUT/DELETE /api/v1/products/{id}`, dan `GET /api/v1/products/low-stock`
4. THE Inventory_Manager SHALL menyediakan endpoint untuk pergerakan stok: `POST /api/v1/stock-movements`, `GET /api/v1/stock-movements`
5. THE Order_Manager SHALL menyediakan endpoint: `GET/POST /api/v1/orders`, `GET/PUT /api/v1/orders/{id}`, `PUT /api/v1/orders/{id}/status`
6. THE POS SHALL menyediakan endpoint: `POST /api/v1/transactions/checkout`, `GET /api/v1/transactions/{id}/receipt`
7. THE Finance_Manager SHALL menyediakan endpoint: `GET/POST /api/v1/expenses`, `GET /api/v1/income`, `GET /api/v1/finance/summary`
8. THE Report_Generator SHALL menyediakan endpoint: `GET /api/v1/reports/stock`, `GET /api/v1/reports/stock-movement`, `GET /api/v1/reports/profit-loss` — masing-masing mendukung parameter `format` (pdf/excel), `start_date`, dan `end_date`
9. THE System SHALL mengorganisasi kode backend dalam struktur folder Laravel standar dengan pemisahan: `app/Http/Controllers/API/`, `app/Services/`, `app/Repositories/`, dan `app/Http/Resources/`
10. WHEN endpoint yang memerlukan autentikasi diakses tanpa token, THE System SHALL mengembalikan respons HTTP 401
