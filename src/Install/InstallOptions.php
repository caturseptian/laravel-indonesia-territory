<?php

namespace CaturSeptian\IndonesiaTerritory\Install;

use CaturSeptian\IndonesiaTerritory\Enums\Level;
use CaturSeptian\IndonesiaTerritory\Territory;

/**
 * Pilihan developer saat territory:install, sudah divalidasi.
 */
final class InstallOptions
{
    public const POSTAL = 'postal';

    public const COORDINATES = 'coordinates';

    public const ISLANDS = 'islands';

    public const CHANGES = 'changes';

    public const EXTRAS = [self::POSTAL, self::COORDINATES, self::ISLANDS, self::CHANGES];

    public const DEPENDENT = 'dependent';

    public const EJECT = 'eject';

    /**
     * @param  list<string>  $extras
     * @param  list<Level>  $boundaryLevels
     * @param  'all'|list<string>  $boundaryProvinces
     */
    public function __construct(
        public readonly Level $level,
        public readonly array $extras = [],
        public readonly array $boundaryLevels = [],
        public readonly string|array $boundaryProvinces = 'all',
        public readonly string $mode = self::DEPENDENT,
        public readonly bool $api = false,
        public readonly string $prefix = 'territory_',
    ) {
        $this->validate();
    }

    /**
     * Dari opsi CLI: daftar berupa string dipisah koma, opsi yang tidak diisi bernilai null.
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(array $input): self
    {
        $list = fn (mixed $value): array => array_values(array_unique(array_filter(
            array_map('trim', explode(',', (string) $value)),
            fn (string $item) => $item !== '',
        )));

        $level = Level::tryFrom((string) ($input['levels'] ?? 'village'));
        $boundaryLevels = array_map(fn (string $value) => Level::tryFrom($value), $list($input['boundaries'] ?? ''));
        if ($level === null || in_array(null, $boundaryLevels, true)) {
            throw new InvalidInstallOptions('Level tidak dikenal. Pilihan: province, regency, district, village.');
        }

        $provinces = $list($input['boundary_provinces'] ?? '');

        return new self(
            level: $level,
            extras: $list($input['with'] ?? ''),
            boundaryLevels: $boundaryLevels,
            boundaryProvinces: $provinces === [] || $provinces === ['all'] ? 'all' : $provinces,
            mode: (string) ($input['mode'] ?? self::DEPENDENT),
            api: (bool) ($input['api'] ?? false),
            prefix: (string) ($input['prefix'] ?? 'territory_'),
        );
    }

    public function has(string $extra): bool
    {
        return in_array($extra, $this->extras, true);
    }

    /**
     * @return list<Level>
     */
    public function levels(): array
    {
        return $this->level->withAncestors();
    }

    /**
     * Nama tabel tanpa prefix, dalam urutan pembuatan.
     *
     * @return list<string>
     */
    public function tables(): array
    {
        $tables = array_map(fn (Level $level) => $level->table(), $this->levels());

        if ($this->has(self::ISLANDS)) {
            $tables[] = 'islands';
        }
        if ($this->has(self::CHANGES)) {
            $tables[] = 'code_changes';
        }
        if ($this->boundaryLevels !== []) {
            $tables[] = 'boundaries';
        }

        return $tables;
    }

    private function validate(): void
    {
        $errors = [];

        if (($unknown = array_diff($this->extras, self::EXTRAS)) !== []) {
            $errors[] = 'Data tambahan tidak dikenal: '.implode(', ', $unknown).'. Pilihan: '.implode(', ', self::EXTRAS).'.';
        }
        if ($this->has(self::POSTAL) && $this->level !== Level::Village) {
            $errors[] = 'Kode pos (postal) membutuhkan level village.';
        }
        if ($this->has(self::ISLANDS) && $this->level->depth() < Level::Regency->depth()) {
            $errors[] = 'Pulau (islands) membutuhkan level regency atau lebih dalam.';
        }
        foreach ($this->boundaryLevels as $boundary) {
            if ($boundary->depth() > $this->level->depth()) {
                $errors[] = "Boundary {$boundary->value} membutuhkan level {$boundary->value} ikut di-install.";
            }
        }
        foreach (is_array($this->boundaryProvinces) ? $this->boundaryProvinces : [] as $code) {
            if (! Territory::isValid($code, Level::Province)) {
                $errors[] = "Kode provinsi tidak valid: {$code}.";
            }
        }
        if (! in_array($this->mode, [self::DEPENDENT, self::EJECT], true)) {
            $errors[] = "Mode tidak dikenal: {$this->mode}. Pilihan: dependent, eject.";
        }
        if (preg_match('/^[a-z0-9_]*$/', $this->prefix) !== 1) {
            $errors[] = 'Prefix tabel hanya boleh huruf kecil, angka, dan garis bawah.';
        }

        if ($errors !== []) {
            throw new InvalidInstallOptions(implode(PHP_EOL, $errors));
        }
    }
}
