<?php

namespace App\Console\Commands;

use App\Services\TrialEvidenceImporter;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use JsonException;

class ImportTrialEvidence extends Command
{
    protected $signature = 'observatory:import {file : Sanitized trial-evidence.json artifact}';

    protected $description = 'Validate and import pilot evidence into an existing trial';

    public function handle(TrialEvidenceImporter $importer): int
    {
        $path = (string) $this->argument('file');
        if (! is_file($path) || filesize($path) > 65536) {
            $this->error('Berkas tidak ditemukan atau lebih besar dari 64 KiB.');

            return self::FAILURE;
        }
        try {
            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new JsonException('Berkas tidak dapat dibaca.');
            }
            $data = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($data)) {
                throw new JsonException('Bukti harus berbentuk object JSON.');
            }
            $trial = $importer->import($data);
        } catch (JsonException|ValidationException $exception) {
            $this->error($exception instanceof ValidationException ? json_encode($exception->errors(), JSON_THROW_ON_ERROR) : $exception->getMessage());

            return self::FAILURE;
        }
        $this->info("Bukti tersimpan: trial={$trial->id}; status={$trial->status->value}; run={$trial->github_run_id}");

        return self::SUCCESS;
    }
}
