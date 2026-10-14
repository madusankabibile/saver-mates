<?php

/*
|--------------------------------------------------------------------------
| SaverMate web installer
|--------------------------------------------------------------------------
| A plain PHP wizard that runs before Laravel can boot (there is no .env or
| APP_KEY yet). It checks the server, collects database and admin details,
| writes .env, then boots Laravel once to migrate, seed and create the admin.
|
| When it finishes it writes storage/installed. public/index.php sends every
| request here until that file exists, and this script refuses to run once
| it does - delete it only if you really mean to reinstall.
*/

declare(strict_types=1);

const MIN_PHP = '8.1.0';

$root = dirname(__DIR__, 2);
$lock = $root.'/storage/installed';

session_name('savermate_installer');
session_start();

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

/* ---------------------------------------------------------------- */
/* Helpers                                                           */
/* ---------------------------------------------------------------- */

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function old(string $key, string $default = ''): string
{
    return e($_POST[$key] ?? $_SESSION['data'][$key] ?? $default);
}

function redirect(string $step): void
{
    header('Location: ?step='.$step);
    exit;
}

/** The public/ URL this copy is served from, e.g. http://host/savermates/public */
function detectAppUrl(): string
{
    $https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'], 2)), '/');

    return ($https ? 'https' : 'http').'://'.$host.$path;
}

function requirements(string $root): array
{
    $checks = [];

    $checks[] = ['PHP '.MIN_PHP.' or newer (you have '.PHP_VERSION.')', version_compare(PHP_VERSION, MIN_PHP, '>='), true];

    $extensions = [
        'pdo_mysql' => true, 'mbstring' => true, 'openssl' => true, 'tokenizer' => true,
        'xml' => true, 'ctype' => true, 'json' => true, 'fileinfo' => true, 'curl' => true,
        'dom' => true, 'filter' => true, 'session' => true,
        'gd' => false, // photos still upload without it, they just aren't downscaled
    ];

    foreach ($extensions as $ext => $required) {
        $checks[] = ["PHP extension: {$ext}".($required ? '' : ' (recommended)'), extension_loaded($ext), $required];
    }

    $checks[] = ['Composer dependencies (vendor/ folder)', is_file($root.'/vendor/autoload.php'), true];

    foreach (['', 'storage', 'storage/app', 'storage/framework', 'storage/logs', 'bootstrap/cache'] as $dir) {
        $label = $dir === '' ? 'project folder (to write .env)' : $dir;
        $checks[] = ["Writable: {$label}", is_writable($root.($dir ? '/'.$dir : '')), true];
    }

    return $checks;
}

function pdoFor(array $db, bool $withDatabase = true): PDO
{
    $dsn = "mysql:host={$db['db_host']};port={$db['db_port']};charset=utf8mb4";

    if ($withDatabase) {
        $dsn .= ";dbname={$db['db_database']}";
    }

    return new PDO($dsn, $db['db_username'], $db['db_password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);
}

/** Quote a value for .env, keeping simple values bare like Laravel does. */
function envValue(string $value): string
{
    if ($value === '') {
        return '';
    }

    if (preg_match('/^[A-Za-z0-9_.:\/@+=-]+$/', $value)) {
        return $value;
    }

    return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
}

function writeEnv(string $root, array $values): void
{
    $template = is_file($root.'/.env.example') ? file_get_contents($root.'/.env.example') : '';

    foreach ($values as $key => $value) {
        $line = $key.'='.envValue((string) $value);
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        $template = preg_match($pattern, $template)
            ? preg_replace_callback($pattern, fn () => $line, $template)
            : rtrim($template)."\n".$line."\n";
    }

    if (file_put_contents($root.'/.env', $template) === false) {
        throw new RuntimeException('Could not write the .env file.');
    }
}

/** public/storage -> storage/app/public, with a junction fallback on Windows. */
function linkStorage(string $root): ?string
{
    $link = $root.'/public/storage';
    $target = $root.'/storage/app/public';

    if (file_exists($link)) {
        return null;
    }

    if (@symlink($target, $link)) {
        return null;
    }

    if (PHP_OS_FAMILY === 'Windows' && function_exists('exec')) {
        @exec('mklink /J '.escapeshellarg(str_replace('/', '\\', $link)).' '.escapeshellarg(str_replace('/', '\\', $target)));

        if (file_exists($link)) {
            return null;
        }
    }

    return 'Could not create public/storage automatically. Run "php artisan storage:link" in the project folder, '
        .'or ask your host to point public/storage at storage/app/public. Avatars and uploads will not display until you do.';
}

/* ---------------------------------------------------------------- */
/* Already installed                                                 */
/* ---------------------------------------------------------------- */

$step = $_GET['step'] ?? 'requirements';

if (is_file($lock) && $step !== 'done') {
    $step = 'locked';
}

$errors = [];
$warnings = [];

/* ---------------------------------------------------------------- */
/* Form handling                                                     */
/* ---------------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step !== 'locked') {
    if (! hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $errors[] = 'Your session expired. Please submit the form again.';
    } elseif ($step === 'database') {
        $db = [
            'db_host' => trim($_POST['db_host'] ?? ''),
            'db_port' => trim($_POST['db_port'] ?? '3306'),
            'db_database' => trim($_POST['db_database'] ?? ''),
            'db_username' => trim($_POST['db_username'] ?? ''),
            'db_password' => (string) ($_POST['db_password'] ?? ''),
        ];

        if ($db['db_host'] === '' || $db['db_database'] === '' || $db['db_username'] === '') {
            $errors[] = 'Host, database name and username are required.';
        } elseif (! ctype_digit($db['db_port'])) {
            $errors[] = 'The port must be a number.';
        } elseif (! preg_match('/^[A-Za-z0-9_$-]+$/', $db['db_database'])) {
            $errors[] = 'The database name may only contain letters, numbers, dashes and underscores.';
        }

        if (! $errors) {
            try {
                try {
                    $pdo = pdoFor($db);
                } catch (PDOException $ex) {
                    // 1049 = unknown database. Try to create it if the user is allowed to.
                    if ((int) ($ex->errorInfo[1] ?? 0) !== 1049 && ! str_contains($ex->getMessage(), '1049')) {
                        throw $ex;
                    }

                    pdoFor($db, false)->exec('CREATE DATABASE `'.$db['db_database'].'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                    $pdo = pdoFor($db);
                }

                $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
                $db['fresh'] = false;

                if ($tables && empty($_POST['wipe'])) {
                    $errors[] = 'The database "'.$db['db_database'].'" already contains '.count($tables).' table(s). '
                        .'Choose an empty database, or tick the box below to drop everything in it.';
                    $_SESSION['show_wipe'] = true;
                } else {
                    $db['fresh'] = (bool) $tables;
                    $_SESSION['data'] = array_merge($_SESSION['data'] ?? [], $db);
                    $_SESSION['db_ok'] = true;
                    unset($_SESSION['show_wipe']);
                    redirect('site');
                }
            } catch (PDOException $ex) {
                $errors[] = 'Could not connect: '.$ex->getMessage();
            }
        }
    } elseif ($step === 'site') {
        if (empty($_SESSION['db_ok'])) {
            redirect('database');
        }

        $site = [
            'app_name' => trim($_POST['app_name'] ?? ''),
            'app_url' => rtrim(trim($_POST['app_url'] ?? ''), '/'),
            'timezone' => $_POST['timezone'] ?? 'UTC',
            'admin_name' => trim($_POST['admin_name'] ?? ''),
            'admin_username' => trim($_POST['admin_username'] ?? ''),
            'admin_email' => trim($_POST['admin_email'] ?? ''),
            'registration_open' => ! empty($_POST['registration_open']),
            'demo' => ! empty($_POST['demo']),
        ];
        $password = (string) ($_POST['admin_password'] ?? '');

        if ($site['app_name'] === '') {
            $errors[] = 'Give the site a name.';
        }
        if (! filter_var($site['app_url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'The site URL is not a valid URL.';
        }
        if (! in_array($site['timezone'], timezone_identifiers_list(), true)) {
            $errors[] = 'Pick a time zone from the list.';
        }
        if ($site['admin_name'] === '') {
            $errors[] = 'The administrator needs a name.';
        }
        if (! preg_match('/^[A-Za-z0-9._-]{3,40}$/', $site['admin_username'])) {
            $errors[] = 'Usernames are 3-40 characters: letters, numbers, dots, dashes and underscores.';
        }
        if ($site['admin_email'] !== '' && ! filter_var($site['admin_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'The email address is not valid.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'The password must be at least 8 characters.';
        } elseif ($password !== ($_POST['admin_password_confirmation'] ?? '')) {
            $errors[] = 'The two passwords do not match.';
        }

        $_SESSION['data'] = array_merge($_SESSION['data'] ?? [], $site);

        if (! $errors) {
            $_SESSION['admin_password'] = $password;
            redirect('install');
        }
    } elseif ($step === 'install') {
        $data = $_SESSION['data'] ?? [];

        if (empty($_SESSION['db_ok']) || empty($_SESSION['admin_password'])) {
            redirect('database');
        }

        @set_time_limit(300);
        $log = [];

        try {
            writeEnv($root, [
                'APP_NAME' => $data['app_name'],
                'APP_ENV' => 'production',
                'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
                'APP_DEBUG' => 'false',
                'APP_URL' => $data['app_url'],
                'APP_TIMEZONE' => $data['timezone'],
                'LOG_LEVEL' => 'error',
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => $data['db_host'],
                'DB_PORT' => $data['db_port'],
                'DB_DATABASE' => $data['db_database'],
                'DB_USERNAME' => $data['db_username'],
                'DB_PASSWORD' => $data['db_password'],
                'FILESYSTEM_DISK' => 'public',
            ]);
            $log[] = 'Wrote .env';

            // A cached config from the build machine would override the new .env.
            foreach (glob($root.'/bootstrap/cache/{config,routes-v7,events}.php', GLOB_BRACE) ?: [] as $cached) {
                @unlink($cached);
            }

            foreach (['storage/app/public', 'storage/app/drive', 'storage/app/chunks', 'storage/framework/cache/data',
                'storage/framework/sessions', 'storage/framework/views', 'storage/logs'] as $dir) {
                is_dir($root.'/'.$dir) || @mkdir($root.'/'.$dir, 0775, true);
            }

            require $root.'/vendor/autoload.php';
            $app = require $root.'/bootstrap/app.php';
            $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
            $kernel->bootstrap();

            $kernel->call($data['fresh'] ? 'migrate:fresh' : 'migrate', ['--force' => true]);
            $log[] = trim($kernel->output());

            if (! empty($data['demo'])) {
                $kernel->call('db:seed', ['--force' => true]);
                $log[] = 'Seeded demo data. '.trim($kernel->output());
            }

            $admin = App\Models\User::firstOrNew(['username' => $data['admin_username']]);
            $admin->fill([
                'name' => $data['admin_name'],
                'email' => $data['admin_email'] ?: null,
                'password' => $_SESSION['admin_password'],
            ]);
            $admin->is_admin = true;
            $admin->save();
            $log[] = "Created administrator @{$admin->username}";

            App\Models\Setting::put(['registration_open' => $data['registration_open'] ? '1' : '0']);

            if ($warning = linkStorage($root)) {
                $warnings[] = $warning;
            } else {
                $log[] = 'Linked public/storage';
            }

            $kernel->call('optimize:clear');

            file_put_contents($lock, 'Installed '.date('c')."\n");
            $log[] = 'Installation complete';

            $_SESSION['result'] = ['log' => $log, 'warnings' => $warnings, 'url' => $data['app_url'], 'username' => $data['admin_username']];
            unset($_SESSION['admin_password'], $_SESSION['db_ok']);
            redirect('done');
        } catch (Throwable $ex) {
            $errors[] = 'Installation failed: '.$ex->getMessage();
            $_SESSION['install_log'] = $log;
        }
    }
}

/* ---------------------------------------------------------------- */
/* View                                                              */
/* ---------------------------------------------------------------- */

$steps = ['requirements' => 'Server check', 'database' => 'Database', 'site' => 'Site & admin', 'install' => 'Install'];
$data = $_SESSION['data'] ?? [];
$checks = requirements($root);
$canContinue = ! in_array(false, array_map(fn ($c) => $c[1] || ! $c[2], $checks), true);
$csrf = '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">';

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Install SaverMate</title>
<link rel="icon" href="../favicon.svg" type="image/svg+xml">
<style>
    :root {
        --ink: #13231d; --ink-soft: #3c5249; --muted: #6a7f77; --paper: #eff3f1; --surface: #fff;
        --jade: #07b37f; --jade-dark: #05946a; --jade-wash: #e3f7ef; --coral: #e5483a; --coral-wash: #ffeceb;
        --amber-wash: #fff5e4; --line: #dde7e2; --r: 6px;
        --brand-grad: linear-gradient(100deg, #059a6d 0%, #07b37f 55%, #1fc490 100%);
    }
    * { box-sizing: border-box; }
    body { margin: 0; background: var(--paper); color: var(--ink); font: 15px/1.55 'Segoe UI', system-ui, -apple-system, sans-serif; }
    .wrap { max-width: 640px; margin: 0 auto; padding: 40px 16px 64px; }
    .brand { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 1.35rem; margin-bottom: 24px; }
    .brand img { width: 36px; height: 36px; }
    .steps { display: flex; gap: 6px; margin: 0 0 18px; padding: 0; list-style: none; flex-wrap: wrap; }
    .steps li { flex: 1; min-width: 110px; font-size: .8rem; color: var(--muted); border-top: 3px solid var(--line); padding-top: 6px; }
    .steps li.on { color: var(--ink); border-color: var(--jade); font-weight: 600; }
    .steps li.past { border-color: var(--jade-dark); }
    .card { background: var(--surface); border-radius: var(--r); padding: 28px; box-shadow: 0 1px 2px rgba(19,35,29,.04), 0 4px 14px -8px rgba(19,35,29,.1); }
    h1 { font-size: 1.3rem; margin: 0 0 6px; }
    p.lead { margin: 0 0 20px; color: var(--ink-soft); }
    .checks { list-style: none; margin: 0 0 20px; padding: 0; border: 1px solid var(--line); border-radius: var(--r); }
    .checks li { display: flex; justify-content: space-between; gap: 12px; padding: 8px 12px; border-top: 1px solid var(--line); }
    .checks li:first-child { border-top: 0; }
    .ok { color: var(--jade-dark); font-weight: 600; } .bad { color: var(--coral); font-weight: 600; } .soft { color: #b07a1c; font-weight: 600; }
    label { display: block; font-weight: 600; font-size: .9rem; margin: 14px 0 4px; }
    .hint { font-weight: 400; color: var(--muted); font-size: .82rem; }
    input[type=text], input[type=password], input[type=email], input[type=url], input[type=number], select {
        width: 100%; padding: 9px 11px; border: 1px solid var(--line); border-radius: var(--r); font: inherit; color: inherit; background: #fff;
    }
    input:focus, select:focus { outline: 2px solid var(--jade); outline-offset: -1px; }
    .row { display: grid; grid-template-columns: 1fr 120px; gap: 12px; }
    .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    @media (max-width: 520px) { .row, .row2 { grid-template-columns: 1fr; } .card { padding: 20px; } }
    .check { display: flex; gap: 10px; align-items: flex-start; font-weight: 400; margin-top: 14px; }
    .check input { margin-top: 4px; }
    fieldset { border: 0; border-top: 1px solid var(--line); margin: 22px 0 0; padding: 8px 0 0; }
    legend { font-weight: 700; padding-right: 8px; color: var(--ink-soft); font-size: .8rem; text-transform: uppercase; letter-spacing: .05em; }
    .actions { display: flex; justify-content: space-between; align-items: center; margin-top: 24px; gap: 12px; }
    .btn { display: inline-block; border: 0; background: var(--brand-grad); color: #fff; font: inherit; font-weight: 600; padding: 10px 20px; border-radius: var(--r); cursor: pointer; text-decoration: none; }
    .btn[disabled] { opacity: .45; cursor: not-allowed; }
    .link { color: var(--ink-soft); }
    .alert { border-radius: var(--r); padding: 12px 14px; margin-bottom: 16px; }
    .alert.err { background: var(--coral-wash); color: #8f2219; }
    .alert.warn { background: var(--amber-wash); color: #7a5410; }
    .alert.good { background: var(--jade-wash); color: #035a40; }
    .alert ul { margin: 0; padding-left: 18px; }
    pre { background: #f5f8f7; border: 1px solid var(--line); border-radius: var(--r); padding: 12px; font-size: .8rem; white-space: pre-wrap; max-height: 260px; overflow: auto; }
    dl { display: grid; grid-template-columns: max-content 1fr; gap: 6px 16px; margin: 0; }
    dt { color: var(--muted); } dd { margin: 0; word-break: break-all; }
</style>
</head>
<body>
<div class="wrap">
    <div class="brand"><img src="../favicon.svg" alt=""> SaverMate installer</div>

    <?php if (isset($steps[$step])): $past = true; ?>
        <ol class="steps">
            <?php foreach ($steps as $key => $label): if ($key === $step) $past = false; ?>
                <li class="<?= $key === $step ? 'on' : ($past ? 'past' : '') ?>"><?= e($label) ?></li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>

    <div class="card">
        <?php if ($errors): ?>
            <div class="alert err"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <?php if ($step === 'locked'): ?>
            <h1>Already installed</h1>
            <p class="lead">SaverMate is already installed on this server, so the installer is switched off.</p>
            <p>To reinstall, delete <code>storage/installed</code> from the project folder and reload this page.
               Reinstalling into the same database can drop all existing data.</p>
            <div class="actions"><span></span><a class="btn" href="../">Open SaverMate</a></div>

        <?php elseif ($step === 'requirements'): ?>
            <h1>Server check</h1>
            <p class="lead">Making sure this server can run SaverMate.</p>
            <ul class="checks">
                <?php foreach ($checks as [$label, $passed, $required]): ?>
                    <li><span><?= e($label) ?></span>
                        <?php if ($passed): ?><span class="ok">OK</span>
                        <?php elseif ($required): ?><span class="bad">Missing</span>
                        <?php else: ?><span class="soft">Missing</span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (! $canContinue): ?>
                <div class="alert err">Fix the items marked <strong>Missing</strong>, then reload this page.
                    Writable folders need write permission for the web server user (e.g. <code>chmod -R 775 storage bootstrap/cache</code>).</div>
            <?php endif; ?>
            <div class="actions"><span></span>
                <?php if ($canContinue): ?><a class="btn" href="?step=database">Continue</a>
                <?php else: ?><a class="btn" href="?step=requirements">Check again</a><?php endif; ?>
            </div>

        <?php elseif ($step === 'database'): ?>
            <h1>Database</h1>
            <p class="lead">Enter your MySQL or MariaDB details. If the database does not exist yet the installer will try to create it.</p>
            <form method="post" action="?step=database">
                <?= $csrf ?>
                <div class="row">
                    <div><label for="db_host">Host</label><input type="text" id="db_host" name="db_host" value="<?= old('db_host', '127.0.0.1') ?>" required></div>
                    <div><label for="db_port">Port</label><input type="number" id="db_port" name="db_port" value="<?= old('db_port', '3306') ?>" required></div>
                </div>
                <label for="db_database">Database name</label>
                <input type="text" id="db_database" name="db_database" value="<?= old('db_database', 'savermate') ?>" required>
                <div class="row2">
                    <div><label for="db_username">Username</label><input type="text" id="db_username" name="db_username" value="<?= old('db_username', 'root') ?>" required autocomplete="off"></div>
                    <div><label for="db_password">Password</label><input type="password" id="db_password" name="db_password" value="" autocomplete="new-password"></div>
                </div>
                <?php if (! empty($_SESSION['show_wipe'])): ?>
                    <label class="check"><input type="checkbox" name="wipe" value="1">
                        <span>Drop every existing table in this database and install fresh. <strong>This deletes that data permanently.</strong></span></label>
                <?php endif; ?>
                <div class="actions">
                    <a class="link" href="?step=requirements">Back</a>
                    <button class="btn" type="submit">Test connection &amp; continue</button>
                </div>
            </form>

        <?php elseif ($step === 'site'): ?>
            <h1>Site &amp; administrator</h1>
            <p class="lead">Name the network and create the first administrator account.</p>
            <form method="post" action="?step=site">
                <?= $csrf ?>
                <label for="app_name">Site name</label>
                <input type="text" id="app_name" name="app_name" value="<?= old('app_name', 'SaverMate') ?>" required>
                <label for="app_url">Site URL <span class="hint">- the address people will open; detected automatically</span></label>
                <input type="url" id="app_url" name="app_url" value="<?= old('app_url', detectAppUrl()) ?>" required>
                <label for="timezone">Time zone</label>
                <select id="timezone" name="timezone">
                    <?php $tz = $_POST['timezone'] ?? $data['timezone'] ?? date_default_timezone_get(); ?>
                    <?php foreach (timezone_identifiers_list() as $zone): ?>
                        <option<?= $zone === $tz ? ' selected' : '' ?>><?= e($zone) ?></option>
                    <?php endforeach; ?>
                </select>

                <fieldset><legend>Administrator</legend>
                    <div class="row2">
                        <div><label for="admin_name">Full name</label><input type="text" id="admin_name" name="admin_name" value="<?= old('admin_name') ?>" required></div>
                        <div><label for="admin_username">Username</label><input type="text" id="admin_username" name="admin_username" value="<?= old('admin_username', 'admin') ?>" required pattern="[A-Za-z0-9._\-]{3,40}"></div>
                    </div>
                    <label for="admin_email">Email <span class="hint">(optional)</span></label>
                    <input type="email" id="admin_email" name="admin_email" value="<?= old('admin_email') ?>">
                    <div class="row2">
                        <div><label for="admin_password">Password</label><input type="password" id="admin_password" name="admin_password" minlength="8" required autocomplete="new-password"></div>
                        <div><label for="admin_password_confirmation">Confirm password</label><input type="password" id="admin_password_confirmation" name="admin_password_confirmation" minlength="8" required autocomplete="new-password"></div>
                    </div>
                </fieldset>

                <fieldset><legend>Options</legend>
                    <?php $posted = $_SERVER['REQUEST_METHOD'] === 'POST'; ?>
                    <label class="check"><input type="checkbox" name="registration_open" value="1"<?= ($posted ? ! empty($_POST['registration_open']) : ($data['registration_open'] ?? true)) ? ' checked' : '' ?>>
                        <span>Let colleagues create their own accounts <span class="hint">- you can change this later in the admin panel</span></span></label>
                    <label class="check"><input type="checkbox" name="demo" value="1"<?= ($posted ? ! empty($_POST['demo']) : ($data['demo'] ?? false)) ? ' checked' : '' ?>>
                        <span>Add demo data <span class="hint">- eight sample colleagues with posts, drives and chats, all with the password "password". For trying it out only.</span></span></label>
                </fieldset>

                <div class="actions">
                    <a class="link" href="?step=database">Back</a>
                    <button class="btn" type="submit">Continue</button>
                </div>
            </form>

        <?php elseif ($step === 'install'): ?>
            <?php if (empty($_SESSION['db_ok']) || empty($_SESSION['admin_password'])): ?>
                <h1>Almost there</h1>
                <p class="lead">Some details are missing - please go back through the previous steps.</p>
                <div class="actions"><span></span><a class="btn" href="?step=database">Back to database</a></div>
            <?php else: ?>
                <h1>Ready to install</h1>
                <p class="lead">Check the details below. Installing creates the tables and your administrator account.</p>
                <dl>
                    <dt>Site</dt><dd><?= e($data['app_name']) ?></dd>
                    <dt>URL</dt><dd><?= e($data['app_url']) ?></dd>
                    <dt>Time zone</dt><dd><?= e($data['timezone']) ?></dd>
                    <dt>Database</dt><dd><?= e($data['db_username'].'@'.$data['db_host'].':'.$data['db_port'].' / '.$data['db_database']) ?></dd>
                    <dt>Administrator</dt><dd>@<?= e($data['admin_username']) ?></dd>
                    <dt>Demo data</dt><dd><?= ! empty($data['demo']) ? 'Yes' : 'No' ?></dd>
                </dl>
                <?php if (! empty($data['fresh'])): ?>
                    <div class="alert warn" style="margin-top:16px">Every existing table in <strong><?= e($data['db_database']) ?></strong> will be dropped.</div>
                <?php endif; ?>
                <?php if (! empty($_SESSION['install_log'])): ?>
                    <pre><?= e(implode("\n", $_SESSION['install_log'])) ?></pre>
                <?php endif; ?>
                <form method="post" action="?step=install" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Installing...';">
                    <?= $csrf ?>
                    <div class="actions">
                        <a class="link" href="?step=site">Back</a>
                        <button class="btn" type="submit">Install SaverMate</button>
                    </div>
                </form>
            <?php endif; ?>

        <?php elseif ($step === 'done'): ?>
            <?php $result = $_SESSION['result'] ?? null; ?>
            <h1>SaverMate is installed</h1>
            <?php if ($result): ?>
                <p class="lead">Sign in as <strong>@<?= e($result['username']) ?></strong> with the password you chose.
                    The admin panel is in the menu once you are signed in.</p>
                <?php foreach ($result['warnings'] as $warning): ?>
                    <div class="alert warn"><?= e($warning) ?></div>
                <?php endforeach; ?>
                <div class="alert good">The installer has locked itself. For extra safety you can delete the <code>public/install</code> folder.</div>
                <pre><?= e(implode("\n", $result['log'])) ?></pre>
                <div class="actions"><span></span><a class="btn" href="<?= e($result['url']) ?>/login">Sign in</a></div>
            <?php else: ?>
                <p class="lead">The installer has finished.</p>
                <div class="actions"><span></span><a class="btn" href="../">Open SaverMate</a></div>
            <?php endif; ?>

        <?php else: ?>
            <h1>Page not found</h1>
            <div class="actions"><span></span><a class="btn" href="?step=requirements">Start</a></div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
