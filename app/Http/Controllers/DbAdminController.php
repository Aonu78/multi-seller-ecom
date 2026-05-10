<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Symfony\Component\HttpFoundation\Response;

class DbAdminController extends Controller
{
    private function authorizeToken(Request $request): void
    {
        $token = (string) $request->header('X-DB-ADMIN-TOKEN', $request->query('token', ''));
        abort_unless(hash_equals((string) config('app.db_admin_token'), $token), 403, 'Forbidden');
    }

    private function mysqlCredentials(): array
    {
        $connection = config('database.default');
        $cfg = config("database.connections.$connection");

        abort_unless(($cfg['driver'] ?? null) === 'mysql', 400, 'Only mysql is supported in this example.');

        return [
            'host' => $cfg['host'] ?? '127.0.0.1',
            'port' => (string)($cfg['port'] ?? '3306'),
            'database' => $cfg['database'] ?? '',
            'username' => $cfg['username'] ?? '',
            'password' => $cfg['password'] ?? '',
        ];
    }

    /**
     * POST /admin/db/backup
     * Creates a .sql.gz in storage/app/backups/
     */
    public function backup(Request $request)
    {
        // $this->authorizeToken($request);

        $creds = $this->mysqlCredentials();
        $timestamp = now()->format('Ymd_His');
        $dir = 'backups';
        $filename = "db_backup_{$creds['database']}_{$timestamp}.sql.gz";
        $relativePath = "$dir/$filename";
        $fullPath = Storage::path($relativePath);

        Storage::makeDirectory($dir);

        // Use mysqldump -> gzip
        $command = sprintf(
            'mysqldump --single-transaction --quick --lock-tables=false -h%s -P%s -u%s %s | gzip > %s',
            escapeshellarg($creds['host']),
            escapeshellarg($creds['port']),
            escapeshellarg($creds['username']),
            escapeshellarg($creds['database']),
            escapeshellarg($fullPath)
        );

        $process = Process::fromShellCommandline($command, null, [
            // Avoid putting password into the command line if possible. We'll pass it via env.
            'MYSQL_PWD' => $creds['password'],
        ]);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            return response()->json([
                'ok' => false,
                'error' => $process->getErrorOutput() ?: $process->getOutput(),
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'path' => $relativePath,
            'bytes' => Storage::size($relativePath),
        ]);
    }

    /**
     * POST /admin/db/restore
     * Body: { "path": "restore/mydump.sql.gz" }  (recommended: storage/app/restore only)
     *
     * Restores into current DB (DROPS ALL TABLES FIRST).
     */
    public function restore(Request $request)
    {
        // $this->authorizeToken($request);

        $request->validate([
            'path' => ['required', 'string', 'max:500'],
        ]);

        $path = $request->string('path')->toString();

        // Restrict restore files to storage/app/restore to avoid arbitrary file reads
        abort_unless(str_starts_with($path, 'restore/'), 422, 'Path must start with restore/.');

        abort_unless(Storage::exists($path), 404, 'File not found.');
        $fullPath = Storage::path($path);

        $creds = $this->mysqlCredentials();

        // Danger: This drops all tables in the target DB. Keep it explicit.
        $this->dropAllTables();

        // If .gz: gunzip -c | mysql ...
        // Else: cat | mysql ...
        $isGz = str_ends_with($fullPath, '.gz');

        $pipe = $isGz
            ? sprintf('gunzip -c %s', escapeshellarg($fullPath))
            : sprintf('cat %s', escapeshellarg($fullPath));

        $command = sprintf(
            '%s | mysql -h%s -P%s -u%s %s',
            $pipe,
            escapeshellarg($creds['host']),
            escapeshellarg($creds['port']),
            escapeshellarg($creds['username']),
            escapeshellarg($creds['database'])
        );

        $process = Process::fromShellCommandline($command, null, [
            'MYSQL_PWD' => $creds['password'],
        ]);
        $process->setTimeout(600);
        $process->run();

        if (!$process->isSuccessful()) {
            return response()->json([
                'ok' => false,
                'error' => $process->getErrorOutput() ?: $process->getOutput(),
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'restored_from' => $path,
        ]);
    }

    private function dropAllTables(): void
    {
        // MySQL-specific: disable FK checks, drop tables
        $tables = DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
        if (empty($tables)) return;

        $dbName = DB::getDatabaseName();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($tables as $row) {
            // Column name is like "Tables_in_databasename"
            $tableName = array_values((array) $row)[0];
            DB::statement("DROP TABLE IF EXISTS `" . str_replace('`', '``', $tableName) . "`");
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
