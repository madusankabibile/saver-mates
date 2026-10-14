<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Post extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'title', 'type', 'body', 'theme'];

    /**
     * The three things a member can publish.
     */
    public const KINDS = ['standard', 'colored', 'article'];

    /**
     * CommonMark settings. Raw HTML is stripped and javascript: style links are
     * dropped, so rendered article bodies are safe to echo unescaped.
     */
    public const MARKDOWN = [
        'html_input' => 'strip',
        'allow_unsafe_links' => false,
    ];

    /**
     * Gradient themes available to colored text posts.
     */
    public const THEMES = [
        'sunset' => ['label' => 'Sunset', 'css' => 'linear-gradient(135deg,#ff6a88 0%,#ff99ac 45%,#fcb69f 100%)'],
        'ocean' => ['label' => 'Ocean', 'css' => 'linear-gradient(135deg,#2193b0 0%,#3b82f6 50%,#6dd5ed 100%)'],
        'violet' => ['label' => 'Violet', 'css' => 'linear-gradient(135deg,#6366f1 0%,#8b5cf6 50%,#d946ef 100%)'],
        'mint' => ['label' => 'Mint', 'css' => 'linear-gradient(135deg,#0ba360 0%,#10b981 50%,#3cba92 100%)'],
        'citrus' => ['label' => 'Citrus', 'css' => 'linear-gradient(135deg,#f7971e 0%,#f59e0b 50%,#ffd200 100%)'],
        'berry' => ['label' => 'Berry', 'css' => 'linear-gradient(135deg,#c31432 0%,#f43f5e 50%,#ec4899 100%)'],
        'midnight' => ['label' => 'Midnight', 'css' => 'linear-gradient(135deg,#0f2027 0%,#203a43 50%,#2c5364 100%)'],
        'candy' => ['label' => 'Candy', 'css' => 'linear-gradient(135deg,#a18cd1 0%,#c084fc 50%,#fbc2eb 100%)'],
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(PostImage::class)->orderBy('position');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Top level comments only - replies hang off each of these.
     */
    public function rootComments(): HasMany
    {
        return $this->hasMany(Comment::class)->whereNull('parent_id')->oldest();
    }

    public function themeCss(): string
    {
        return self::THEMES[$this->theme]['css'] ?? self::THEMES['violet']['css'];
    }

    public function isArticle(): bool
    {
        return $this->type === 'article';
    }

    /**
     * Rendered article or post body. Modern articles use sanitized rich text HTML;
     * legacy articles and standard notes fall back to Markdown.
     */
    public function renderedBody(): string
    {
        if ($this->isArticle()) {
            $body = (string) $this->body;

            if (preg_match('/<[a-z][\s\S]*>/i', $body)) {
                return \App\Support\HtmlSanitizer::clean($body);
            }

            return Str::markdown($body, self::MARKDOWN);
        }

        return Str::markdown((string) $this->body, self::MARKDOWN);
    }

    /**
     * Markdown or HTML flattened to plain text, for feed teasers and previews.
     */
    public function plainBody(): string
    {
        if ($this->isArticle()) {
            $html = preg_replace('/<(\/?(p|div|h[1-6]|li|blockquote|figure|figcaption|tr)[^>]*|br\s*\/?|img[^>]*)>/i', ' ', (string) $this->renderedBody());
            $text = strip_tags($html);
        } else {
            $text = (string) $this->body;
        }

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES, 'UTF-8')));
    }

    /**
     * Returns the cover image or first inline article image URL for previews.
     */
    public function firstImageUrl(): ?string
    {
        if ($this->images->isNotEmpty()) {
            return $this->images->first()->url();
        }

        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', (string) $this->body, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function excerpt(int $length = 260): string
    {
        return Str::limit($this->plainBody(), $length);
    }

    /**
     * Rounded up, and never zero - "1 min read" reads better than "0 min".
     */
    public function readingMinutes(): int
    {
        $words = str_word_count($this->plainBody());

        return max(1, (int) ceil($words / 200));
    }

    public function displayTitle(): string
    {
        return $this->title ?: Str::limit($this->plainBody(), 70);
    }

    /**
     * Reaction tallies keyed by emoji type, highest first.
     */
    public function reactionSummary(): array
    {
        $counts = [];

        foreach ($this->reactions as $reaction) {
            // Fold any retired type onto its replacement so the tally groups
            // cleanly instead of showing a key that no longer has an emoji.
            $type = Reaction::normalise($reaction->type);
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        arsort($counts);

        return $counts;
    }

    public function reactionOf(?User $user): ?Reaction
    {
        if (! $user) {
            return null;
        }

        return $this->reactions->firstWhere('user_id', $user->id);
    }

    /**
     * Longer colored posts need to step the font size down to stay readable.
     */
    public function coloredFontSize(): string
    {
        $length = mb_strlen((string) $this->body);

        return match (true) {
            $length <= 40 => '2rem',
            $length <= 90 => '1.6rem',
            $length <= 180 => '1.3rem',
            default => '1.1rem',
        };
    }
}
