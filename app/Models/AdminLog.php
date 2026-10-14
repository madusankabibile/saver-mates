<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** An audit trail of everything done from the admin panel. */
class AdminLog extends Model
{
    protected $fillable = ['user_id', 'action', 'description', 'ip'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, string $description): self
    {
        return self::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'description' => Str::limit($description, 250),
            'ip' => request()->ip(),
        ]);
    }

    public function tone(): string
    {
        return match (true) {
            str_contains($this->action, 'delete') => 'danger',
            str_contains($this->action, 'suspend') => 'warn',
            default => 'info',
        };
    }
}
