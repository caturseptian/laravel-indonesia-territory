# Changelog

## 1.0.0 - unreleased

- Repo berubah dari aplikasi Laravel menjadi package `caturseptian/laravel-indonesia-territory`. Versi aplikasi tersimpan di tag `app-final`.
- `territory:install` interaktif dan non-interaktif: level wilayah, kode pos, koordinat, pulau, riwayat perubahan kode, boundary per provinsi.
- Mode dependent dan eject (tanpa dependensi package di production).
- `territory:seed`, `territory:boundaries`, rule `TerritoryCode`, helper `Territory`, REST API opsional.
- Mendukung Laravel 12 dan 13 dengan PHP 8.2 ke atas. Laravel 11 tidak didukung karena sudah tidak menerima perbaikan keamanan.
- Data Kepmendagri 300.2.2-2138 Tahun 2025 beserta perubahannya 300.2.2-2430 Tahun 2025.
