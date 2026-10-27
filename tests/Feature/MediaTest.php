<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use App\Support\SongTags;
use App\Support\Subtitles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MediaTest extends TestCase
{
    protected array $users = [];

    /** Fake song-details catalogue: path => response. Unknown songs find nothing. */
    protected array $catalogue = [];

    protected function setUp(): void
    {
        parent::setUp();

        \App\Support\SongLookup::flush();
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            $key = parse_url($request->url(), PHP_URL_HOST).parse_url($request->url(), PHP_URL_PATH);

            return $this->catalogue[$key] ?? Http::response(['data' => [], 'results' => [], 'total' => 0]);
        });
    }

    /** A catalogue that knows one song, its album genre and its cover art. */
    protected function knowSong(string $title, string $artist, int $duration = 3): void
    {
        $img = imagecreatetruecolor(60, 60);
        ob_start();
        imagejpeg($img);
        $jpeg = ob_get_clean();

        $track = [
            'id' => 9001, 'title' => $title, 'title_short' => $title, 'duration' => $duration, 'rank' => 5000,
            'artist' => ['name' => $artist],
            'album' => ['id' => 777, 'title' => $title.' (Single)', 'cover_xl' => 'https://cdn.test/cover.jpg', 'cover_medium' => 'https://cdn.test/cover-m.jpg'],
        ];

        $this->catalogue = [
            'api.deezer.com/search' => Http::response(['data' => [
                // A remix by the same artist ranks higher but should lose to the original.
                ['title' => $title.' (Remix)', 'title_short' => $title.' (Remix)', 'rank' => 900000] + $track,
                $track,
            ]]),
            'api.deezer.com/track/9001' => Http::response($track),
            'api.deezer.com/album/777' => Http::response(['genres' => ['data' => [['name' => 'Asian Music']]]]),
            'cdn.test/cover.jpg' => Http::response($jpeg, 200, ['Content-Type' => 'image/jpeg']),
        ];
    }

    protected function tearDown(): void
    {
        foreach ($this->users as $user) {
            $user->delete(); // cascades to movies, songs, playlists, likes
        }

        parent::tearDown();
    }

    protected function member(): User
    {
        return $this->users[] = User::factory()->create();
    }

    /** Send $bytes to the chunk endpoint in pieces; returns [upload_id, total_chunks]. */
    protected function uploadInChunks(User $user, string $bytes, int $pieceSize): array
    {
        $id = (string) Str::uuid();
        $pieces = str_split($bytes, $pieceSize);

        foreach ($pieces as $i => $piece) {
            $this->actingAs($user)
                ->post(route('uploads.chunk'), [
                    'upload_id' => $id,
                    'index' => $i,
                    'chunk' => UploadedFile::fake()->createWithContent('chunk', $piece),
                ])
                ->assertOk();
        }

        return [$id, count($pieces)];
    }

    /** A tiny but valid ISO media file: ftyp + moov/mvhd declaring 125 seconds. */
    protected function fakeMp4(): string
    {
        $ftyp = pack('N', 20).'ftypisom'.pack('N', 0x200).'isom';
        $mvhd = "\0\0\0\0".pack('N', 0).pack('N', 0).pack('N', 1000).pack('N', 125000).str_repeat("\0", 80);
        $mvhd = pack('N', 8 + strlen($mvhd)).'mvhd'.$mvhd;
        $moov = pack('N', 8 + strlen($mvhd)).'moov'.$mvhd;
        $mdat = pack('N', 8 + 4000).'mdat'.random_bytes(4000);

        return $ftyp.$mdat.$moov; // moov at the end, like many real files
    }

    /** ID3v2.3 tag (title, artist) followed by 3 seconds of 128 kbps MPEG-1 Layer III frames. */
    protected function fakeMp3(string $title, string $artist): string
    {
        $frame = fn ($id, $text) => $id.pack('N', strlen($text) + 1)."\0\0"."\0".$text;
        $frames = $frame('TIT2', $title).$frame('TPE1', $artist);

        $size = strlen($frames);
        $syncsafe = chr(($size >> 21) & 0x7F).chr(($size >> 14) & 0x7F).chr(($size >> 7) & 0x7F).chr($size & 0x7F);
        $tag = 'ID3'."\x03\x00\x00".$syncsafe.$frames;

        // 128 kbps, 44.1 kHz, no padding: 417 bytes per frame, 1152 samples per frame.
        $audioFrame = "\xFF\xFB\x90\x64".str_repeat("\0", 413);
        $frameCount = (int) ceil(3 * 44100 / 1152);

        return $tag.str_repeat($audioFrame, $frameCount);
    }

    /* ---------------------------------------------------------------- */
    /* Movies                                                            */
    /* ---------------------------------------------------------------- */

    public function test_movie_uploads_in_chunks_with_converted_subtitles(): void
    {
        Storage::fake('public');
        $user = $this->member();

        [$uploadId, $total] = $this->uploadInChunks($user, $this->fakeMp4(), 1500);

        $srt = "1\r\n00:00:01,000 --> 00:00:02,500\r\nHello there\r\n\r\n2\r\n00:00:03,000 --> 00:00:04,000\r\nCaf\xE9\r\n";

        $response = $this->actingAs($user)->post(route('movies.store'), [
            'upload_id' => $uploadId,
            'total_chunks' => $total,
            'filename' => 'Test.Movie.2021.720p.mp4',
            'title' => 'Test Movie (2021)',
            'description' => 'A test upload.',
            'category' => 'Drama',
            'thumbnail' => UploadedFile::fake()->image('thumb.jpg', 640, 360),
            'subtitles' => [UploadedFile::fake()->createWithContent('Test.Movie.sinhala.srt', $srt)],
            'subtitle_langs' => [''],
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonStructure(['url']);

        $movie = Movie::where('user_id', $user->id)->with('subtitles')->firstOrFail();
        $this->assertSame('video/mp4', $movie->video_mime);
        $this->assertSame(125, $movie->duration, 'Duration is read from the mvhd box at the end of the file.');
        $this->assertSame(strlen($this->fakeMp4()), $movie->video_size);
        Storage::disk('public')->assertExists($movie->video_path);
        Storage::disk('public')->assertExists($movie->thumbnail_path);

        $sub = $movie->subtitles->first();
        $this->assertSame('si', $sub->lang);
        $this->assertSame('Sinhala', $sub->label);
        $vtt = Storage::disk('public')->get($sub->path);
        $this->assertStringStartsWith('WEBVTT', $vtt);
        $this->assertStringContainsString('00:00:01.000 --> 00:00:02.500', $vtt);
        $this->assertStringContainsString('Café', $vtt, 'Windows-1252 text is converted to UTF-8.');

        $this->get(route('movies.show', $movie))
            ->assertOk()
            ->assertSee('Test Movie (2021)')
            ->assertSee('<track kind="subtitles"', false);

        $this->get(route('movies.index', ['category' => 'Drama']))->assertOk()->assertSee('Test Movie (2021)');
    }

    public function test_upload_rejects_files_that_are_not_video(): void
    {
        Storage::fake('public');
        $user = $this->member();

        [$uploadId, $total] = $this->uploadInChunks($user, str_repeat('not a video ', 200), 1000);

        $this->actingAs($user)->postJson(route('movies.store'), [
            'upload_id' => $uploadId,
            'total_chunks' => $total,
            'filename' => 'sneaky.mp4',
            'title' => 'Sneaky',
        ])->assertStatus(422);

        $this->assertSame(0, Movie::where('user_id', $user->id)->count());
        $this->assertEmpty(Storage::disk('public')->allFiles('movies/videos'));
    }

    public function test_incomplete_upload_is_rejected(): void
    {
        Storage::fake('public');
        $user = $this->member();

        [$uploadId] = $this->uploadInChunks($user, $this->fakeMp4(), 1500);

        $this->actingAs($user)->postJson(route('movies.store'), [
            'upload_id' => $uploadId,
            'total_chunks' => 9, // more pieces than were sent
            'filename' => 'movie.mp4',
            'title' => 'Half a movie',
        ])->assertStatus(422)->assertJsonValidationErrors('upload_id');
    }

    public function test_guests_can_watch_but_not_upload(): void
    {
        $this->post(route('uploads.chunk'), [])->assertRedirect(route('login'));
        $this->get(route('movies.create'))->assertRedirect(route('login'));
        $this->get(route('movies.index'))->assertOk();
    }

    public function test_views_count_once_per_session_and_only_owner_edits(): void
    {
        Storage::fake('public');
        $owner = $this->member();
        $other = $this->member();

        $movie = Movie::create([
            'user_id' => $owner->id, 'title' => 'Counted', 'video_path' => 'movies/videos/x.mp4',
            'video_mime' => 'video/mp4',
        ]);

        $this->post(route('movies.view', $movie))->assertOk()->assertJson(['views' => 1]);
        $this->post(route('movies.view', $movie))->assertOk()->assertJson(['views' => 1]);

        $this->actingAs($other)->post(route('movies.like', $movie))->assertJson(['liked' => true, 'count' => 1]);
        $this->actingAs($other)->post(route('movies.like', $movie))->assertJson(['liked' => false, 'count' => 0]);

        $this->actingAs($other)->get(route('movies.edit', $movie))->assertForbidden();
        $this->actingAs($other)->delete(route('movies.destroy', $movie))->assertForbidden();
        $this->actingAs($owner)->delete(route('movies.destroy', $movie))->assertRedirect(route('movies.index'));
        $this->assertModelMissing($movie);
    }

    public function test_movie_comments_thread_notify_and_respect_permissions(): void
    {
        $owner = $this->member();
        $viewer = $this->member();
        $stranger = $this->member();

        $movie = Movie::create([
            'user_id' => $owner->id, 'title' => 'Talked about', 'video_path' => 'movies/videos/c.mp4', 'video_mime' => 'video/mp4',
        ]);

        $root = $this->actingAs($viewer)->postJson(route('movie-comments.store', $movie), ['body' => 'Loved the ending'])
            ->assertOk()->assertJson(['parent_id' => null, 'count' => 1])->json();
        $this->assertStringContainsString('Loved the ending', $root['html']);

        $rootId = \App\Models\MovieComment::where('movie_id', $movie->id)->value('id');

        $this->actingAs($owner)->postJson(route('movie-comments.store', $movie), ['body' => 'Thanks!', 'parent_id' => $rootId])
            ->assertOk()->assertJson(['parent_id' => $rootId, 'count' => 2]);

        // Uploader hears about the comment, commenter hears about the reply.
        $this->assertDatabaseHas('alerts', ['user_id' => $owner->id, 'actor_id' => $viewer->id, 'type' => 'movie_comment', 'movie_id' => $movie->id]);
        $this->assertDatabaseHas('alerts', ['user_id' => $viewer->id, 'actor_id' => $owner->id, 'type' => 'movie_reply', 'movie_id' => $movie->id]);

        $this->get(route('movies.show', $movie))->assertOk()->assertSee('Loved the ending')->assertSee('Thanks!')->assertSee('data-comment-count>2<', false);

        $this->actingAs($stranger)->deleteJson(route('movie-comments.destroy', $rootId))->assertForbidden();
        $this->actingAs($owner)->deleteJson(route('movie-comments.destroy', $rootId))->assertOk()->assertJson(['count' => 0]);

        auth()->logout();
        $this->post(route('movie-comments.store', $movie), ['body' => 'guest'])->assertRedirect(route('login'));
    }

    public function test_song_details_can_be_saved_in_place(): void
    {
        $owner = $this->member();
        $song = Song::create(['user_id' => $owner->id, 'title' => 'Wrong title', 'path' => 'music/tracks/e.mp3', 'mime' => 'audio/mpeg']);

        $this->actingAs($owner)->postJson(route('songs.update', $song), ['title' => 'Right title', 'artist' => 'Someone', 'album' => ''])
            ->assertOk()->assertJson(['title' => 'Right title', 'artist' => 'Someone', 'album' => null]);

        $this->actingAs($this->member())->postJson(route('songs.update', $song), ['title' => 'Hijack'])->assertForbidden();
    }

    public function test_srt_conversion_and_language_guessing(): void
    {
        $vtt = Subtitles::toVtt("\xEF\xBB\xBF1\n00:00:05,120 --> 00:00:07,000\n{\\an8}Top line\n");
        $this->assertStringStartsWith("WEBVTT\n\n", $vtt);
        $this->assertStringContainsString('00:00:05.120 --> 00:00:07.000', $vtt);
        $this->assertStringNotContainsString('{\\an8}', $vtt);

        $this->assertSame(['en', 'English (SDH)'], Subtitles::guessLanguage('English [SDH].eng.srt'));
        $this->assertSame(['es', 'Spanish'], Subtitles::guessLanguage('Spanish.spa.srt'));
        $this->assertSame(['si', 'Sinhala'], Subtitles::guessLanguage('Eiffel-2021-BLURAY-24FPS.si.srt'));
    }

    /* ---------------------------------------------------------------- */
    /* Songs and playlists                                               */
    /* ---------------------------------------------------------------- */

    public function test_a_song_not_found_keeps_only_its_name(): void
    {
        Storage::fake('public');
        $user = $this->member();

        [$uploadId, $total] = $this->uploadInChunks($user, $this->fakeMp3('Sanda Paaya', 'Test Singer'), 20000);

        $this->actingAs($user)->postJson(route('songs.store'), [
            'upload_id' => $uploadId,
            'total_chunks' => $total,
            'filename' => '01_whatever.mp3',
        ])->assertOk()->assertJson(['title' => 'Sanda Paaya', 'artist_raw' => null, 'found' => false, 'duration' => '0:03']);

        $song = Song::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('audio/mpeg', $song->mime);
        $this->assertSame(3, $song->duration);
        $this->assertNull($song->artist);
        $this->assertNull($song->album);
        $this->assertNull($song->cover_path);
        Storage::disk('public')->assertExists($song->path);

        $this->get(route('songs.index'))->assertOk()->assertSee('Sanda Paaya');
    }

    public function test_a_found_song_gets_its_details_filled_in(): void
    {
        Storage::fake('public');
        $this->knowSong('Qorvane Tellisu Ranja', 'Zevandri Pereira');
        $user = $this->member();

        // No tags at all: everything comes from the file name.
        [$uploadId, $total] = $this->uploadInChunks($user, $this->fakeMp3('', ''), 20000);

        $this->actingAs($user)->postJson(route('songs.store'), [
            'upload_id' => $uploadId,
            'total_chunks' => $total,
            'filename' => 'Qorvane_Tellisu_Ranja.mp3',
        ])->assertOk()->assertJson([
            'title' => 'Qorvane Tellisu Ranja',
            'artist_raw' => 'Zevandri Pereira',
            'album' => 'Qorvane Tellisu Ranja (Single)',
            'genre' => 'Asian Music',
            'found' => 'full',
        ]);

        $song = Song::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('music/covers/album-dz-777.jpg', $song->cover_path);
        Storage::disk('public')->assertExists($song->cover_path);
    }

    public function test_found_artists_keep_the_library_spelling(): void
    {
        Storage::fake('public');
        // Made-up names so the real library's artists cannot interfere.
        $this->knowSong('Qorvane Tellisu Ranja', 'Zevandri Pereira');
        $user = $this->member();
        foreach (['Older song', 'Another'] as $i => $title) {
            Song::create(['user_id' => $user->id, 'title' => $title, 'artist' => 'Zevandri Perera', 'path' => "music/tracks/x{$i}.mp3", 'mime' => 'audio/mpeg']);
        }
        Song::create(['user_id' => $user->id, 'title' => 'Rare spelling', 'artist' => 'Zevandri Pereira', 'path' => 'music/tracks/x9.mp3', 'mime' => 'audio/mpeg']);

        [$uploadId, $total] = $this->uploadInChunks($user, $this->fakeMp3('', ''), 20000);

        // Two songs say "Perera", one says "Pereira": the majority spelling wins.
        $this->actingAs($user)->postJson(route('songs.store'), [
            'upload_id' => $uploadId, 'total_chunks' => $total, 'filename' => 'Qorvane Tellisu Ranja.mp3',
        ])->assertOk()->assertJson(['artist_raw' => 'Zevandri Perera', 'found' => 'full']);
    }

    public function test_the_search_uses_the_first_three_words(): void
    {
        Storage::fake('public');
        $this->knowSong('Qorvane Tellisu Ranja', 'Zevandri Pereira');
        $user = $this->member();

        [$uploadId, $total] = $this->uploadInChunks($user, $this->fakeMp3('', ''), 20000);
        $this->actingAs($user)->postJson(route('songs.store'), [
            'upload_id' => $uploadId, 'total_chunks' => $total, 'filename' => 'Qorvane_Tellisu_Ranja_Zevandri_Pereira.mp3',
        ])->assertOk()->assertJson(['title' => 'Qorvane Tellisu Ranja', 'found' => 'full']);

        Http::assertSent(function ($r) {
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $query);

            return str_contains($r->url(), 'api.deezer.com/search') && ($query['q'] ?? null) === 'qorvane tellisu ranja';
        });
    }

    public function test_the_second_catalogue_is_tried_when_the_first_has_nothing(): void
    {
        Storage::fake('public');
        $img = imagecreatetruecolor(60, 60);
        ob_start();
        imagejpeg($img);
        $jpeg = ob_get_clean();

        $this->catalogue = [
            'itunes.apple.com/search' => Http::response(['results' => [[
                'trackId' => 555, 'trackName' => 'Melindra Sova (feat. Someone)', 'artistName' => 'Tavrin Kodagoda',
                'collectionId' => 444, 'collectionName' => 'Melindra Sova - Single', 'primaryGenreName' => 'Pop',
                'artworkUrl100' => 'https://art.test/img/100x100bb.jpg', 'trackTimeMillis' => 3000,
            ]]]),
            'art.test/img/600x600bb.jpg' => Http::response($jpeg, 200, ['Content-Type' => 'image/jpeg']),
        ];
        $user = $this->member();

        [$uploadId, $total] = $this->uploadInChunks($user, $this->fakeMp3('', ''), 20000);
        $this->actingAs($user)->postJson(route('songs.store'), [
            'upload_id' => $uploadId, 'total_chunks' => $total, 'filename' => 'Melindra Sova Tavrin Kodagoda.mp3',
        ])->assertOk()->assertJson([
            'title' => 'Melindra Sova',
            'artist_raw' => 'Tavrin Kodagoda',
            'album' => 'Melindra Sova',  // " - Single" dropped
            'genre' => 'Pop',
            'found' => 'full',
        ]);

        $this->assertSame('music/covers/album-it-444.jpg', Song::where('user_id', $user->id)->value('cover_path'));
    }

    public function test_another_singers_version_still_gives_title_and_artist(): void
    {
        Storage::fake('public');
        $this->knowSong('Qorvane Tellisu Ranja', 'Some Other Band');
        $user = $this->member();

        [$uploadId, $total] = $this->uploadInChunks($user, $this->fakeMp3('', ''), 20000);
        $this->actingAs($user)->postJson(route('songs.store'), [
            'upload_id' => $uploadId, 'total_chunks' => $total, 'filename' => 'Qorvane_Tellisu_Ranja_Melani_Sendru.mp3',
        ])->assertOk()->assertJson([
            'title' => 'Qorvane Tellisu Ranja',
            'artist_raw' => 'Melani Sendru',
            'album' => null,
            'cover' => null,
            'found' => 'partial',
        ]);
    }

    public function test_a_different_artists_song_is_not_used(): void
    {
        Storage::fake('public');
        $this->knowSong('Sanda Paaya', 'Someone Else Entirely');
        $user = $this->member();

        [$uploadId, $total] = $this->uploadInChunks($user, $this->fakeMp3('Sanda Paaya', 'Test Singer'), 20000);

        $this->actingAs($user)->postJson(route('songs.store'), [
            'upload_id' => $uploadId, 'total_chunks' => $total, 'filename' => 'sanda.mp3',
        ])->assertOk()->assertJson(['title' => 'Sanda Paaya', 'artist_raw' => null, 'found' => false]);
    }

    public function test_owners_can_look_up_and_apply_details_later(): void
    {
        Storage::fake('public');
        $this->knowSong('Qorvane Tellisu Ranja', 'Zevandri Pereira');
        $owner = $this->member();
        $other = $this->member();
        $song = Song::create(['user_id' => $owner->id, 'title' => 'qorvane', 'path' => 'music/tracks/y.mp3', 'mime' => 'audio/mpeg', 'duration' => 3]);

        $this->actingAs($other)->getJson(route('songs.lookup', $song))->assertForbidden();
        $this->actingAs($other)->post(route('songs.lookup.apply', $song), ['id' => 'dz:9001'])->assertForbidden();

        // Members never see where the details come from.
        $this->actingAs($owner)->get(route('songs.edit', $song))->assertOk()
            ->assertSee('Find song details')->assertDontSee('deezer', false)->assertDontSee('Deezer', false);

        $results = $this->actingAs($owner)->getJson(route('songs.lookup', $song).'?q=qorvane')->assertOk()->json();
        $this->assertSame('Zevandri Pereira', $results[1]['artist']);
        $this->assertArrayNotHasKey('rank', $results[0]);

        $this->actingAs($owner)->post(route('songs.lookup.apply', $song), ['id' => 'dz:9001'])
            ->assertRedirect(route('songs.edit', $song));

        $song->refresh();
        $this->assertSame('Qorvane Tellisu Ranja', $song->title);
        $this->assertSame('Zevandri Pereira', $song->artist);
        $this->assertSame('Asian Music', $song->genre);
        $this->assertNotNull($song->cover_path);

        // And the owner can still change anything by hand afterwards.
        $this->actingAs($owner)->post(route('songs.update', $song), ['title' => 'My title', 'artist' => 'My artist'])->assertRedirect();
        $this->assertSame('My artist', $song->fresh()->artist);
    }

    public function test_playlists_are_public_and_only_the_owner_edits_them(): void
    {
        $owner = $this->member();
        $other = $this->member();

        $songs = collect(range(1, 3))->map(fn ($i) => Song::create([
            'user_id' => $owner->id, 'title' => "Track {$i}", 'artist' => 'Band', 'path' => "music/tracks/{$i}.mp3",
            'mime' => 'audio/mpeg', 'duration' => 200,
        ]));

        $created = $this->actingAs($owner)->postJson(route('playlists.store'), [
            'name' => 'Road trip',
            'song_id' => $songs[0]->id,
        ])->assertOk()->json();

        $playlist = Playlist::findOrFail($created['id']);

        $add = fn ($song) => $this->actingAs($owner)->postJson(route('playlists.songs.add', $playlist), ['song_id' => $song->id]);
        $add($songs[1])->assertJson(['added' => true, 'count' => 2]);
        $add($songs[2])->assertJson(['added' => true, 'count' => 3]);
        $add($songs[1])->assertJson(['added' => false, 'count' => 3]);

        $this->actingAs($owner)->postJson(route('playlists.reorder', $playlist), [
            'ids' => [$songs[2]->id, $songs[0]->id, $songs[1]->id],
        ])->assertOk();
        $this->assertSame(
            [$songs[2]->id, $songs[0]->id, $songs[1]->id],
            $playlist->fresh()->songs->pluck('id')->all()
        );

        // Anyone can open and play it; nobody else can change it.
        auth()->logout();
        $this->get(route('playlists.show', $playlist))->assertOk()->assertSee('Road trip')->assertSee('Track 3');
        $this->post(route('playlists.play', $playlist))->assertJson(['plays' => 1]);

        $this->actingAs($other)->postJson(route('playlists.songs.add', $playlist), ['song_id' => $songs[0]->id])->assertForbidden();
        $this->actingAs($other)->postJson(route('playlists.reorder', $playlist), ['ids' => []])->assertForbidden();
        $this->actingAs($other)->delete(route('playlists.destroy', $playlist))->assertForbidden();

        $this->actingAs($owner)->deleteJson(route('playlists.songs.remove', [$playlist, $songs[0]]))->assertJson(['count' => 2]);
    }

    public function test_song_likes_and_play_counts(): void
    {
        $owner = $this->member();
        $song = Song::create([
            'user_id' => $owner->id, 'title' => 'Liked one', 'path' => 'music/tracks/l.mp3', 'mime' => 'audio/mpeg',
        ]);

        $this->post(route('songs.play', $song), ['duration' => 241.6])->assertJson(['plays' => 1]);
        $this->assertSame(242, $song->fresh()->duration, 'A missing duration is filled in by the player.');

        $this->actingAs($owner)->post(route('songs.like', $song))->assertJson(['liked' => true]);
        $this->actingAs($owner)->get(route('songs.index', ['view' => 'liked']))->assertOk()->assertSee('Liked one');
    }

    public function test_tag_cleanup_drops_download_site_watermarks(): void
    {
        $clean = SongTags::clean(
            ['title' => null, 'artist' => 'Sarigama.lk', 'album' => '[www.sinhalasongs.lk]', 'genre' => 'Other'],
            'Hanthanata_Payana_Sanda_Amarasiri_Peiris_Sarigama_lk.mp3',
            'amarasiri piris'
        );

        $this->assertSame('Hanthanata Payana Sanda', $clean['title']);
        $this->assertSame('Amarasiri Piris', $clean['artist']);
        $this->assertNull($clean['album']);
        $this->assertNull($clean['genre']);

        $this->assertSame('Malata Suvada Se', SongTags::clean(['title' => '2Malata Suvada Se.mp3'], 'x.mp3')['title']);
    }
}
