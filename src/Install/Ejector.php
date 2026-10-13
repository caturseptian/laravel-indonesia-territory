<?php

namespace CaturSeptian\IndonesiaTerritory\Install;

use CaturSeptian\IndonesiaTerritory\Data\DataRepository;
use CaturSeptian\IndonesiaTerritory\Enums\Level;
use Illuminate\Support\Facades\File;

/**
 * Menyalin kode dan data package ke aplikasi dengan namespace App\, supaya package boleh dihapus.
 */
final class Ejector
{
    private const NAMESPACES = [
        'CaturSeptian\IndonesiaTerritory\Models' => 'App\Models\Territory',
        'CaturSeptian\IndonesiaTerritory\Enums' => 'App\Enums\Territory',
        'CaturSeptian\IndonesiaTerritory\Seeding' => 'Database\Seeders',
        'CaturSeptian\IndonesiaTerritory\Http\Controllers' => 'App\Http\Controllers\Territory',
        'CaturSeptian\IndonesiaTerritory\Http\Resources' => 'App\Http\Resources\Territory',
    ];

    public function __construct(private readonly DataRepository $data) {}

    /**
     * @return array<string, string> berkas sumber => berkas tujuan
     */
    public function targets(InstallOptions $options): array
    {
        $source = dirname(__DIR__);
        $targets = [];

        foreach (glob($source.'/Models/*.php') as $file) {
            $targets[$file] = app_path('Models/Territory/'.basename($file));
        }
        foreach (glob($source.'/Enums/*.php') as $file) {
            $targets[$file] = app_path('Enums/Territory/'.basename($file));
        }
        $targets[$source.'/Seeding/TerritorySeeder.php'] = database_path('seeders/TerritorySeeder.php');

        if ($options->api) {
            foreach (glob($source.'/Http/Controllers/*.php') as $file) {
                $targets[$file] = app_path('Http/Controllers/Territory/'.basename($file));
            }
            foreach (glob($source.'/Http/Resources/*.php') as $file) {
                $targets[$file] = app_path('Http/Resources/Territory/'.basename($file));
            }
            $targets[dirname($source).'/routes/api.php'] = base_path('routes/territory.php');
        }

        $names = array_map(fn (Level $level) => $level->table(), $options->levels());
        if ($options->has(InstallOptions::ISLANDS)) {
            $names[] = 'islands';
        }
        if ($options->has(InstallOptions::CHANGES)) {
            $names[] = 'code_changes';
        }
        foreach ([...array_map(fn (string $name) => "{$name}.csv.gz", $names), 'manifest.json'] as $file) {
            $targets[$this->data->path($file)] = database_path("data/territory/{$file}");
        }

        return $targets;
    }

    /**
     * @return list<string>
     */
    public function eject(InstallOptions $options): array
    {
        $written = [];
        foreach ($this->targets($options) as $from => $to) {
            File::ensureDirectoryExists(dirname($to));

            str_ends_with($from, '.php')
                ? file_put_contents($to, $this->transform(file_get_contents($from), $options->prefix))
                : copy($from, $to);

            $written[] = $to;
        }

        return $written;
    }

    /**
     * Ganti namespace package dengan namespace aplikasi dan tulis nama tabel secara literal.
     */
    public function transform(string $code, string $prefix): string
    {
        $code = strtr($code, self::NAMESPACES);
        $code = preg_replace("/Territory::table\\('(\\w+)'\\)/", "'{$prefix}\$1'", $code);

        return preg_replace('/^use CaturSeptian\\\\IndonesiaTerritory\\\\Territory;\\n/m', '', $code);
    }
}
