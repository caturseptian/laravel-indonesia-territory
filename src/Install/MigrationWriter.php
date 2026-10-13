<?php

namespace CaturSeptian\IndonesiaTerritory\Install;

use Illuminate\Support\Facades\File;

/**
 * Menulis migration wilayah yang hanya berisi tabel dan kolom pilihan developer.
 * Nama tabel ditulis literal agar migration tidak bergantung pada config atau package.
 */
final class MigrationWriter
{
    public const SUFFIX = '_create_territory_tables.php';

    public function write(InstallOptions $options, string $directory, bool $force = false): string
    {
        $existing = glob($directory.'/*'.self::SUFFIX) ?: [];
        if ($existing !== [] && ! $force) {
            throw new InstallationException('Migration wilayah sudah ada: '.basename($existing[0]).'. Jalankan ulang dengan --force untuk membuatnya ulang.');
        }

        array_map(unlink(...), $existing);
        File::ensureDirectoryExists($directory);

        $path = $directory.'/'.date('Y_m_d_His').self::SUFFIX;
        file_put_contents($path, $this->render($options));

        return $path;
    }

    public function render(InstallOptions $options): string
    {
        $up = [];
        $down = [];
        foreach ($options->tables() as $table) {
            $name = $options->prefix.$table;
            $columns = array_map(fn (string $line) => '            '.$line, $this->columns($table, $options));
            $up[] = "        Schema::create('{$name}', function (Blueprint \$table) {\n".implode("\n", $columns)."\n        });";
            array_unshift($down, "        Schema::dropIfExists('{$name}');");
        }

        return implode("\n", [
            '<?php',
            '',
            'use Illuminate\Database\Migrations\Migration;',
            'use Illuminate\Database\Schema\Blueprint;',
            'use Illuminate\Support\Facades\Schema;',
            '',
            '// Dibuat oleh `php artisan territory:install`.',
            'return new class extends Migration',
            '{',
            '    public function up(): void',
            '    {',
            implode("\n\n", $up),
            '    }',
            '',
            '    public function down(): void',
            '    {',
            implode("\n", $down),
            '    }',
            '};',
            '',
        ]);
    }

    /**
     * @return list<string>
     */
    private function columns(string $table, InstallOptions $options): array
    {
        $prefix = $options->prefix;
        $coordinates = $options->has(InstallOptions::COORDINATES) ? [
            "\$table->decimal('latitude', 9, 6)->nullable();",
            "\$table->decimal('longitude', 9, 6)->nullable();",
            "\$table->string('spatial_status', 16)->nullable();",
            "\$table->json('bbox')->nullable();",
        ] : [];
        $postal = $options->has(InstallOptions::POSTAL) ? ["\$table->string('postal_code', 5)->nullable()->index();"] : [];

        return match ($table) {
            'provinces' => [
                "\$table->string('code', 2)->primary();",
                "\$table->string('name');",
                ...$coordinates,
            ],
            'regencies' => [
                "\$table->string('code', 5)->primary();",
                "\$table->string('province_code', 2);",
                "\$table->string('type', 9);",
                "\$table->string('name');",
                ...$coordinates,
                "\$table->foreign('province_code')->references('code')->on('{$prefix}provinces')->cascadeOnDelete();",
                "\$table->index('name');",
            ],
            'districts' => [
                "\$table->string('code', 8)->primary();",
                "\$table->string('regency_code', 5);",
                "\$table->string('name');",
                ...$coordinates,
                "\$table->foreign('regency_code')->references('code')->on('{$prefix}regencies')->cascadeOnDelete();",
                "\$table->index('name');",
            ],
            'villages' => [
                "\$table->string('code', 13)->primary();",
                "\$table->string('district_code', 8);",
                "\$table->string('type', 9);",
                "\$table->string('name');",
                ...$postal,
                ...$coordinates,
                "\$table->foreign('district_code')->references('code')->on('{$prefix}districts')->cascadeOnDelete();",
                "\$table->index('name');",
            ],
            'islands' => [
                "\$table->string('code', 11)->primary();",
                "\$table->string('name');",
                "\$table->string('province_code', 2);",
                "\$table->string('regency_code', 5);",
                "\$table->decimal('latitude', 9, 6)->nullable();",
                "\$table->decimal('longitude', 9, 6)->nullable();",
                "\$table->decimal('area_km2', 12, 4)->nullable();",
                "\$table->boolean('inhabited')->nullable();",
                "\$table->foreign('province_code')->references('code')->on('{$prefix}provinces')->cascadeOnDelete();",
                "\$table->foreign('regency_code')->references('code')->on('{$prefix}regencies')->cascadeOnDelete();",
                "\$table->index('name');",
            ],
            'code_changes' => [
                '$table->id();',
                "\$table->string('from_document', 64);",
                "\$table->string('to_document', 64);",
                "\$table->string('level', 8);",
                "\$table->string('change', 8);",
                "\$table->string('code', 13);",
                "\$table->string('old_name')->nullable();",
                "\$table->string('new_name')->nullable();",
                "\$table->index('code');",
            ],
            'boundaries' => [
                "\$table->string('level', 8);",
                "\$table->string('code', 13);",
                "\$table->longText('geometry');",
                "\$table->primary(['level', 'code']);",
            ],
        };
    }
}
