<?php

namespace CaturSeptian\IndonesiaTerritory\Enums;

/**
 * Tingkat wilayah administrasi, Permendagri 58 Tahun 2021 Pasal 4-6.
 */
enum Level: string
{
    case Province = 'province';
    case Regency = 'regency';
    case District = 'district';
    case Village = 'village';

    public function depth(): int
    {
        return match ($this) {
            self::Province => 1,
            self::Regency => 2,
            self::District => 3,
            self::Village => 4,
        };
    }

    public function table(): string
    {
        return match ($this) {
            self::Province => 'provinces',
            self::Regency => 'regencies',
            self::District => 'districts',
            self::Village => 'villages',
        };
    }

    public function parent(): ?self
    {
        return match ($this) {
            self::Province => null,
            self::Regency => self::Province,
            self::District => self::Regency,
            self::Village => self::District,
        };
    }

    public function parentColumn(): ?string
    {
        return $this->parent() === null ? null : $this->parent()->value.'_code';
    }

    public function label(): string
    {
        return match ($this) {
            self::Province => 'Provinsi',
            self::Regency => 'Kabupaten/kota',
            self::District => 'Kecamatan',
            self::Village => 'Kelurahan/desa',
        };
    }

    /**
     * Pola kode: kab/kota 01-69 kabupaten, 71-99 kota; desa 1xxx kelurahan, 2xxx desa, 3xxx desa adat.
     */
    public function pattern(): string
    {
        return match ($this) {
            self::Province => '/^(?!00)\d{2}$/',
            self::Regency => '/^\d{2}\.(0[1-9]|[1-6]\d|7[1-9]|[89]\d)$/',
            self::District => '/^\d{2}\.\d{2}\.(?!00)\d{2}$/',
            self::Village => '/^\d{2}\.\d{2}\.\d{2}\.[123](?!000)\d{3}$/',
        };
    }

    /**
     * Level ini beserta semua level di atasnya, mulai dari provinsi.
     *
     * @return list<self>
     */
    public function withAncestors(): array
    {
        return array_values(array_filter(self::cases(), fn (self $level) => $level->depth() <= $this->depth()));
    }
}
