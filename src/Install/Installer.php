<?php

namespace CaturSeptian\IndonesiaTerritory\Install;

use CaturSeptian\IndonesiaTerritory\Data\DataRepository;
use CaturSeptian\IndonesiaTerritory\Enums\Level;
use Illuminate\Support\Facades\File;

/**
 * Menulis migration, config, dan (mode eject) salinan kode serta data ke aplikasi.
 */
final class Installer
{
    public function __construct(
        private readonly MigrationWriter $migrations,
        private readonly Ejector $ejector,
        private readonly DataRepository $data,
    ) {}

    /**
     * @return list<string> berkas yang sudah ada dan akan ditimpa
     */
    public function conflicts(InstallOptions $options): array
    {
        $paths = glob(database_path('migrations/*'.MigrationWriter::SUFFIX)) ?: [];

        if (is_file(config_path('territory.php'))) {
            $paths[] = config_path('territory.php');
        }
        if ($options->mode === InstallOptions::EJECT) {
            $paths = [...$paths, ...array_values(array_filter($this->ejector->targets($options), is_file(...)))];
        }

        return $paths;
    }

    /**
     * @return list<string> berkas yang ditulis
     */
    public function install(InstallOptions $options, bool $force = false): array
    {
        $conflicts = $this->conflicts($options);
        if ($conflicts !== [] && ! $force) {
            throw new InstallationException("Berkas berikut sudah ada:\n  ".implode("\n  ", $conflicts)."\nJalankan ulang dengan --force untuk menimpanya.");
        }

        $written = [
            $this->migrations->write($options, database_path('migrations'), $force),
            $this->writeConfig($options),
        ];
        if ($options->mode === InstallOptions::EJECT) {
            $written = [...$written, ...$this->ejector->eject($options)];
        }

        config(['territory' => require config_path('territory.php')]);

        return $written;
    }

    public function writeConfig(InstallOptions $options): string
    {
        $eject = $options->mode === InstallOptions::EJECT;
        $values = [
            'mode' => $options->mode,
            'prefix' => $options->prefix,
            'level' => $options->level->value,
            'extras' => $options->extras,
            'boundary_levels' => array_map(fn (Level $level) => $level->value, $options->boundaryLevels),
            'boundary_provinces' => $options->boundaryProvinces,
            'dataset_version' => $this->data->datasetVersion(),
            'api' => $options->api,
        ];

        $replace = [
            '{{data_path}}' => $eject ? "database_path('data/territory')" : 'null',
            '{{boundary_path}}' => $eject ? "database_path('data/territory/boundaries')" : "storage_path('app/territory/boundaries')",
        ];
        foreach ($values as $key => $value) {
            $replace['{{'.$key.'}}'] = self::export($value);
        }

        $path = config_path('territory.php');
        File::ensureDirectoryExists(dirname($path));
        file_put_contents($path, strtr(file_get_contents(dirname(__DIR__, 2).'/stubs/config.php.stub'), $replace));

        return $path;
    }

    private static function export(mixed $value): string
    {
        return match (true) {
            is_array($value) => '['.implode(', ', array_map(self::export(...), $value)).']',
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            default => var_export($value, true),
        };
    }
}
