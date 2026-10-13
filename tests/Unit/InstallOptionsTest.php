<?php

namespace CaturSeptian\IndonesiaTerritory\Tests\Unit;

use CaturSeptian\IndonesiaTerritory\Enums\Level;
use CaturSeptian\IndonesiaTerritory\Install\InstallOptions;
use CaturSeptian\IndonesiaTerritory\Install\InvalidInstallOptions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class InstallOptionsTest extends TestCase
{
    public function test_default_dari_input_kosong(): void
    {
        $options = InstallOptions::fromInput([]);

        $this->assertSame(Level::Village, $options->level);
        $this->assertSame([], $options->extras);
        $this->assertSame([], $options->boundaryLevels);
        $this->assertSame('all', $options->boundaryProvinces);
        $this->assertSame(InstallOptions::DEPENDENT, $options->mode);
        $this->assertFalse($options->api);
        $this->assertSame('territory_', $options->prefix);
    }

    public function test_membaca_daftar_dipisah_koma(): void
    {
        $options = InstallOptions::fromInput([
            'levels' => 'village',
            'with' => 'postal, coordinates,postal',
            'boundaries' => 'province,district',
            'boundary_provinces' => '31,32',
            'mode' => 'eject',
            'api' => true,
            'prefix' => '',
        ]);

        $this->assertSame(['postal', 'coordinates'], $options->extras);
        $this->assertSame([Level::Province, Level::District], $options->boundaryLevels);
        $this->assertSame(['31', '32'], $options->boundaryProvinces);
        $this->assertSame('', $options->prefix);
        $this->assertTrue($options->api);
    }

    public function test_daftar_tabel_sesuai_pilihan(): void
    {
        $this->assertSame(['provinces', 'regencies'], (new InstallOptions(Level::Regency))->tables());
        $this->assertSame(
            ['provinces', 'regencies', 'districts', 'villages', 'islands', 'code_changes', 'boundaries'],
            (new InstallOptions(Level::Village, InstallOptions::EXTRAS, [Level::Province]))->tables(),
        );
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'level tidak dikenal' => [['levels' => 'kota'], 'Level tidak dikenal'],
            'data tambahan tidak dikenal' => [['with' => 'cuaca'], 'Data tambahan tidak dikenal: cuaca'],
            'kode pos tanpa desa' => [['levels' => 'district', 'with' => 'postal'], 'membutuhkan level village'],
            'pulau tanpa kab/kota' => [['levels' => 'province', 'with' => 'islands'], 'membutuhkan level regency'],
            'boundary lebih dalam dari level' => [['levels' => 'regency', 'boundaries' => 'village'], 'Boundary village'],
            'kode provinsi tidak valid' => [['boundaries' => 'village', 'boundary_provinces' => '3x'], 'Kode provinsi tidak valid: 3x'],
            'mode tidak dikenal' => [['mode' => 'hybrid'], 'Mode tidak dikenal'],
            'prefix tidak aman' => [['prefix' => 'wil-ayah'], 'Prefix tabel'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    #[DataProvider('invalidInputs')]
    public function test_menolak_kombinasi_tidak_konsisten(array $input, string $message): void
    {
        $this->expectException(InvalidInstallOptions::class);
        $this->expectExceptionMessage($message);

        InstallOptions::fromInput($input);
    }
}
