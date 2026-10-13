<?php

namespace CaturSeptian\IndonesiaTerritory\Tests\Unit;

use CaturSeptian\IndonesiaTerritory\Enums\Level;
use CaturSeptian\IndonesiaTerritory\Territory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TerritoryTest extends TestCase
{
    /**
     * @return array<string, array{string, Level}>
     */
    public static function validCodes(): array
    {
        return [
            'provinsi' => ['31', Level::Province],
            'kabupaten' => ['32.04', Level::Regency],
            'kota' => ['31.71', Level::Regency],
            'kecamatan' => ['31.71.01', Level::District],
            'kelurahan' => ['31.71.01.1001', Level::Village],
            'desa' => ['11.01.01.2001', Level::Village],
            'desa adat' => ['51.03.01.3001', Level::Village],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidCodes(): array
    {
        return [
            'kosong' => [''],
            'satu digit' => ['3'],
            'provinsi 00' => ['00'],
            'huruf' => ['ab'],
            'kab/kota 70' => ['31.70'],
            'kab/kota 00' => ['31.00'],
            'kab/kota satu digit' => ['31.7'],
            'kecamatan 00' => ['31.71.00'],
            'desa 0xxx' => ['31.71.01.0001'],
            'desa 4xxx' => ['31.71.01.4001'],
            'desa x000' => ['31.71.01.1000'],
            'terlalu panjang' => ['31.71.01.1001.1'],
            'newline di akhir' => ["31.71\n"],
        ];
    }

    #[DataProvider('validCodes')]
    public function test_mengenali_level_kode_valid(string $code, Level $level): void
    {
        $this->assertSame($level, Territory::level($code));
        $this->assertTrue(Territory::isValid($code));
        $this->assertTrue(Territory::isValid($code, $level));
    }

    #[DataProvider('invalidCodes')]
    public function test_menolak_kode_tidak_valid(string $code): void
    {
        $this->assertNull(Territory::level($code));
        $this->assertFalse(Territory::isValid($code));
    }

    public function test_level_harus_sama_bila_diminta(): void
    {
        $this->assertFalse(Territory::isValid('31.71', Level::Village));
    }

    public function test_kode_induk_dari_provinsi(): void
    {
        $this->assertSame(
            ['province' => '31', 'regency' => '31.71', 'district' => '31.71.01'],
            Territory::parents('31.71.01.1001'),
        );
        $this->assertSame([], Territory::parents('31'));
    }

    public function test_kode_induk_menolak_kode_tidak_valid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Territory::parents('31.70');
    }

    public function test_level_beserta_induknya(): void
    {
        $this->assertSame([Level::Province, Level::Regency, Level::District], Level::District->withAncestors());
        $this->assertSame([Level::Province], Level::Province->withAncestors());
        $this->assertSame('villages', Level::Village->table());
        $this->assertSame('district_code', Level::Village->parentColumn());
        $this->assertSame(Level::District, Level::Village->parent());
        $this->assertNull(Level::Province->parent());
    }
}
