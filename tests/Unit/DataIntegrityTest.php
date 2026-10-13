<?php

namespace CaturSeptian\IndonesiaTerritory\Tests\Unit;

use CaturSeptian\IndonesiaTerritory\Data\DataRepository;
use CaturSeptian\IndonesiaTerritory\Enums\Level;
use CaturSeptian\IndonesiaTerritory\Territory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DataIntegrityTest extends TestCase
{
    /**
     * @var array<string, list<array<string, string>>>
     */
    private static array $rows = [];

    /**
     * @return list<array<string, string>>
     */
    private static function rows(string $name): array
    {
        return self::$rows[$name] ??= iterator_to_array(DataRepository::package()->rows($name), false);
    }

    /**
     * @return array<string, array{Level}>
     */
    public static function levels(): array
    {
        return array_combine(
            array_map(fn (Level $level) => $level->value, Level::cases()),
            array_map(fn (Level $level) => [$level], Level::cases()),
        );
    }

    public function test_checksum_dan_jumlah_baris_sesuai_manifest(): void
    {
        $repository = DataRepository::package();

        $this->assertSame([], $repository->verify());
        foreach ($repository->manifest()['files'] as $name => $file) {
            $this->assertCount($file['rows'], self::rows($name), $name);
        }
    }

    public function test_jumlah_sesuai_rekapitulasi_kepmendagri(): void
    {
        $this->assertCount(38, self::rows('provinces'));
        $this->assertCount(514, self::rows('regencies'));
        $this->assertCount(7285, self::rows('districts'));
        $this->assertCount(83762, self::rows('villages'));
    }

    #[DataProvider('levels')]
    public function test_kode_valid_dan_unik(Level $level): void
    {
        $codes = array_column(self::rows($level->table()), 'code');

        $this->assertSame(count($codes), count(array_unique($codes)));
        foreach ($codes as $code) {
            $this->assertTrue(Territory::isValid($code, $level), "Kode {$level->value} tidak valid: {$code}");
        }
    }

    #[DataProvider('levels')]
    public function test_setiap_wilayah_punya_induk(Level $level): void
    {
        if ($level->parent() === null) {
            $this->addToAssertionCount(1);

            return;
        }

        $parents = array_flip(array_column(self::rows($level->parent()->table()), 'code'));
        foreach (self::rows($level->table()) as $row) {
            $parent = $row[$level->parentColumn()];
            $this->assertArrayHasKey($parent, $parents, $row['code']);
            $this->assertStringStartsWith($parent.'.', $row['code']);
        }
    }

    public function test_jenis_mengikuti_aturan_kode(): void
    {
        foreach (self::rows('regencies') as $row) {
            $expected = (int) substr($row['code'], 3) >= 71 ? 'kota' : 'kabupaten';
            $this->assertSame($expected, $row['type'], $row['code']);
        }

        $types = ['1' => 'kelurahan', '2' => 'desa', '3' => 'desa_adat'];
        foreach (self::rows('villages') as $row) {
            $this->assertSame($types[$row['code'][9]], $row['type'], $row['code']);
        }
    }

    public function test_kode_pos_lima_digit(): void
    {
        $filled = 0;
        foreach (self::rows('villages') as $row) {
            if ($row['postal_code'] !== '') {
                $this->assertMatchesRegularExpression('/^\d{5}$/', $row['postal_code'], $row['code']);
                $filled++;
            }
            if ($row['code'] === '31.71.01.1001') {
                $this->assertSame('Gambir', $row['name']);
                $this->assertSame('10110', $row['postal_code']);
            }
        }

        $this->assertSame(72329, $filled);
    }

    public function test_koordinat_berada_di_wilayah_indonesia(): void
    {
        foreach (['provinces', 'regencies', 'districts', 'villages', 'islands'] as $name) {
            foreach (self::rows($name) as $row) {
                if ($row['latitude'] === '') {
                    continue;
                }
                $this->assertGreaterThan(-11.5, (float) $row['latitude'], $row['code']);
                $this->assertLessThan(6.5, (float) $row['latitude'], $row['code']);
                $this->assertGreaterThan(94.5, (float) $row['longitude'], $row['code']);
                $this->assertLessThan(141.5, (float) $row['longitude'], $row['code']);
            }
        }
    }

    public function test_pulau_milik_kabupaten_kota_yang_ada(): void
    {
        $provinces = array_flip(array_column(self::rows('provinces'), 'code'));
        $regencies = array_flip(array_column(self::rows('regencies'), 'code'));

        $this->assertCount(17374, self::rows('islands'));
        foreach (self::rows('islands') as $row) {
            $this->assertMatchesRegularExpression('/^\d{2}\.\d{2}\.4\d{4}$/', $row['code']);
            $this->assertArrayHasKey($row['province_code'], $provinces, $row['code']);
            // Pulau berkode NN.00.4xxxx langsung di bawah provinsi, tanpa kabupaten/kota.
            if (substr($row['code'], 3, 2) === '00') {
                $this->assertSame('', $row['regency_code'], $row['code']);
            } else {
                $this->assertArrayHasKey($row['regency_code'], $regencies, $row['code']);
            }
            // Status berpenghuni kosong bila tidak tercantum di sumber.
            $this->assertContains($row['inhabited'], ['0', '1', ''], $row['code']);
        }
    }

    public function test_riwayat_perubahan_berbentuk_benar(): void
    {
        $levels = array_map(fn (Level $level) => $level->value, Level::cases());

        $this->assertNotEmpty(self::rows('code_changes'));
        foreach (self::rows('code_changes') as $row) {
            $this->assertContains($row['change'], ['added', 'removed', 'renamed']);
            $this->assertContains($row['level'], $levels);
            $this->assertNotSame('', $row['from_document']);
            $this->assertNotSame('', $row['to_document']);
        }
    }

    public function test_manifest_boundary_mencakup_setiap_provinsi(): void
    {
        $paths = array_column(DataRepository::package()->manifest()['boundaries'], null, 'path');

        $this->assertArrayHasKey('provinces.geojson', $paths);
        $this->assertArrayHasKey('regencies.geojson', $paths);
        foreach (self::rows('provinces') as $province) {
            $this->assertArrayHasKey("districts-{$province['code']}.geojson", $paths);
            $this->assertArrayHasKey("villages-{$province['code']}.geojson", $paths);
        }
        foreach ($paths as $entry) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $entry['sha256']);
            $this->assertGreaterThan(0, $entry['bytes']);
        }
    }
}
