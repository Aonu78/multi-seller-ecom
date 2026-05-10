<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class DbUpload extends Command
{
    protected $signature = 'db:upload
        {source : Local file path OR http(s) URL}
        {--to=restore : Target folder inside storage/app (default: restore)}
        {--name= : Optional target filename (default: keep original name)}';

    protected $description = 'Upload a database dump into storage/app/{folder} (supports local path or URL).';

    public function handle(): int
    {
        $source = (string) $this->argument('source');
        $folder = trim((string) $this->option('to'), "/ \t\n\r\0\x0B");
        $folder = $folder === '' ? 'restore' : $folder;

        Storage::makeDirectory($folder);

        $isUrl = preg_match('#^https?://#i', $source) === 1;

        $defaultName = $isUrl
            ? basename(parse_url($source, PHP_URL_PATH) ?: 'dump.sql')
            : basename($source);

        $targetName = (string) ($this->option('name') ?: $defaultName);
        if ($targetName === '') $targetName = 'dump.sql';

        $targetPath = $folder . '/' . $targetName;

        if ($isUrl) {
            $this->info("Downloading: $source");
            $res = Http::timeout(300)->withOptions(['stream' => true])->get($source);
            if (!$res->ok()) {
                $this->error("Download failed: HTTP ".$res->status());
                return self::FAILURE;
            }
            Storage::put($targetPath, $res->body());
        } else {
            if (!is_file($source) || !is_readable($source)) {
                $this->error("Local file not found/readable: $source");
                return self::FAILURE;
            }
            Storage::put($targetPath, file_get_contents($source));
        }

        $this->info("Saved to: storage/app/$targetPath");
        $this->info("Full path: " . Storage::path($targetPath));

        return self::SUCCESS;
    }
}
