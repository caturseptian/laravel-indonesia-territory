# Laravel Indonesian Territory: Kode dan Data Wilayah Indonesia

[![tests](https://github.com/caturseptian/laravel-indonesian-territory/actions/workflows/tests.yml/badge.svg)](https://github.com/caturseptian/laravel-indonesian-territory/actions/workflows/tests.yml)
![PHP](https://img.shields.io/badge/PHP-8.4%20|%208.5-777bb4)
![Laravel](https://img.shields.io/badge/Laravel-13-ff2d20)
![License](https://img.shields.io/badge/license-MIT-blue)
[![Ko-fi](https://img.shields.io/badge/Ko--fi-dukung-ff5e5b?logo=ko-fi&logoColor=white)](https://ko-fi.com/caturseptian)
[![Trakteer](https://img.shields.io/badge/Trakteer-dukung-c0392b)](https://trakteer.id/caturseptian)

Proyek Laravel ini berisi **kode wilayah administrasi pemerintahan Indonesia** (kodifikasi wilayah).
Isinya provinsi, kabupaten/kota, kecamatan, kelurahan/desa dan kode pos, lengkap dengan migration,
seeder, model Eloquent dan REST API JSON. Datanya mengikuti
**Kepmendagri Nomor 300.2.2-2138 Tahun 2025** dan perubahannya, 300.2.2-2430 Tahun 2025 (berlaku 23 Juni 2025).

| Tingkat | Jumlah | Format kode | Contoh |
|---|---:|---|---|
| Provinsi | 38 | `NN` | `31` Daerah Khusus Ibukota Jakarta |
| Kabupaten/kota | 514 (416 kabupaten, 98 kota) | `NN.NN` | `31.71` Kota Administrasi Jakarta Pusat |
| Kecamatan/distrik | 7.285 | `NN.NN.NN` | `31.71.01` Gambir |
| Kelurahan/desa | 83.762 (8.496 kelurahan, 75.252 desa, 14 desa adat) | `NN.NN.NN.NNNN` | `31.71.01.1001` Gambir, kode pos 10110 |

Kode pos tersedia untuk 72.329 kelurahan/desa. Sisanya `null` karena tidak ada kecocokan pasti di data Pos Indonesia.

> Proyek ini independen dan tidak berafiliasi dengan Kementerian Dalam Negeri. Data diolah dari dokumen
> regulasi yang terbit untuk publik. Untuk keperluan resmi, rujuk dokumen aslinya.

## Tanpa instalasi: Indonesia Region API

Data yang sama tersedia online di **[indonesia-region.caturseptian.site](https://indonesia-region.caturseptian.site)**:
gratis, tanpa API key dan tanpa server sendiri. Cocok kalau Anda hanya butuh datanya, atau memakai bahasa selain PHP.

| Halaman | Isi |
|---|---|
| [Dokumentasi API](https://indonesia-region.caturseptian.site/id/docs) | daftar endpoint, format kode, contoh klien Go, PHP, Java, TypeScript |
| [API Explorer](https://indonesia-region.caturseptian.site/id/explorer) | telusuri provinsi sampai kelurahan/desa langsung di browser |
| [Peta](https://indonesia-region.caturseptian.site/id/map) | batas wilayah BIG sampai tingkat desa dan 17.374 pulau bernama |
| [Unduhan](https://indonesia-region.caturseptian.site/api/v1/export/indonesia-regions.csv) | seluruh data dalam [CSV](https://indonesia-region.caturseptian.site/api/v1/export/indonesia-regions.csv), [JSON](https://indonesia-region.caturseptian.site/api/v1/export/indonesia-regions.json), [SQL](https://indonesia-region.caturseptian.site/api/v1/export/indonesia-regions.sql), [XLSX](https://indonesia-region.caturseptian.site/api/v1/export/indonesia-regions.xlsx), [XML](https://indonesia-region.caturseptian.site/api/v1/export/indonesia-regions.xml), [PDF](https://indonesia-region.caturseptian.site/api/v1/export/indonesia-regions.pdf) |

```bash
curl https://indonesia-region.caturseptian.site/api/v1/provinces.json
curl https://indonesia-region.caturseptian.site/api/v1/districts/31.71.01/villages.json
```

Selain data di repositori ini, situs tersebut juga menyediakan koordinat, poligon batas wilayah, data pulau
dan riwayat perubahan kode sejak 2017.

## Isi repositori

- `database/data/*.csv`: data wilayah dalam CSV biasa. Bisa dipakai tanpa Laravel, misalnya untuk impor ke Excel, Python atau database lain.
- `database/migrations/…_create_territory_tables.php`: tabel `provinces`, `regencies`, `districts`, `villages`. Kolom `code` resmi menjadi primary key, dan antartabel terhubung dengan foreign key.
- `database/seeders/TerritorySeeder.php`: mengisi seluruh 91.599 baris dalam waktu kurang dari satu detik di SQLite.
- `app/Models`: `Province`, `Regency`, `District`, `Village` beserta relasinya, plus enum `RegencyType` dan `VillageType`.
- `routes/api.php`: REST API hanya-baca.
- `php artisan territory:sync`: mengunduh data terbaru dari Indonesia Region API.

## Kebutuhan

- PHP 8.4 atau 8.5 (disarankan 8.5) dengan ekstensi `pdo_sqlite`, `pdo_mysql` atau `pdo_pgsql`
- Composer 2
- Laravel 13

## Instalasi

```bash
git clone https://github.com/caturseptian/laravel-indonesian-territory.git
cd laravel-indonesian-territory
composer setup      # install, .env, APP_KEY, database SQLite, migrate --seed
php artisan serve
```

Untuk MySQL/MariaDB atau PostgreSQL, ubah `DB_CONNECTION` dan kredensial database di `.env`, lalu jalankan:

```bash
php artisan migrate --seed
```

### Memakai data di proyek Laravel lain

Salin berkas berikut ke proyek Anda:
`database/data/`, migration `create_territory_tables`, `TerritorySeeder`, `app/Models/{Province,Regency,District,Village}.php`
dan `app/Enums/`. Setelah itu jalankan:

```bash
php artisan migrate
php artisan db:seed --class=TerritorySeeder
```

## Contoh penggunaan

```php
use App\Models\District;
use App\Models\Province;
use App\Models\Village;

Province::find('32')->regencies;                    // kabupaten/kota di Jawa Barat
District::find('31.71.01')->villages;               // kelurahan di Kecamatan Gambir
Village::where('postal_code', '10110')->get();      // wilayah dengan kode pos 10110
Village::find('31.71.01.1001')->district->regency->province->name;
// "Daerah Khusus Ibukota Jakarta"
```

Kode selalu berupa **string**. Pertahankan titik dan angka nol di depannya: `32.04` adalah Kabupaten Bandung, sedangkan `32.73` adalah Kota Bandung.

## REST API

| Endpoint | Hasil |
|---|---|
| `GET /api/provinces` | semua provinsi |
| `GET /api/provinces/{kode}` | satu provinsi beserta jumlah kabupaten/kota |
| `GET /api/provinces/{kode}/regencies` | kabupaten/kota di provinsi tersebut |
| `GET /api/regencies/{kode}` | satu kabupaten/kota beserta provinsinya |
| `GET /api/regencies/{kode}/districts` | kecamatan di kabupaten/kota tersebut |
| `GET /api/districts/{kode}` | satu kecamatan beserta induknya |
| `GET /api/districts/{kode}/villages` | kelurahan/desa di kecamatan tersebut |
| `GET /api/villages/{kode}` | satu kelurahan/desa beserta seluruh induknya |
| `GET /api/villages?q={nama}` | cari kelurahan/desa berdasarkan nama (minimal 3 huruf, 50 per halaman) |
| `GET /api/villages?postal_code={kode_pos}` | cari kelurahan/desa berdasarkan kode pos |

Kode yang tidak dikenal menghasilkan HTTP 404.

```bash
curl http://localhost:8000/api/villages/31.71.01.1001
```

```json
{
  "data": {
    "code": "31.71.01.1001",
    "name": "Gambir",
    "type": "kelurahan",
    "postal_code": "10110",
    "district_code": "31.71.01",
    "district": {
      "code": "31.71.01",
      "name": "Gambir",
      "regency_code": "31.71",
      "regency": {
        "code": "31.71",
        "name": "Kota Administrasi Jakarta Pusat",
        "type": "kota",
        "province_code": "31",
        "province": { "code": "31", "name": "Daerah Khusus Ibukota Jakarta" }
      }
    }
  }
}
```

## Sumber data

| Dokumen | Dipakai untuk |
|---|---|
| Kepmendagri 300.2.2-2138 Tahun 2025 | tabel kode dan nama wilayah yang berlaku saat ini |
| Kepmendagri 300.2.2-2430 Tahun 2025 | perubahan (hanya data pulau, tidak mengubah kode atau nama wilayah) |
| Permendagri 58 Tahun 2021 | aturan format kode |
| Pos Indonesia | kode pos, dicocokkan per nama kelurahan/desa di dalam kecamatannya |

Data diekstrak dan divalidasi oleh proyek [Indonesia Region API](https://indonesia-region.caturseptian.site).
Jumlah wilayah sama dengan rekapitulasi Lampiran A di Kepmendagri. Jumlah kecamatan dan kelurahan/desa
yang tercetak di setiap baris kabupaten/kota juga sama dengan jumlah baris di bawahnya. Nama wilayah
ditulis persis seperti di dokumen. Pengujian di `tests/Unit/TerritoryDataTest.php` memeriksa format kode,
keunikan kode, induk setiap wilayah dan kesesuaian jenis wilayah dengan kodenya.

Untuk memperbarui data ke versi terbaru dari [Indonesia Region API](https://indonesia-region.caturseptian.site):

```bash
php artisan territory:sync
php artisan migrate:fresh --seed
```

## Pengujian

```bash
composer test
```

## Dukung proyek ini

Repositori ini gratis. Kalau membantu pekerjaanmu, kamu bisa ikut menanggung biaya server dan pembaruan data:
[Trakteer](https://trakteer.id/caturseptian) · [Saweria](https://saweria.co/forcaturseptian) ·
[Ko-fi](https://ko-fi.com/caturseptian) · [PayPal](https://paypal.me/ccseptian).

## English

**Laravel Indonesian Territory** provides the official administrative region codes of Indonesia
(*kode wilayah*) for Laravel 13 on PHP 8.4 or newer. It covers 38 provinces, 514 regencies and cities,
7,285 districts and 83,762 villages, plus postal codes, following the Ministry of Home Affairs decree
Kepmendagri 300.2.2-2138/2025. The repository ships plain CSV files, a migration, a fast seeder,
Eloquent models and a read-only JSON REST API. Codes are strings in the official dotted format, for example
`31.71.01.1001`. Install with `composer setup`, then call `GET /api/provinces`.
The same data, plus coordinates, boundaries, islands and downloads, is available without installation
at [indonesia-region.caturseptian.site](https://indonesia-region.caturseptian.site) (English docs at `/docs`).
If it helps your work, you can support it on [Ko-fi](https://ko-fi.com/caturseptian) or [PayPal](https://paypal.me/ccseptian).

## Lisensi

Kode program dirilis dengan [lisensi MIT](LICENSE). Data wilayah berasal dari dokumen pemerintah yang terbit untuk publik.

**Kata kunci:** kode wilayah Indonesia, kodifikasi wilayah, data wilayah Indonesia, kode wilayah Kemendagri 2025,
daftar provinsi kabupaten kota kecamatan kelurahan desa, kode pos Indonesia, seeder wilayah Laravel,
database wilayah Indonesia SQL, Indonesian administrative regions, Indonesia region codes.
