<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The app used to run on UTC, so every stored time is a UTC wall-clock time.
 * Now that it runs on the local timezone (APP_TIMEZONE), shift existing rows
 * by the same offset so old posts and messages keep showing the right time.
 * Only fixed-offset zones (no daylight saving, e.g. Asia/Colombo) shift exactly.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->shift($this->offsetMinutes());
    }

    public function down(): void
    {
        $this->shift(-$this->offsetMinutes());
    }

    protected function offsetMinutes(): int
    {
        return Carbon::now(config('app.timezone'))->utcOffset();
    }

    protected function shift(int $minutes): void
    {
        if ($minutes === 0) {
            return;
        }

        $columns = DB::select("SELECT TABLE_NAME AS t, COLUMN_NAME AS c FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE IN ('timestamp', 'datetime')");

        foreach ($columns as $col) {
            // "Muted until I turn it back on" is a far-future marker, not a real time.
            $skip = $col->c === 'muted_until' ? " AND `{$col->c}` < '2099-01-01'" : '';

            DB::statement("UPDATE `{$col->t}` SET `{$col->c}` = `{$col->c}` + INTERVAL {$minutes} MINUTE WHERE `{$col->c}` IS NOT NULL{$skip}");
        }
    }
};
