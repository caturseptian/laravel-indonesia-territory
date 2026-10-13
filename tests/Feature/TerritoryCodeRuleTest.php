<?php

namespace CaturSeptian\IndonesiaTerritory\Tests\Feature;

use CaturSeptian\IndonesiaTerritory\Enums\Level;
use CaturSeptian\IndonesiaTerritory\Install\InstallOptions;
use CaturSeptian\IndonesiaTerritory\Rules\TerritoryCode;
use CaturSeptian\IndonesiaTerritory\Tests\TestCase;
use Illuminate\Support\Facades\Validator;

class TerritoryCodeRuleTest extends TestCase
{
    private function passes(mixed $value, TerritoryCode $rule): bool
    {
        return Validator::make(['code' => $value], ['code' => [$rule]])->passes();
    }

    public function test_memeriksa_format_dan_level(): void
    {
        $this->assertTrue($this->passes('31.71.01.1001', new TerritoryCode(Level::Village)));
        $this->assertFalse($this->passes('31.71.01', new TerritoryCode(Level::Village)));
        $this->assertFalse($this->passes('31.70', new TerritoryCode(Level::Regency)));
        $this->assertFalse($this->passes(31, new TerritoryCode(Level::Province)));
    }

    public function test_memeriksa_keberadaan_di_database(): void
    {
        $this->useFixtureData();
        $this->migrate(new InstallOptions(Level::Village));
        $this->artisan('territory:seed')->assertSuccessful();

        $this->assertTrue($this->passes('31.71.01.1001', new TerritoryCode(Level::Village, exists: true)));
        $this->assertFalse($this->passes('31.71.01.1999', new TerritoryCode(Level::Village, exists: true)));
    }

    public function test_pesan_error_menyebut_level(): void
    {
        $validator = Validator::make(['kode_desa' => '31'], ['kode_desa' => [new TerritoryCode(Level::Village)]]);

        $this->assertStringContainsString('kelurahan/desa', $validator->errors()->first('kode_desa'));
    }
}
