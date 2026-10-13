<?php

namespace CaturSeptian\IndonesiaTerritory\Tests\Feature;

use CaturSeptian\IndonesiaTerritory\Enums\Level;
use CaturSeptian\IndonesiaTerritory\Install\InstallationException;
use CaturSeptian\IndonesiaTerritory\Install\Installer;
use CaturSeptian\IndonesiaTerritory\Install\InstallOptions;
use CaturSeptian\IndonesiaTerritory\Tests\TestCase;
use Illuminate\Support\Facades\File;

class InstallerTest extends TestCase
{
    private function installer(): Installer
    {
        return $this->app->make(Installer::class);
    }

    public function test_dependent_menulis_migration_dan_config_saja(): void
    {
        $this->installer()->install(new InstallOptions(Level::District, [InstallOptions::COORDINATES]));

        $this->assertCount(1, glob(database_path('migrations/*_create_territory_tables.php')));
        $config = require config_path('territory.php');
        $this->assertSame('dependent', $config['mode']);
        $this->assertSame('district', $config['level']);
        $this->assertSame(['coordinates'], $config['extras']);
        $this->assertNull($config['data_path']);
        $this->assertSame(storage_path('app/territory/boundaries'), $config['boundary_path']);
        $this->assertDirectoryDoesNotExist(app_path('Models/Territory'));
        $this->assertSame('district', config('territory.level'));
    }

    public function test_eject_menyalin_kode_dan_data(): void
    {
        $this->installer()->install(new InstallOptions(Level::Village, [InstallOptions::ISLANDS], mode: InstallOptions::EJECT, prefix: 'wil_'));

        $village = file_get_contents(app_path('Models/Territory/Village.php'));
        $this->assertStringContainsString('namespace App\Models\Territory;', $village);
        $this->assertStringContainsString("return 'wil_villages';", $village);
        $this->assertStringContainsString('use App\Enums\Territory\VillageType;', $village);
        $this->assertFileExists(app_path('Enums/Territory/Level.php'));
        $this->assertStringContainsString('namespace Database\Seeders;', file_get_contents(database_path('seeders/TerritorySeeder.php')));
        $this->assertFileExists(database_path('data/territory/villages.csv.gz'));
        $this->assertFileExists(database_path('data/territory/islands.csv.gz'));
        $this->assertFileDoesNotExist(database_path('data/territory/code_changes.csv.gz'));
        $this->assertFileExists(database_path('data/territory/manifest.json'));
        $this->assertFileDoesNotExist(base_path('routes/territory.php'));
        $this->assertSame(database_path('data/territory'), (require config_path('territory.php'))['data_path']);

        foreach (File::allFiles($this->workspace) as $file) {
            if ($file->getExtension() === 'php') {
                $this->assertStringNotContainsString('IndonesiaTerritory', $file->getContents(), $file->getPathname());
            }
        }
    }

    public function test_eject_dengan_api_menyalin_controller_dan_route(): void
    {
        $this->installer()->install(new InstallOptions(Level::Village, mode: InstallOptions::EJECT, api: true));

        $this->assertFileExists(app_path('Http/Controllers/Territory/VillageController.php'));
        $this->assertFileExists(app_path('Http/Resources/Territory/TerritoryResource.php'));
        $this->assertStringContainsString('use App\Http\Controllers\Territory\VillageController;', file_get_contents(base_path('routes/territory.php')));
    }

    public function test_menolak_bila_berkas_sudah_ada_lalu_force_menimpa(): void
    {
        $options = new InstallOptions(Level::Province, mode: InstallOptions::EJECT);
        $this->installer()->install($options);

        try {
            $this->installer()->install($options);
            $this->fail('Seharusnya ditolak.');
        } catch (InstallationException $e) {
            $this->assertStringContainsString('config/territory.php', $e->getMessage());
            $this->assertStringContainsString('Province.php', $e->getMessage());
        }

        $written = $this->installer()->install($options, force: true);
        $this->assertContains(config_path('territory.php'), $written);
    }
}
