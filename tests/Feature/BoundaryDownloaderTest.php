<?php

namespace CaturSeptian\IndonesiaTerritory\Tests\Feature;

use CaturSeptian\IndonesiaTerritory\Data\BoundaryDownloader;
use CaturSeptian\IndonesiaTerritory\Data\DataRepository;
use CaturSeptian\IndonesiaTerritory\Enums\Level;
use CaturSeptian\IndonesiaTerritory\Install\InstallationException;
use CaturSeptian\IndonesiaTerritory\Install\InstallOptions;
use CaturSeptian\IndonesiaTerritory\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class BoundaryDownloaderTest extends TestCase
{
    private const PRIMARY = 'https://utama.test';

    private const FALLBACK = 'https://cadangan.test';

    /**
     * @var array<string, string>
     */
    private array $bodies = [];

    protected function setUp(): void
    {
        parent::setUp();

        $feature = fn (string $level, string $code) => ['type' => 'Feature', 'properties' => ['code' => $code, 'level' => $level], 'geometry' => ['type' => 'Point', 'coordinates' => [106.8, -6.2]]];
        $this->bodies = [
            'provinces.geojson' => json_encode(['type' => 'FeatureCollection', 'features' => [$feature('province', '31'), $feature('province', '32')]]),
            'regencies.geojson' => json_encode(['type' => 'FeatureCollection', 'features' => [$feature('regency', '31.71')]]),
            'villages-31.geojson' => json_encode(['type' => 'FeatureCollection', 'features' => [$feature('village', '31.71.01.1001')]]),
            'villages-32.geojson' => json_encode(['type' => 'FeatureCollection', 'features' => [$feature('village', '32.04.01.2001')]]),
        ];

        File::ensureDirectoryExists($path = $this->workspace.'/manifest');
        file_put_contents($path.'/manifest.json', json_encode(['dataset_version' => 'uji', 'files' => [], 'boundaries' => array_map(
            fn (string $file) => [
                'path' => $file,
                'level' => match (true) {
                    str_starts_with($file, 'provinces') => 'province',
                    str_starts_with($file, 'regencies') => 'regency',
                    default => 'village',
                },
                'province' => preg_match('/-(\d{2})\./', $file, $m) ? $m[1] : null,
                'bytes' => strlen($this->bodies[$file]),
                'sha256' => hash('sha256', $this->bodies[$file]),
            ],
            array_keys($this->bodies),
        )]));
        $this->app->instance(DataRepository::class, new DataRepository($path));
        config(['territory.source.urls' => [self::PRIMARY, self::FALLBACK], 'territory.boundary_path' => $this->workspace.'/boundaries']);
    }

    private function downloader(): BoundaryDownloader
    {
        return new BoundaryDownloader($this->app->make(DataRepository::class), [self::PRIMARY, self::FALLBACK], $this->workspace.'/boundaries', retrySleepMs: 0);
    }

    private function fakeServer(string $host): void
    {
        Http::fake(function ($request) use ($host) {
            $file = basename($request->url());
            if (! str_starts_with($request->url(), $host)) {
                return Http::response('', 500);
            }

            return Http::response($this->bodies[$file] ?? '', isset($this->bodies[$file]) ? 200 : 404);
        });
    }

    public function test_rencana_menyaring_level_dan_provinsi(): void
    {
        $paths = fn (array $files) => array_column($files, 'path');

        $this->assertSame(['provinces.geojson', 'villages-31.geojson'], $paths($this->downloader()->plan([Level::Province, Level::Village], ['31'])));
        $this->assertSame(['villages-31.geojson', 'villages-32.geojson'], $paths($this->downloader()->plan([Level::Village], 'all')));
    }

    public function test_rencana_menolak_provinsi_tanpa_boundary(): void
    {
        $this->expectException(InstallationException::class);
        $this->expectExceptionMessage('99');

        $this->downloader()->plan([Level::Village], ['31', '99']);
    }

    public function test_mengunduh_memverifikasi_dan_mengompres(): void
    {
        $this->fakeServer(self::PRIMARY);

        $this->downloader()->download($this->downloader()->plan([Level::Province], 'all'));

        $this->assertSame($this->bodies['provinces.geojson'], gzdecode(file_get_contents($this->workspace.'/boundaries/provinces.geojson.gz')));
    }

    public function test_pindah_ke_url_cadangan(): void
    {
        $this->fakeServer(self::FALLBACK);

        $this->downloader()->download($this->downloader()->plan([Level::Regency], 'all'));

        $this->assertFileExists($this->workspace.'/boundaries/regencies.geojson.gz');
    }

    public function test_checksum_salah_tidak_menyisakan_berkas(): void
    {
        $this->bodies['provinces.geojson'] = '{"type":"FeatureCollection","features":[]}';
        $this->fakeServer(self::PRIMARY);

        try {
            $this->downloader()->download($this->downloader()->plan([Level::Province], 'all'));
            $this->fail('Seharusnya gagal karena checksum.');
        } catch (InstallationException $e) {
            $this->assertStringContainsString('Checksum', $e->getMessage());
        }

        $this->assertSame([], glob($this->workspace.'/boundaries/*'));
    }

    public function test_semua_url_gagal_memberi_petunjuk_melanjutkan(): void
    {
        Http::fake(fn () => Http::response('', 503));

        $this->expectException(InstallationException::class);
        $this->expectExceptionMessage('territory:boundaries');

        $this->downloader()->download($this->downloader()->plan([Level::Province], 'all'));
    }

    public function test_berkas_yang_sudah_ada_dilewati(): void
    {
        $this->fakeServer(self::PRIMARY);
        $plan = $this->downloader()->plan([Level::Province, Level::Regency], 'all');
        $this->downloader()->download([$plan[0]]);

        $statuses = [];
        $this->downloader()->download($plan, function (array $file, string $status) use (&$statuses) {
            $statuses[$file['path']] = $status;
        });

        $this->assertSame(['provinces.geojson' => 'sudah ada', 'regencies.geojson' => 'diunduh'], $statuses);
    }

    public function test_perintah_mengunduh_lalu_mengisi_tabel(): void
    {
        $this->fakeServer(self::PRIMARY);
        config(['territory.boundaries.levels' => ['province', 'village'], 'territory.boundaries.provinces' => 'all']);
        $this->useFixtureData();
        $this->migrate(new InstallOptions(Level::Village, boundaryLevels: [Level::Province, Level::Village]));

        $this->artisan('territory:boundaries', ['--provinces' => '31'])->assertSuccessful();

        $this->assertSame(3, DB::table('territory_boundaries')->count());
        $this->assertFileDoesNotExist($this->workspace.'/boundaries/villages-32.geojson.gz');
    }

    public function test_perintah_gagal_tanpa_level_boundary_di_config(): void
    {
        $this->artisan('territory:boundaries')->expectsOutputToContain('--boundaries')->assertFailed();
    }

    public function test_checksum_salah_di_url_utama_mencoba_url_cadangan(): void
    {
        $good = $this->bodies['provinces.geojson'];
        Http::fake(fn ($request) => Http::response(str_starts_with($request->url(), self::PRIMARY) ? '{"terpotong":' : $good));

        $this->downloader()->download($this->downloader()->plan([Level::Province], 'all'));

        $this->assertSame($good, gzdecode(file_get_contents($this->workspace.'/boundaries/provinces.geojson.gz')));
    }

    public function test_404_tidak_diulang(): void
    {
        $body = $this->bodies['provinces.geojson'];
        Http::fake(fn ($request) => str_starts_with($request->url(), self::PRIMARY) ? Http::response('', 404) : Http::response($body));

        $this->downloader()->download($this->downloader()->plan([Level::Province], 'all'));

        Http::assertSentCount(2);
    }

    public function test_perintah_mengabaikan_koma_di_akhir_daftar_provinsi(): void
    {
        $this->fakeServer(self::PRIMARY);
        config(['territory.boundaries.levels' => ['village']]);

        $this->artisan('territory:boundaries', ['--provinces' => '31,'])->assertSuccessful();

        $this->assertFileExists($this->workspace.'/boundaries/villages-31.geojson.gz');
    }
}
