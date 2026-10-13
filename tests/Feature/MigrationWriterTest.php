<?php

namespace CaturSeptian\IndonesiaTerritory\Tests\Feature;

use CaturSeptian\IndonesiaTerritory\Enums\Level;
use CaturSeptian\IndonesiaTerritory\Install\InstallationException;
use CaturSeptian\IndonesiaTerritory\Install\InstallOptions;
use CaturSeptian\IndonesiaTerritory\Install\MigrationWriter;
use CaturSeptian\IndonesiaTerritory\Tests\TestCase;
use Illuminate\Support\Facades\Schema;

class MigrationWriterTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function columns(string $table): array
    {
        $columns = Schema::getColumnListing($table);
        sort($columns);

        return $columns;
    }

    public function test_provinsi_saja(): void
    {
        $this->migrate(new InstallOptions(Level::Province));

        $this->assertSame(['code', 'name'], $this->columns('territory_provinces'));
        $this->assertFalse(Schema::hasTable('territory_regencies'));
    }

    public function test_desa_dengan_semua_data_tambahan_dan_boundary(): void
    {
        $this->migrate(new InstallOptions(Level::Village, InstallOptions::EXTRAS, [Level::Province, Level::Village]));

        foreach (['provinces', 'regencies', 'districts', 'villages', 'islands', 'code_changes', 'boundaries'] as $table) {
            $this->assertTrue(Schema::hasTable("territory_{$table}"), $table);
        }
        $this->assertTrue(Schema::hasColumns('territory_villages', ['postal_code', 'latitude', 'longitude', 'spatial_status', 'bbox']));
        $this->assertTrue(Schema::hasColumns('territory_provinces', ['latitude', 'bbox']));
        $this->assertSame(['code', 'geometry', 'level'], $this->columns('territory_boundaries'));
    }

    public function test_data_tambahan_yang_tidak_dipilih_tidak_dibuat(): void
    {
        $this->migrate(new InstallOptions(Level::Village));

        $this->assertSame(['code', 'district_code', 'name', 'type'], $this->columns('territory_villages'));
        $this->assertFalse(Schema::hasTable('territory_islands'));
        $this->assertFalse(Schema::hasTable('territory_code_changes'));
        $this->assertFalse(Schema::hasTable('territory_boundaries'));
    }

    public function test_prefix_khusus_dan_kosong(): void
    {
        $this->migrate(new InstallOptions(Level::Regency, prefix: 'wil_'));
        $this->assertTrue(Schema::hasTable('wil_regencies'));

        Schema::dropAllTables();

        $this->migrate(new InstallOptions(Level::Regency, prefix: ''));
        $this->assertTrue(Schema::hasTable('provinces'));
        $this->assertTrue(Schema::hasTable('regencies'));
    }

    public function test_menolak_menimpa_tanpa_force(): void
    {
        $writer = new MigrationWriter;
        $directory = $this->workspace.'/database/migrations';
        $writer->write(new InstallOptions(Level::Province), $directory);

        $this->expectException(InstallationException::class);
        $this->expectExceptionMessage('--force');

        $writer->write(new InstallOptions(Level::Province), $directory);
    }

    public function test_force_mengganti_migration_lama(): void
    {
        $writer = new MigrationWriter;
        $directory = $this->workspace.'/database/migrations';
        $writer->write(new InstallOptions(Level::Province), $directory);
        $writer->write(new InstallOptions(Level::Regency), $directory, force: true);

        $this->assertCount(1, glob($directory.'/*'.MigrationWriter::SUFFIX));
    }

    public function test_down_menghapus_semua_tabel(): void
    {
        $path = (new MigrationWriter)->write(
            new InstallOptions(Level::Village, InstallOptions::EXTRAS, [Level::Province]),
            $this->workspace.'/database/migrations',
        );
        (require $path)->up();
        (require $path)->down();

        $this->assertFalse(Schema::hasTable('territory_provinces'));
        $this->assertFalse(Schema::hasTable('territory_villages'));
        $this->assertFalse(Schema::hasTable('territory_boundaries'));
    }
}
