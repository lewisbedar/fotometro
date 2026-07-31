<?php

namespace App\Console\Commands;

use App\Services\Idfm\IdfmClient;
use Illuminate\Console\Command;
use RuntimeException;

class VendorIdfmDataCommand extends Command
{
    protected $signature = 'fotometro:vendor-idfm-data';

    protected $description = 'Re-download the IDFM network/access datasets and store them under resources/idfm-data/, so the regular import runs against a committed snapshot instead of the live IDFM API.';

    /**
     * The live IDFM URLs, independent of config/fotometro.php (which points at
     * the vendored files by default) — this is the one place that still knows
     * where to fetch a fresh copy from.
     */
    private const LIVE_URLS = [
        'lines' => 'https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/referentiel-des-lignes/records?limit=100',
        'arrets-lignes' => 'https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/arrets-lignes/records?limit=100',
        'stop-areas' => 'https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/zones-d-arrets/exports/csv?limit=-1',
        'stop-relations' => 'https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/relations/exports/csv?limit=-1',
        'traces' => 'https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/traces-des-lignes-de-transport-en-commun-idfm/records?limit=100',
        'accesses' => 'https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/acces/exports/csv?limit=-1',
        'access-relations' => 'https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/relations-acces/exports/csv?limit=-1',
    ];

    public function handle(IdfmClient $client): int
    {
        $directory = base_path('resources/idfm-data');

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            $this->error("Unable to create {$directory}.");

            return self::FAILURE;
        }

        foreach (self::LIVE_URLS as $name => $url) {
            $this->line("Downloading {$name}...");

            try {
                $data = $client->fetchComplete($url);
            } catch (RuntimeException $exception) {
                $this->error("Download failed for {$name}: {$exception->getMessage()}");

                return self::FAILURE;
            }

            $path = $data['_temporary_files'][0] ?? null;

            if (! $path || ! is_file($path)) {
                $this->error("Download failed for {$name}: no file was produced.");

                return self::FAILURE;
            }

            $target = "{$directory}/{$name}.csv";

            if (! rename($path, $target)) {
                copy($path, $target);
                unlink($path);
            }
        }

        $this->info('IDFM snapshot updated under resources/idfm-data/. Review the diff (e.g. `git diff --stat`) and commit it.');

        return self::SUCCESS;
    }
}
