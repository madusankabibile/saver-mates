# SaverMate

**A self-hosted social workspace for your local network.** A shared newsfeed, a
colleague directory with live presence, private and group chat with file transfer,
a personal cloud drive that can publish to a network-wide drive, and a built-in
movie and music library. Everything runs on one ordinary PHP + MySQL server, with
no internet connection needed.

![PHP](https://img.shields.io/badge/PHP-8.1%2B-777bb4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-10-ff2d20?logo=laravel&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL%20%2F%20MariaDB-supported-4479a1?logo=mysql&logoColor=white)
![No build step](https://img.shields.io/badge/Node%20build-not%20required-07b37f)

## Screenshots

<table>
  <tr>
    <td><img src="storage/sc/Screenshot 2026-10-26 105827.png" alt="Newsfeed"/><br/><sub><b>Newsfeed</b> — post updates, photos and articles</sub></td>
    <td><img src="storage/sc/Screenshot 2026-10-26 105855.png" alt="Post detail"/><br/><sub><b>Post detail</b> — reactions, comments and sharing</sub></td>
  </tr>
  <tr>
    <td><img src="storage/sc/Screenshot 2026-10-26 105910.png" alt="Movies library"/><br/><sub><b>Movies</b> — portrait poster grid with search and categories</sub></td>
    <td><img src="storage/sc/Screenshot 2026-10-26 105803.png" alt="Movie player"/><br/><sub><b>Movie player</b> — theater mode, subtitles and Up Next queue</sub></td>
  </tr>
  <tr>
    <td><img src="storage/sc/Screenshot 2026-10-26 105942.png" alt="Music library"/><br/><sub><b>Music</b> — artist browse, queue and persistent player</sub></td>
    <td><img src="storage/sc/Screenshot 2026-10-26 110020.png" alt="Chat"/><br/><sub><b>Chat</b> — private messages with drive file sharing</sub></td>
  </tr>
  <tr>
    <td><img src="storage/sc/Screenshot 2026-10-26 110000.png" alt="My Drive"/><br/><sub><b>My Drive</b> — private cloud storage with publish-to-network</sub></td>
    <td><img src="storage/sc/Screenshot 2026-10-26 105923.png" alt="Movies browsing"/><br/><sub><b>Movies</b> — filter by category, sort by views or likes</sub></td>
  </tr>
</table>

SaverMate is built for offices, hospitals, schools and labs that want an intranet
they can run themselves. It is stock Laravel 10 with Blade views, hand-written CSS
and vanilla JavaScript. It needs no Node build step and no WebSocket server, and
uses no Composer packages beyond what `laravel/laravel` ships with. If XAMPP runs
on the machine, SaverMate will too.

---

## Features

**Social**
- Public newsfeed (readable by guests) with quick posts with up to 4 photos,
  Markdown articles with live preview, and colored text posts
- Six reaction types and threaded comments
- Profiles with feed, shared files, about, skills and statistics tabs
- Searchable colleague directory with live online indicators
- Notifications and unread badges

**Chat**
- Private 1-on-1 and group conversations, with group admins and members
- Send files from your device, or attach straight from your drive without uploading again
- Edit and remove messages

**Drive**
- Personal cloud drive with nested folders, upload, preview, rename and download
- Private by default, with a one-click switch to publish a file or a whole folder
- Searchable public network drive

**Movies & music**
- Upload large movies and songs in the browser (chunked uploads, no `php.ini` changes)
- Watch page with resume, theater mode, subtitles (with sync adjustment), likes,
  comments and "Up next"
- Music player that keeps playing while you browse, with queue, shuffle, repeat,
  sleep timer and media keys
- Shared playlists, artist pages, liked songs, and ID3 tag and cover-art reading
- Bulk import from a folder or USB drive with `php artisan media:import`
- No ffmpeg required

**Administration**
- Dashboard, user management (create, suspend, reset password, bulk actions, CSV export)
- Content moderation across posts, comments, files, movies and songs
- Open or close self-registration, and show a site-wide announcement banner
- Activity log of admin actions

---

## Requirements

- PHP **8.1+** with `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`,
  `ctype`, `json`, `fileinfo`, `curl`, `dom` (and `gd`, recommended for photo resizing)
- MySQL 5.7+ or MariaDB 10.3+
- Apache with `mod_rewrite` and `AllowOverride All` (XAMPP, WAMP, Laragon and most
  shared hosts work out of the box)

---

## Installation

### Option 1: Release zip (recommended)

1. Download the latest `savermate-x.y.z.zip` from
   [Releases](../../releases) and unzip it into your web root, e.g.
   `C:\xampp\htdocs\savermate` or `/var/www/savermate`.
2. On Linux, let the web server write to `storage/` and `bootstrap/cache/`:
   ```bash
   chmod -R 775 storage bootstrap/cache
   chown -R www-data:www-data .   # your web server's user
   ```
3. Open `http://your-server/savermate` in a browser. The **install wizard** checks
   your server, connects to (or creates) the database, and asks for the site name,
   time zone and your administrator account. It then sets everything up.

The release already includes its dependencies, so you don't need Composer or Node.
More detail and troubleshooting are in [INSTALL.md](INSTALL.md).

### Option 2: From source

```bash
git clone https://github.com/<you>/savermate.git
cd savermate
composer install
```

Then open the folder in a browser and the same install wizard runs.

Or set it up by hand:

```bash
cp .env.example .env            # then set DB_* and APP_URL
php artisan key:generate
php artisan migrate --seed      # --seed adds demo data (optional)
php artisan storage:link
php -r "file_put_contents('storage/installed', 'manual');"
```

`storage/installed` tells SaverMate the setup is done. Until that file exists,
every request goes to the installer.

### Demo data

If you choose demo data in the installer, or run `--seed`, you get eight sample
colleagues with posts, drives and conversations. Each one's password is
`password`:

`nimal` (has the most data) · `sanduni` · `ruwan` · `dilani` · `ayesha` · `kasun` · `tharindu` · `menaka`

Open two browsers signed in as different people to watch presence, chat and
notifications update live. Reset the demo at any time with
`php artisan migrate:fresh --seed`. **This wipes the database.**

### A cleaner URL

Laravel is served from `public/`, so a subfolder install lives at
`http://your-server/savermate/public` (opening the folder itself redirects there).
To get a clean address, point a virtual host or your hosting panel's document root
at `public/`:

```apache
<VirtualHost *:80>
    ServerName savermate.local
    DocumentRoot "/path/to/savermate/public"
    <Directory "/path/to/savermate/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Add `127.0.0.1 savermate.local` to your hosts file (or a DNS entry on your
network), set `APP_URL=http://savermate.local` in `.env`, and restart Apache.

---

## Building a release

```bash
php build/make-release.php 1.0.0
```

This writes `dist/savermate-1.0.0.zip` (about 7 MB). It is a clean copy without
`.env`, uploads, logs, caches, tests or dev Composer packages. It works offline,
because it strips the dev packages from the existing `vendor/` instead of
downloading anything. Attach the zip to a GitHub release.

---

## How it works

### Project layout

```
app/Models/              User, Post, Reaction, Comment, Conversation, Message,
                         DriveFolder, DriveFile, Alert, Movie, Song, Playlist, Setting, ...
app/Http/Controllers/    One controller per feature area; Admin/ for the admin panel
app/Http/Middleware/     TrackPresence (stamps last_seen_at), BlockSuspended, EnsureAdmin
app/Support/             ImageStore, ChunkedUpload, MediaLibrary, MediaMeta, SongTags, Subtitles
app/Console/Commands/    ImportMedia (php artisan media:import)
resources/views/         Blade templates; components/icon.blade.php is the SVG icon set
public/css/app.css       The whole design system, hand written
public/js/app.js         All front-end behaviour, vanilla JS (helpers on window.SM)
public/js/uploader.js    Chunked uploader shared by movies and songs
public/js/movies.js      Watch page, upload page, watched-progress bars
public/js/music.js       Music player, playlists, in-section navigation
public/install/          Stand-alone install wizard (runs before Laravel can boot)
build/make-release.php   Release zip builder
```

Every fetch in `app.js` is built from the `base-url` meta tag, because the app may
be served from a subfolder. Never hard-code a leading-slash URL there.

Elements are shown and hidden with the `hidden` attribute. A global
`[hidden] { display: none !important }` rule in `app.css` makes it override the
component classes. Do not remove that rule.

### "Real time" without a WebSocket server

The browser polls small JSON endpoints, and a heartbeat keeps presence fresh:

| What                 | Endpoint                  | Interval |
|----------------------|---------------------------|----------|
| New chat messages    | `/chat/{id}/poll?after=N` | 3 s      |
| Who is online        | `/members/presence`       | 20 s     |
| Notification bell    | `/alerts/feed`            | 20 s     |
| Unread message badge | `/chat/unread`            | 20 s     |
| "I am still here"    | `/heartbeat`              | 45 s     |

Polling pauses while a tab is in the background. A member counts as online for
two minutes after their last request (`User::ONLINE_WINDOW_MINUTES`).
`TrackPresence` writes at most once a minute, so the 3-second chat poll doesn't
hammer the `users` table. To move to true push later, broadcast when a message or
alert is created and replace the poll calls in `app.js` with channel
subscriptions.

### File privacy

There are two storage disks, on purpose:

- **`storage/app/public`** (linked to `public/storage`, web-readable) holds only
  genuinely public media: avatars, covers, newsfeed photos, movies and music.
- **`storage/app`** (not web-reachable) holds every drive file and chat attachment.

Private content is never served by Apache directly. Every read goes through a
controller that checks permission first. A drive file is served only if it is
public or belongs to the person asking. A chat attachment is served only to the
members of that conversation. Making a folder public also makes every folder and
file inside it public, so the network drive never shows a half-shared tree. In
subfolder installs, the root `.htaccess` blocks everything outside `public/`.

### Articles

Articles are stored as Markdown and rendered with `league/commonmark`, which the
framework already includes. `Post::MARKDOWN` sets `html_input => strip` and
`allow_unsafe_links => false`, so raw HTML, event handlers and `javascript:` links
are stripped when the article is rendered. The editor preview uses the same
renderer, so what you see while writing is exactly what gets published.

### Movies and music

- **Streaming:** Media lives on the public disk, so Apache streams it directly and
  answers HTTP Range requests. That is what lets you seek instantly inside a 2 GB
  file.
- **Chunked uploads:** `uploader.js` posts 8 MB pieces to `/uploads/chunks`
  (retrying any piece that fails). `ChunkedUpload::assemble` joins them, and
  `MediaLibrary` checks the result really is video or audio before saving it.
- **No ffmpeg:** `MediaMeta` reads durations from MP4 `mvhd` boxes and MP3
  Xing/VBRI headers. `SongTags` reads ID3 tags and cover art. Thumbnails are
  captured in the uploader's browser from a hidden `<video>`, and `.srt` subtitles
  are converted to WebVTT on upload.
- **Formats:** MP4 (H.264 + AAC) plays in every browser. For MKV or HEVC files,
  the watch page warns that they may not play and offers a download.
- **Uninterrupted playback:** `music.js` handles navigation inside `/songs` itself
  and swaps only the middle panel, so the player keeps going. The queue, position
  and settings survive a reload.

Bulk import:

```bash
php artisan media:import movies "/media/Movies" --user=admin --limit=8
php artisan media:import songs  "/media/Songs"  --user=admin --per-folder=6
```

---

## Design

The app uses a light theme. Emerald (`#07b37f`) carries the brand, coral
(`#ff6b5d`) marks anything unread or destructive, and amber (`#f8b24a`) marks the
drive, all on a soft paper background (`#eff3f1`). Every surface has a 6px radius.
Display text is set in Outfit and body text in IBM Plex Sans, with a Segoe UI /
system fallback so it still looks right on a network with no internet access.

Visual cues carry state rather than decoration: a jade ring means online, a coral
dot means unread, a colored chip shows a file's type, and a padlock or globe shows
privacy. The layout works down to phone size, where the left rail becomes a bottom
tab bar and the music player becomes a mini bar. Keyboard focus is always visible,
`prefers-reduced-motion` is respected, and destructive actions use in-page dialogs
instead of `confirm()`.

---

## Contributing

Issues and pull requests are welcome. Please keep to the project's constraints:
no Node build step, no new Composer dependencies unless they are really needed,
and front-end code in plain JavaScript that works from a subfolder.
