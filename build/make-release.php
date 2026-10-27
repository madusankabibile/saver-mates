<?php

/*
|--------------------------------------------------------------------------
| Build a distributable SaverMate zip
|--------------------------------------------------------------------------
| Usage (from the project folder):
|
|     php build/make-release.php [version]
|
| Copies the project into dist/savermate-<version>/, leaving out everything
| that belongs to this machine (.env, uploads, logs, caches, the install
| lock), strips the dev-only Composer packages from the copy, and zips
| it as dist/savermate-<version>.zip. The person receiving it unzips it on
| their server and opens it in a browser - public/install takes it from there.
*/

declare(strict_types=1);

$root = dirname(__DIR__);
$version = $argv[1] ?? date('Y.m.d');

if (! preg_match('/^[A-Za-z0-9._-]+$/', $version)) {
    fwrite(STDERR, "Version may only contain letters, numbers, dots, dashes and underscores.\n");
    exit(1);
}

if (! class_exists(ZipArchive::class)) {
    fwrite(STDERR, "The PHP zip extension is required to build a release.\n");
    exit(1);
}

$name = 'savermate-'.$version;
$dist = $root.'/dist';
$stage = $dist.'/'.$name;
$zipPath = $dist.'/'.$name.'.zip';

/*
| Paths are relative to the project root, with forward slashes. A directory
| rule excludes everything under it.
*/
$exclude = [
    '.env', '.env.backup', '.env.production', '.phpunit.result.cache', '.phpunit.cache',
    '.git', '.idea', '.vscode', '.fleet', '.claude', 'node_modules',
    'tests', 'phpunit.xml',
    'build', 'dist',
    'public/storage',    // symlink; the installer recreates it
    'public/hot', 'public/build',
];

function excluded(string $relative, array $exclude): bool
{
    foreach ($exclude as $rule) {
        if ($relative === $rule || str_starts_with($relative, $rule.'/')) {
            return true;
        }
    }

    // storage/ and bootstrap/cache/ ship as empty skeletons: keep only the
    // .gitignore placeholders so the folders exist after unzipping.
    if (str_starts_with($relative, 'storage/') || str_starts_with($relative, 'bootstrap/cache/')) {
        return basename($relative) !== '.gitignore';
    }

    return false;
}

function removeTree(string $path): void
{
    if (is_link($path) || is_file($path)) {
        unlink($path);

        return;
    }

    if (! is_dir($path)) {
        return;
    }

    foreach (scandir($path) as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            removeTree($path.'/'.$entry);
        }
    }

    rmdir($path);
}

function step(string $message): void
{
    echo "\n> {$message}\n";
}

/* ---------------------------------------------------------------- */

step("Building {$name}");

removeTree($stage);
@unlink($zipPath);
is_dir($dist) || mkdir($dist, 0775, true);

step('Copying project files');

$directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
$filter = new RecursiveCallbackFilterIterator($directory, function (SplFileInfo $file) use ($root, $exclude) {
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

    // Skeleton folders are walked only where a placeholder lives, so the
    // gigabytes of uploads under storage/app are never even listed.
    if ($file->isDir() && ! $file->isLink() && (str_starts_with($relative, 'storage') || $relative === 'bootstrap/cache')) {
        return $relative === 'storage' || is_file($file->getPathname().'/.gitignore');
    }

    return ! $file->isLink() && ! excluded($relative, $exclude);
});

$copied = 0;

foreach (new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::SELF_FIRST) as $file) {
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    $target = $stage.'/'.$relative;

    if ($file->isDir()) {
        continue; // created on demand, so empty storage folders without placeholders are skipped
    }

    is_dir(dirname($target)) || mkdir(dirname($target), 0775, true);
    copy($file->getPathname(), $target);
    $copied++;
}

echo "  {$copied} files\n";

step('Removing development Composer packages');

// vendor/ was copied above, so this only deletes the dev packages and
// rebuilds the autoloader - it needs no network access.
$composer = trim((string) shell_exec(PHP_OS_FAMILY === 'Windows' ? 'where composer 2>NUL' : 'command -v composer 2>/dev/null'));
$status = 1;

if ($composer !== '') {
    passthru('composer install --no-dev --optimize-autoloader --no-scripts --no-interaction --no-progress --working-dir='.escapeshellarg($stage), $status);
}

if ($status !== 0) {
    echo "  Skipped (composer missing or failed) - the release ships this project's full vendor/, which still works.\n";
}

step('Writing zip');

$zip = new ZipArchive();

if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Could not create {$zipPath}\n");
    exit(1);
}

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);

foreach ($files as $file) {
    $entry = $name.'/'.str_replace('\\', '/', substr($file->getPathname(), strlen($stage) + 1));
    $file->isDir() ? $zip->addEmptyDir($entry) : $zip->addFile($file->getPathname(), $entry);
}

$count = $zip->numFiles;
$zip->close();

removeTree($stage);

printf("\nDone: %s (%d entries, %.1f MB)\n", $zipPath, $count, filesize($zipPath) / 1048576);
