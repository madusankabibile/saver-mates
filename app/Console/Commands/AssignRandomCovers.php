<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\RandomCover;
use Illuminate\Console\Command;

class AssignRandomCovers extends Command
{
    protected $signature = 'users:random-covers {--limit=0 : Stop after this many members (0 = all)}';

    protected $description = 'Give every member without a profile banner a random wallpaper';

    public function handle(): int
    {
        $query = User::whereNull('cover')->orWhere('cover', '')->orderBy('id');
        $total = (clone $query)->count();
        $limit = (int) $this->option('limit');

        if ($limit > 0) {
            $total = min($total, $limit);
            $query->limit($limit);
        }

        if ($total === 0) {
            $this->info('Every member already has a banner.');

            return self::SUCCESS;
        }

        $done = 0;
        $bar = $this->output->createProgressBar($total);

        foreach ($query->get() as $user) {
            if (RandomCover::assign($user)) {
                $done++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Assigned {$done} of {$total} banners.".($done < $total ? ' Failures are in storage/logs/laravel.log.' : ''));

        return $done === $total ? self::SUCCESS : self::FAILURE;
    }
}
