<?php

namespace CaturSeptian\IndonesiaTerritory\Tests\Feature;

use App\Enums\Territory\VillageType;
use App\Models\Territory\Island;
use App\Models\Territory\Village;
use CaturSeptian\IndonesiaTerritory\Data\DataRepository;
use CaturSeptian\IndonesiaTerritory\Tests\TestCase;
use Database\Seeders\TerritorySeeder;
use Illuminate\Support\Facades\File;

/**
 * Hasil eject harus berjalan tanpa kelas package: tidak ada referensi namespace package,
 * dan migration + seeder + model hasil salinan bisa dipakai langsung.
 */
class EjectEndToEndTest extends TestCase
{
    public function test_hasil_eject_berjalan_tanpa_package(): void
    {
        $this->artisan('territory:install', [
            '--no-interaction' => true,
            '--levels' => 'village',
            '--with' => 'postal,coordinates,islands,changes',
            '--mode' => 'eject',
            '--api' => true,
        ])->assertSuccessful();

        foreach (File::allFiles($this->workspace) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $this->assertStringNotContainsString('IndonesiaTerritory', $file->getContents(), $file->getPathname());
            $output = [];
            exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file->getPathname()).' 2>&1', $output, $code);
            $this->assertSame(0, $code, implode("\n", $output));
        }

        // Aplikasi uji tidak memakai autoload Composer milik aplikasi, jadi kelas hasil eject dimuat manual.
        spl_autoload_register(function (string $class) {
            $roots = [
                'App\\Models\\Territory\\' => app_path('Models/Territory/'),
                'App\\Enums\\Territory\\' => app_path('Enums/Territory/'),
                'Database\\Seeders\\' => database_path('seeders/'),
            ];
            foreach ($roots as $namespace => $directory) {
                if (str_starts_with($class, $namespace) && is_file($path = $directory.substr($class, strlen($namespace)).'.php')) {
                    require $path;
                }
            }
        });

        config(['territory' => require config_path('territory.php')]);
        (require glob(database_path('migrations/*_create_territory_tables.php'))[0])->up();
        (new TerritorySeeder)->run();

        $village = Village::find('31.71.01.1001');
        $this->assertSame('31', $village->district->regency->province->code);
        $this->assertSame(VillageType::Kelurahan, $village->type);
        $this->assertSame('10110', $village->postal_code);
        $this->assertSame(
            DataRepository::package()->manifest()['files']['villages']['rows'],
            Village::count(),
        );
        $this->assertGreaterThan(0, Island::count());
    }
}
