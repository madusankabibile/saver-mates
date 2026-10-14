# Installing SaverMate

## What you need

- PHP 8.1 or newer with these extensions: pdo_mysql, mbstring, openssl, tokenizer,
  xml, ctype, json, fileinfo, curl, dom (gd is recommended for photo resizing)
- MySQL 5.7+ or MariaDB 10.3+
- Apache with `mod_rewrite` and `AllowOverride All` (XAMPP, WAMP, most shared hosts)

Composer and Node are **not** needed - everything is already in the zip.

## Install

1. Unzip the release into your web root, e.g. `C:\xampp\htdocs\savermate` or
   `/var/www/savermate`. You can rename the folder.
2. On Linux, let the web server write to these folders:

   ```bash
   chmod -R 775 storage bootstrap/cache
   chown -R www-data:www-data storage bootstrap/cache .   # use your web server's user
   ```

3. Open the folder in a browser, e.g. `http://localhost/savermate`.
   You are sent to the installer, which:
   - checks the server,
   - connects to the database (and creates it if it does not exist),
   - asks for the site name, address, time zone and your administrator account,
   - creates the tables and signs you up as the first administrator.
4. Sign in. The admin panel is in the menu.

When it finishes, the installer locks itself (it writes `storage/installed`).
You can also delete the `public/install` folder.

## A cleaner address

Out of the box the site lives at `http://your-server/savermate/public`. For
`http://savermate.local` or a real domain, point a virtual host (or your hosting
panel's "document root") at the **`public`** folder, then change `APP_URL` in `.env`
to the new address:

```apache
<VirtualHost *:80>
    ServerName savermate.local
    DocumentRoot "C:/xampp/htdocs/savermate/public"
    <Directory "C:/xampp/htdocs/savermate/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

## Uploads of large movies and songs

Movies and songs upload in chunks, so PHP's `upload_max_filesize` does not need
to be huge, but `post_max_size` should be at least 10M.

## Troubleshooting

| Problem | Fix |
|---|---|
| "Missing" next to a writable folder | Give the web server write permission (step 2). |
| Avatars and photos do not show | `public/storage` could not be created. Run `php artisan storage:link` in the project folder. |
| Every page is 404 | `mod_rewrite` is off or `AllowOverride` is `None`. |
| Need to reinstall | Delete `storage/installed`, then open the site again. Reinstalling into the same database can wipe it. |
