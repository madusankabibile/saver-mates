<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\DriveController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\GroupChatController;
use App\Http\Controllers\MovieCommentController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\SoftwareController;
use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicDriveController;
use App\Http\Controllers\ReactionController;
use App\Http\Controllers\SongController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
| The newsfeed, member profiles and the public network drive are readable by
| intranet guests who have not signed in.
*/

Route::get('/', [FeedController::class, 'index'])->name('feed.index');
Route::get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');
Route::get('/avatar/{user}.svg', [AvatarController::class, 'initials'])->name('avatar.initials');
Route::get('/u/{user:username}', [ProfileController::class, 'show'])->name('profile.show');

Route::get('/network-drive', [PublicDriveController::class, 'index'])->name('drive.public');
Route::get('/files/{file}/preview', [DriveController::class, 'preview'])->name('drive.files.preview');
Route::get('/files/{file}/download', [DriveController::class, 'download'])->name('drive.files.download');

/* Movies and music are public to watch and listen to. Counters (views,
   plays) are posted by guests too, so they sit outside the auth group. */
Route::get('/movies', [MovieController::class, 'index'])->name('movies.index');
Route::get('/movies/{movie}', [MovieController::class, 'show'])->whereNumber('movie')->name('movies.show');
Route::post('/movies/{movie}/view', [MovieController::class, 'view'])->whereNumber('movie')->name('movies.view');

// Software — public browsing and detail pages
Route::get('/software', [SoftwareController::class, 'index'])->name('software.index');
Route::get('/software/{software}', [SoftwareController::class, 'show'])->whereNumber('software')->name('software.show');
Route::post('/software/{software}/download', [SoftwareController::class, 'download'])->whereNumber('software')->name('software.download');

Route::get('/songs', [SongController::class, 'index'])->name('songs.index');
Route::get('/songs/artists', [SongController::class, 'artistIndex'])->name('songs.artists');
Route::post('/songs/{song}/play', [SongController::class, 'play'])->whereNumber('song')->name('songs.play');
Route::get('/songs/playlists', [PlaylistController::class, 'index'])->name('playlists.index');
Route::get('/songs/playlists/{playlist}', [PlaylistController::class, 'show'])->whereNumber('playlist')->name('playlists.show');
Route::post('/songs/playlists/{playlist}/play', [PlaylistController::class, 'play'])->whereNumber('playlist')->name('playlists.play');

/*
|--------------------------------------------------------------------------
| Guest routes
|--------------------------------------------------------------------------
*/

/* Sign-in and registration happen in a popup on every page. The GET
   addresses only open that popup over the feed (the auth middleware still
   sends guests to route('login')). */
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:6,1');

    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');
});

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::post('/heartbeat', [DirectoryController::class, 'heartbeat'])->name('heartbeat');

    /* Posts, reactions and threaded comments -------------------------- */
    Route::post('/posts', [PostController::class, 'store'])->name('posts.store');
    Route::post('/posts/preview', [PostController::class, 'preview'])->name('posts.preview');
    Route::post('/posts/upload-image', [PostController::class, 'uploadImage'])->name('posts.upload-image');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
    Route::post('/posts/{post}/reactions', [ReactionController::class, 'toggle'])->name('reactions.toggle');
    Route::post('/posts/{post}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

    /* Colleague directory --------------------------------------------- */
    Route::get('/members', [DirectoryController::class, 'index'])->name('directory.index');
    Route::get('/members/presence', [DirectoryController::class, 'presence'])->name('directory.presence');

    /* Own profile ------------------------------------------------------ */
    Route::get('/settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/settings/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    /* Direct chat ------------------------------------------------------ */
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/unread', [ChatController::class, 'unread'])->name('chat.unread');
    Route::get('/chat/drive-picker', [ChatController::class, 'drivePicker'])->name('chat.picker');
    Route::get('/chat/people', [GroupChatController::class, 'people'])->name('chat.people');
    Route::get('/chat/with/{user}', [ChatController::class, 'with'])->name('chat.with');
    Route::get('/chat/{conversation}', [ChatController::class, 'show'])->name('chat.show');
    Route::get('/chat/{conversation}/poll', [ChatController::class, 'poll'])->name('chat.poll');
    Route::post('/chat/{conversation}/messages', [ChatController::class, 'send'])->name('chat.send');
    Route::get('/chat/{conversation}/messages/{message}/info', [ChatController::class, 'info'])->name('chat.messages.info');
    Route::post('/chat/{conversation}/messages/{message}', [ChatController::class, 'update'])->name('chat.messages.update');
    Route::delete('/chat/{conversation}/messages/{message}', [ChatController::class, 'destroyMessage'])->name('chat.messages.destroy');
    Route::get('/chat/attachments/{attachment}', [ChatController::class, 'attachment'])->name('chat.attachment');
    Route::get('/chat/attachments/{attachment}/inline', [ChatController::class, 'attachmentInline'])->name('chat.attachment.inline');

    /* Group chats ------------------------------------------------------- */
    Route::post('/chat/groups', [GroupChatController::class, 'store'])->name('chat.groups.store');
    Route::post('/chat/{conversation}/group', [GroupChatController::class, 'update'])->name('chat.groups.update');
    Route::post('/chat/{conversation}/members', [GroupChatController::class, 'addMembers'])->name('chat.groups.members.add');
    Route::delete('/chat/{conversation}/members/{user}', [GroupChatController::class, 'removeMember'])->name('chat.groups.members.remove');
    Route::post('/chat/{conversation}/members/{user}/role', [GroupChatController::class, 'role'])->name('chat.groups.members.role');
    Route::post('/chat/{conversation}/mute', [GroupChatController::class, 'mute'])->name('chat.groups.mute');
    Route::post('/chat/{conversation}/leave', [GroupChatController::class, 'leave'])->name('chat.groups.leave');
    Route::delete('/chat/{conversation}', [GroupChatController::class, 'destroy'])->name('chat.groups.destroy');

    /* Personal cloud drive --------------------------------------------- */
    Route::get('/drive', [DriveController::class, 'index'])->name('drive.index');
    Route::get('/drive/folders/{folder}', [DriveController::class, 'index'])->name('drive.folder');
    Route::post('/drive/folders', [DriveController::class, 'storeFolder'])->name('drive.folders.store');
    Route::post('/drive/folders/{folder}/rename', [DriveController::class, 'renameFolder'])->name('drive.folders.rename');
    Route::post('/drive/folders/{folder}/visibility', [DriveController::class, 'toggleFolder'])->name('drive.folders.visibility');
    Route::delete('/drive/folders/{folder}', [DriveController::class, 'destroyFolder'])->name('drive.folders.destroy');

    Route::post('/drive/files', [DriveController::class, 'storeFile'])->name('drive.files.store');
    Route::post('/drive/files/{file}/rename', [DriveController::class, 'renameFile'])->name('drive.files.rename');
    Route::post('/drive/files/{file}/visibility', [DriveController::class, 'toggleFile'])->name('drive.files.visibility');
    Route::delete('/drive/files/{file}', [DriveController::class, 'destroyFile'])->name('drive.files.destroy');

    /* Alerts ------------------------------------------------------------ */
    Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::get('/alerts/feed', [AlertController::class, 'feed'])->name('alerts.feed');
    Route::post('/alerts/read', [AlertController::class, 'markAllRead'])->name('alerts.read');

    /* Large uploads arrive in chunks ------------------------------------ */
    Route::post('/uploads/chunks', [UploadController::class, 'chunk'])->name('uploads.chunk');

    /* Software ---------------------------------------------------------- */
    Route::get('/software/upload', [SoftwareController::class, 'create'])->name('software.create');
    Route::post('/software', [SoftwareController::class, 'store'])->name('software.store');
    Route::get('/software/lookup', [SoftwareController::class, 'lookup'])->middleware('throttle:30,1')->name('software.lookup');
    Route::get('/software/{software}/edit', [SoftwareController::class, 'edit'])->whereNumber('software')->name('software.edit');
    Route::post('/software/{software}', [SoftwareController::class, 'update'])->whereNumber('software')->name('software.update');
    Route::delete('/software/{software}', [SoftwareController::class, 'destroy'])->whereNumber('software')->name('software.destroy');
    Route::post('/software/{software}/like', [SoftwareController::class, 'like'])->whereNumber('software')->name('software.like');

    /* Movies ------------------------------------------------------------ */
    Route::get('/movies/upload', [MovieController::class, 'create'])->name('movies.create');
    Route::post('/movies', [MovieController::class, 'store'])->name('movies.store');
    Route::get('/movies/lookup', [MovieController::class, 'lookup'])->middleware('throttle:30,1')->name('movies.lookup');
    Route::get('/movies/{movie}/edit', [MovieController::class, 'edit'])->name('movies.edit');
    Route::post('/movies/{movie}', [MovieController::class, 'update'])->name('movies.update');
    Route::delete('/movies/{movie}', [MovieController::class, 'destroy'])->name('movies.destroy');
    Route::post('/movies/{movie}/thumbnail', [MovieController::class, 'thumbnail'])->name('movies.thumbnail');
    Route::post('/movies/{movie}/like', [MovieController::class, 'like'])->name('movies.like');
    Route::post('/movies/{movie}/comments', [MovieCommentController::class, 'store'])->name('movie-comments.store');
    Route::delete('/movie-comments/{comment}', [MovieCommentController::class, 'destroy'])->name('movie-comments.destroy');

    /* Songs and playlists ----------------------------------------------- */
    Route::get('/songs/upload', [SongController::class, 'create'])->name('songs.create');
    Route::post('/songs', [SongController::class, 'store'])->name('songs.store');
    Route::get('/songs/{song}/edit', [SongController::class, 'edit'])->name('songs.edit');
    Route::get('/songs/{song}/lookup', [SongController::class, 'lookup'])->name('songs.lookup');
    Route::post('/songs/{song}/lookup', [SongController::class, 'applyLookup'])->name('songs.lookup.apply');
    Route::post('/songs/{song}', [SongController::class, 'update'])->name('songs.update');
    Route::delete('/songs/{song}', [SongController::class, 'destroy'])->name('songs.destroy');
    Route::post('/songs/{song}/like', [SongController::class, 'like'])->name('songs.like');

    Route::post('/songs/playlists', [PlaylistController::class, 'store'])->name('playlists.store');
    Route::post('/songs/playlists/{playlist}', [PlaylistController::class, 'update'])->name('playlists.update');
    Route::delete('/songs/playlists/{playlist}', [PlaylistController::class, 'destroy'])->name('playlists.destroy');
    Route::post('/songs/playlists/{playlist}/songs', [PlaylistController::class, 'addSong'])->name('playlists.songs.add');
    Route::delete('/songs/playlists/{playlist}/songs/{song}', [PlaylistController::class, 'removeSong'])->name('playlists.songs.remove');
    Route::post('/songs/playlists/{playlist}/order', [PlaylistController::class, 'reorder'])->name('playlists.reorder');
});

/*
|--------------------------------------------------------------------------
| Admin panel
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    /* Members ------------------------------------------------------------ */
    Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::get('/users/export', [Admin\UserController::class, 'export'])->name('users.export');
    Route::post('/users', [Admin\UserController::class, 'store'])->name('users.store');
    Route::post('/users/bulk', [Admin\UserController::class, 'bulk'])->name('users.bulk');
    Route::get('/users/{user}', [Admin\UserController::class, 'show'])->name('users.show');
    Route::post('/users/{user}', [Admin\UserController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/password', [Admin\UserController::class, 'password'])->name('users.password');
    Route::post('/users/{user}/suspend', [Admin\UserController::class, 'suspend'])->name('users.suspend');
    Route::post('/users/{user}/unsuspend', [Admin\UserController::class, 'unsuspend'])->name('users.unsuspend');
    Route::delete('/users/{user}', [Admin\UserController::class, 'destroy'])->name('users.destroy');

    /* Content moderation -------------------------------------------------- */
    Route::get('/content/{type}', [Admin\ContentController::class, 'index'])
        ->whereIn('type', Admin\ContentController::TYPES)->name('content.index');
    Route::post('/content/{type}/bulk-delete', [Admin\ContentController::class, 'bulkDestroy'])
        ->whereIn('type', Admin\ContentController::TYPES)->name('content.bulk');
    Route::delete('/content/{type}/{id}', [Admin\ContentController::class, 'destroy'])
        ->whereIn('type', Admin\ContentController::TYPES)->whereNumber('id')->name('content.destroy');
    Route::post('/content/files/{file}/visibility', [Admin\ContentController::class, 'toggleFile'])->name('content.files.visibility');

    /* Site settings, announcements and the audit log ---------------------- */
    Route::get('/settings', [Admin\SettingsController::class, 'edit'])->name('settings');
    Route::post('/settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
    Route::post('/broadcast', [Admin\SettingsController::class, 'broadcast'])->name('broadcast');
    Route::get('/activity', [Admin\SettingsController::class, 'activity'])->name('activity');
});
