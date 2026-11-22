# Sewa Garasi Kendaraan

Aplikasi web sewa garasi kendaraan berbasis Laravel (Blade + Tailwind). Fiturnya:

- Registrasi penyewa dengan NIK dan foto KTP, lalu diverifikasi admin.
- Kendaraan Saya (sepeda, motor, mobil, mobil listrik).
- Garasi sepeda, mobil, dan listrik yang dibuat massal oleh admin.
- Sewa harian dan bulanan, dengan daftar garasi yang tersedia.
- Biaya charging untuk mobil listrik di garasi listrik, termasuk charging tambahan.
- Verifikasi pembayaran, perpanjangan sewa oleh admin, dashboard, dan laporan pendapatan.

## Requirement

| Komponen | Versi |
|---|---|
| PHP | 8.3 atau lebih baru (ekstensi: pdo_mysql, pdo_sqlite, mbstring, fileinfo, gd) |
| Composer | 2.x |
| Node.js / npm | Node 20+ |
| MySQL / MariaDB | MySQL 8 atau MariaDB 10.4+ |

## Instalasi

```bash
composer install
npm install
npm run build
cp .env.example .env
php artisan key:generate
```

Buat database kosong `sertifikasi_lsp`, lalu sesuaikan `DB_HOST`, `DB_PORT`, `DB_USERNAME`, dan `DB_PASSWORD`
di `.env`. Setelah itu jalankan:

```bash
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Buka http://127.0.0.1:8000.

## Akun Default

Semua akun memakai password `password`.

| Email | Role | Status |
|---|---|---|
| admin@example.com | Admin | approved |
| budi@example.com | Penyewa | approved |
| siti@example.com | Penyewa | approved |
| pending@example.com | Penyewa | pending (bisa login, belum bisa menyewa) |
| rejected@example.com | Penyewa | rejected (tidak bisa login) |

Data seeder dibuat relatif terhadap tanggal hari ini. Isinya:

- Garasi: S001–S010, C001–C010, dan E001–E005.
- Sewa harian dan bulanan, termasuk yang lintas bulan.
- Satu sewa yang sudah diperpanjang dan satu pembayaran yang ditolak.
- Mobil listrik dengan charging awal dan charging tambahan.
- Dua sewa berurutan di C003, untuk mencoba rekomendasi perpanjangan.

## Menjalankan Test

Test memakai SQLite in-memory (diatur di `phpunit.xml`), sehingga tidak menyentuh database MySQL.

```bash
php artisan test
php artisan test --filter=RentalExtensionServiceTest
vendor/bin/pint
```
