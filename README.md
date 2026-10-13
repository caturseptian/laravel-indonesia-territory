# Laravel Indonesia Territory

[![tests](https://github.com/caturseptian/laravel-indonesia-territory/actions/workflows/tests.yml/badge.svg)](https://github.com/caturseptian/laravel-indonesia-territory/actions/workflows/tests.yml)
[![Packagist Version](https://img.shields.io/packagist/v/caturseptian/laravel-indonesia-territory)](https://packagist.org/packages/caturseptian/laravel-indonesia-territory)
[![Packagist Downloads](https://img.shields.io/packagist/dt/caturseptian/laravel-indonesia-territory)](https://packagist.org/packages/caturseptian/laravel-indonesia-territory)
![PHP](https://img.shields.io/badge/PHP-8.2%20to%208.5-777bb4)
![Laravel](https://img.shields.io/badge/Laravel-12%20|%2013-ff2d20)
![License](https://img.shields.io/badge/license-MIT-blue)
[![Ko-fi](https://img.shields.io/badge/Ko--fi-support-ff5e5b?logo=ko-fi&logoColor=white)](https://ko-fi.com/caturseptian)
[![Trakteer](https://img.shields.io/badge/Trakteer-dukung-c0392b)](https://trakteer.id/caturseptian)

Indonesian administrative regions (*kode wilayah*, Kemendagri codes) for Laravel. Pick what you need once, during
development: provinces only, or all the way down to villages, with or without postal codes, coordinates, boundary
polygons, islands and the history of code changes. Then either keep the package as a dependency, or **eject**: copy
the models, migration, seeder and data into your app and remove the package, so production depends on nothing.

The data follows **Kepmendagri 300.2.2-2138 Tahun 2025**, as amended by 300.2.2-2430 Tahun 2025 (in force since 23 June 2025).

| Level | Count | Code format | Example |
|---|---:|---|---|
| Province (provinsi) | 38 | `NN` | `31` Daerah Khusus Ibukota Jakarta |
| Regency/city (kabupaten/kota) | 514 (416 kabupaten, 98 kota) | `NN.NN` | `31.71` Kota Administrasi Jakarta Pusat |
| District (kecamatan/distrik) | 7,285 | `NN.NN.NN` | `31.71.01` Gambir |
| Village (kelurahan/desa) | 83,762 (8,496 kelurahan, 75,252 desa, 14 desa adat) | `NN.NN.NN.NNNN` | `31.71.01.1001` Gambir |
| Island (pulau) | 17,374 | `NN.NN.4NNNN` | `31.01.40001` |

Postal codes are available for 72,329 villages; the rest are `null` because Pos Indonesia has no exact match.

> This is an independent project, not affiliated with the Ministry of Home Affairs. The data is derived from
> publicly available regulations. For official use, refer to the original documents.

## Installation

```bash
# Eject: install as a dev dependency, copy everything into the app, then remove it
composer require caturseptian/laravel-indonesia-territory --dev

# Dependent: keep the package in production
composer require caturseptian/laravel-indonesia-territory

php artisan territory:install
```

The installer asks what to install, shows a summary (tables, row counts, download size) and writes:

- `database/migrations/…_create_territory_tables.php` with only the tables and columns you picked,
- `config/territory.php` with your choices and the dataset version,
- in eject mode: models, enums, seeder (and the API controllers if enabled) under `App\…`, plus the data files in `database/data/territory/`.

It refuses to run in production unless you pass `--force`, and it refuses to overwrite an existing install unless you pass `--force`.

### Options

| Option | Values | Notes |
|---|---|---|
| `--levels=` | `province`, `regency`, `district`, `village` | the deepest level; the levels above it are always included |
| `--with=` | `postal`, `coordinates`, `islands`, `changes` | comma separated; `postal` needs `village`, `islands` needs `regency` or deeper |
| `--boundaries=` | levels, comma separated | boundary polygons to download; each must be installed |
| `--boundary-provinces=` | `all` or province codes, e.g. `31,32` | limits district and village boundaries |
| `--mode=` | `dependent`, `eject` | |
| `--api` | flag | enables the read-only REST API |
| `--prefix=` | default `territory_` | lowercase letters, digits and `_`; may be empty |
| `--migrate` | flag | runs `migrate` and seeds right away |
| `--force` | flag | overwrite an existing install, allow production |

Non-interactive, for CI or a setup script:

```bash
php artisan territory:install -n --levels=village --with=postal,coordinates \
  --boundaries=province,regency --mode=eject --migrate
```

Without options, `-n` installs down to villages with postal codes and coordinates, in dependent mode.

### Dependent or eject?

| | Eject | Dependent |
|---|---|---|
| Lives in your app | migration, seeder, models (`App\Models\Territory\…`), enums, data in `database/data/territory/` | migration and `config/territory.php` |
| After install | `composer remove caturseptian/laravel-indonesia-territory` | the package stays in `require` |
| Production needs | `php artisan migrate --seed` (no internet, no package) | the package |
| Data updates | run `territory:install --force` again during development | `composer update`, then `php artisan territory:seed --fresh` |

After ejecting, call the seeder from `database/seeders/DatabaseSeeder.php` so that `migrate --seed` fills the
territory tables in production:

```php
$this->call(TerritorySeeder::class);
```

`Territory` (code helpers) and the `TerritoryCode` rule are part of the package and are **not** ejected. If you
remove the package, validate with Laravel's own rules instead, e.g. `Rule::exists('territory_villages', 'code')`.

After ejecting with `--api`, register the copied routes in `bootstrap/app.php`:

```php
use Illuminate\Support\Facades\Route;

->withRouting(
    // ...
    then: fn () => Route::middleware(['api', 'throttle:60,1'])
        ->prefix('api/territory')
        ->group(base_path('routes/territory.php')),
)
```

## Usage

```php
use CaturSeptian\IndonesiaTerritory\Models\Province;   // after eject: App\Models\Territory\Province
use CaturSeptian\IndonesiaTerritory\Models\Village;

Province::find('32')->regencies;                           // regencies and cities in Jawa Barat
Village::find('31.71.01.1001')->district->regency->province->name;
// "Daerah Khusus Ibukota Jakarta"
Village::where('postal_code', '10110')->get();
Village::search('gambir')->get();                          // case-insensitive, % and _ are literal
Province::find('31')->boundary?->geometry;                 // GeoJSON geometry as an array
```

Codes are always **strings**. Keep the dots and leading zeros: `32.04` is Kabupaten Bandung, `32.73` is Kota Bandung.

Helpers that need no database:

```php
use CaturSeptian\IndonesiaTerritory\Enums\Level;
use CaturSeptian\IndonesiaTerritory\Territory;

Territory::level('31.71.01.1001');            // Level::Village
Territory::parents('31.71.01.1001');          // ['province' => '31', 'regency' => '31.71', 'district' => '31.71.01']
Territory::isValid('31.70', Level::Regency);  // false: 01-69 are kabupaten, 71-99 are kota
```

Validation:

```php
use CaturSeptian\IndonesiaTerritory\Rules\TerritoryCode;

$request->validate([
    'village_code' => ['required', new TerritoryCode(Level::Village, exists: true)],
]);
```

### REST API

Disabled by default. Enable it with `--api` or `'api' => ['enabled' => true]` in `config/territory.php`.
Routes are registered only for the levels you installed, under `api/territory` by default.

| Endpoint | Returns |
|---|---|
| `GET /provinces` | all provinces |
| `GET /provinces/{code}` | one province with its regency count |
| `GET /provinces/{code}/regencies` | regencies and cities of a province |
| `GET /regencies/{code}` | one regency or city with its province |
| `GET /regencies/{code}/districts` | districts of a regency or city |
| `GET /districts/{code}` | one district with its parents |
| `GET /districts/{code}/villages` | villages of a district |
| `GET /villages?search=` / `?postal_code=` | search by name (3+ characters) or postal code, 50 per page |
| `GET /villages/{code}` | one village with all its parents |

Unknown codes return 404.

## Boundaries

Boundary polygons come from Badan Informasi Geospasial (BIG), simplified, and are downloaded during install
rather than shipped with the package. Approximate sizes for the whole country:

| Level | Size |
|---|---:|
| Province | 0.4 MB |
| Regency/city | 3.8 MB |
| District | 37.7 MB |
| Village | 238.9 MB |

District and village boundaries can be limited to some provinces with `--boundary-provinces`. Every file is checked
against a SHA-256 checksum recorded in the package, so a given package version always gets exactly the same
polygons. They are stored in a `boundaries` table as GeoJSON text, which works on SQLite, MySQL and PostgreSQL.

If a download is interrupted, continue it with:

```bash
php artisan territory:boundaries
```

In dependent mode the files are stored in `storage/app/territory/boundaries`, which Laravel does not commit.
Run `php artisan territory:boundaries` on deploy, or point `boundary_path` in `config/territory.php` to a
committed directory. `territory:seed` warns when boundaries are configured but no files are found. In eject mode
the files go to `database/data/territory/boundaries` and are committed with the app.

Seeding reads the GeoJSON one feature per line, so even the largest province files (about 23 MB) fit in the
default PHP memory limit.

## Updating the data

A new Kemendagri decree is released as a new minor version of the package. In dependent mode, run
`composer update caturseptian/laravel-indonesia-territory` and then `php artisan territory:seed --fresh`.
In eject mode, require the new version during development, run `territory:install --force` with the same options
and commit the result.

`territory:seed --fresh` updates rows by code instead of emptying the tables, so your own tables that reference
territory codes with a foreign key keep their rows. Codes that no longer exist in the new data (for example after
a region is split or merged) are deleted; if your tables still reference them, the foreign key decides what
happens, so check those references before updating.

## Changing your choices

Run `territory:install --force` with the new options. The migration keeps its file name, so `migrate` does not try
to create the tables twice. With `--migrate` the installer rebuilds the territory tables right away; otherwise it
prints the command to do it:

```bash
php artisan migrate:refresh --path=database/migrations/xxxx_xx_xx_xxxxxx_create_territory_tables.php
```

This drops and recreates only the territory tables. Tables of your own that reference them with a foreign key
have to be handled first.

## Data sources

| Source | Used for |
|---|---|
| Kepmendagri 300.2.2-2138 Tahun 2025 | current region codes and names |
| Kepmendagri 300.2.2-2430 Tahun 2025 | amendment (island data only) |
| Kepmendagri 100.1.1-6117 Tahun 2022, Permendagri 137 Tahun 2017 | history of code changes |
| Permendagri 58 Tahun 2021 | code format rules |
| Badan Informasi Geospasial (BIG) | boundaries and coordinates |
| Pos Indonesia | postal codes, matched by exact village name within its district |

The data is extracted and validated by [Indonesia Region API](https://indonesia-region.caturseptian.site), which
also offers the same data without installation: an [explorer](https://indonesia-region.caturseptian.site/explorer),
a [map](https://indonesia-region.caturseptian.site/map) and downloads in CSV, JSON, SQL, XLSX, XML and PDF.
Backup address: `https://indonesia-region-api.caturseptian-dev.workers.dev`.

## Ringkasan (Bahasa Indonesia)

Package Laravel untuk kode wilayah administrasi Indonesia sesuai Kepmendagri 300.2.2-2138 Tahun 2025: provinsi,
kabupaten/kota, kecamatan, kelurahan/desa, kode pos, koordinat, batas wilayah (poligon BIG), pulau, dan riwayat
perubahan kode sejak 2017.

Pasang dengan `composer require caturseptian/laravel-indonesia-territory --dev`, lalu jalankan
`php artisan territory:install`. Installer menanyakan sampai level mana data dipasang dan data tambahan apa saja
yang diperlukan, lalu membuat migration yang hanya berisi tabel dan kolom yang dipilih.

Ada dua mode. **Eject** menyalin model, migration, seeder, dan data ke aplikasi, sehingga package bisa dihapus dan
production cukup menjalankan `php artisan migrate --seed` tanpa internet (panggil `TerritorySeeder` dari
`DatabaseSeeder`). **Dependent** membiarkan package tetap
terpasang, dan data diperbarui lewat `composer update` lalu `php artisan territory:seed --fresh`.

Kode wilayah selalu berupa string dengan titik dan angka nol di depan, misalnya `31.71.01.1001`.

## Testing

```bash
composer test
```

## Support

The package is free. If it helps your work, you can help cover the server and data updates:
[Trakteer](https://trakteer.id/caturseptian) · [Saweria](https://saweria.co/forcaturseptian) ·
[Ko-fi](https://ko-fi.com/caturseptian) · [Buy Me a Coffee](https://buymeacoffee.com/caturseptian) · [PayPal](https://paypal.me/ccseptian).

## License

The code is released under the [MIT license](LICENSE). The region data comes from publicly published government documents.
