<?php

namespace App\Console\Commands;

use App\Models\Song;
use App\Support\SongLookup;
use Illuminate\Console\Command;

class FindSongDetails extends Command
{
    protected $signature = 'songs:find-details
        {--dry-run : Only show what would change}
        {--limit=0 : Stop after this many songs (0 = all)}';

    protected $description = 'Look up details for songs that have no artist yet, the same way uploads do';

    public function handle(): int
    {
        $query = Song::whereNull('artist')->orderBy('id');
        if ($limit = (int) $this->option('limit')) {
            $query->limit($limit);
        }

        $songs = $query->get();
        if ($songs->isEmpty()) {
            $this->info('Every song already has an artist.');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $rows = [];
        $counts = ['full' => 0, 'partial' => 0, 'none' => 0];

        foreach ($songs as $song) {
            $match = SongLookup::find($song->title, null, $song->duration);
            $kind = $match ? ($match['partial'] ? 'partial' : 'full') : 'none';
            $counts[$kind]++;

            $rows[] = [
                $song->id,
                $song->title,
                $match ? $match['title'] : '-',
                $match ? $match['artist'] : '-',
                $match ? ($match['album'] ?? '-') : '-',
                ['full' => 'all details', 'partial' => 'title + artist', 'none' => 'not found'][$kind],
            ];

            if ($match && ! $dry) {
                SongLookup::apply($song, $match);
            }

            usleep(250_000); // stay well inside the catalogues' rate limits
        }

        $this->table(['ID', 'Name now', 'Title', 'Artist', 'Album', 'Result'], $rows);
        $this->info(sprintf(
            '%s: %d with all details, %d with title and artist, %d not found.',
            $dry ? 'Dry run, nothing saved' : 'Saved',
            $counts['full'], $counts['partial'], $counts['none'],
        ));

        return self::SUCCESS;
    }
}
