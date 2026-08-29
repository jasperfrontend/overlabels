<?php

namespace App\Models;

use App\Support\Frontmatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property array<int, string>|null $tags
 * @property string|null $excerpt
 * @property string $body
 * @property string|null $compiled_css
 * @property string|null $cta_route
 * @property string|null $cta_params
 * @property string|null $cta_url
 * @property string|null $cta_label
 * @property Carbon $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static>|Update published()
 */
class Update extends Model
{
    /**
     * The tag that puts a post on the What's New card. Typed by hand into the
     * admin form's tags field, so adding a post to the card is adding a tag
     * while writing it - no second authoring surface, no migration.
     */
    public const string CARD_TAG = 'whatsnew';

    /**
     * Frontmatter keys the card understands. At least one must be present
     * before a leading `---` block is treated as frontmatter at all, which is
     * what stops an ordinary markdown horizontal rule from eating the top of
     * a post. See App\Support\Frontmatter::split().
     */
    public const array LINK_KEYS = ['route', 'params', 'url', 'label'];

    /** Cache key for the route/path list the visit detector consults. */
    private const string CTA_TARGETS_KEY = 'whatsnew:cta-targets';

    protected $fillable = [
        'title',
        'slug',
        'tags',
        'excerpt',
        'body',
        'compiled_css',
        'published_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'published_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (Update $update) {
            if (empty($update->slug)) {
                $update->slug = self::makeUniqueSlug($update->title, $update->id);
            }

            $update->projectCta();
        });

        // The cached target list is derived from rows, so any write can move it.
        static::saved(fn () => Cache::forget(self::CTA_TARGETS_KEY));
        static::deleted(fn () => Cache::forget(self::CTA_TARGETS_KEY));
    }

    /**
     * Copy the body's CTA frontmatter into the queryable columns.
     *
     * Frontmatter remains the thing an author writes and the only source of
     * truth; these columns are a projection rewritten on every save, so they
     * cannot drift from it. Always assigned, including to null, so removing
     * the frontmatter from a post removes its link.
     */
    private function projectCta(): void
    {
        $meta = Frontmatter::split((string) $this->body, self::LINK_KEYS)[0];

        $this->cta_route = ($meta['route'] ?? '') ?: null;
        $this->cta_params = ($meta['params'] ?? '') ?: null;
        $this->cta_url = ($meta['url'] ?? '') ?: null;
        $this->cta_label = ($meta['label'] ?? '') ?: null;
    }

    public static function makeUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'update';
        $slug = $base;
        $i = 2;

        while (static::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('published_at', '<=', now());
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(UpdateInteraction::class);
    }

    /**
     * Every route name and internal path a live card entry points at.
     *
     * Cached because the visit detector consults it on every authenticated
     * request, and the answer is "no" for almost all of them. Checking an
     * in-memory array first means an ordinary page load does no database work
     * at all - only a request that actually lands on a route some update
     * advertises pays for a query.
     *
     * @return array{routes: array<int, string>, paths: array<int, string>}
     */
    public static function ctaTargets(): array
    {
        return Cache::rememberForever(self::CTA_TARGETS_KEY, function () {
            $rows = static::query()
                ->whereJsonContains('tags', self::CARD_TAG)
                ->where(fn ($q) => $q->whereNotNull('cta_route')->orWhereNotNull('cta_url'))
                ->get(['cta_route', 'cta_url']);

            return [
                'routes' => $rows->pluck('cta_route')->filter()->unique()->values()->all(),
                'paths' => $rows->pluck('cta_url')
                    ->filter()
                    // Only internal paths can ever be observed. An absolute URL
                    // leaves the app, so it goes stale on click instead.
                    ->filter(fn (string $url) => str_starts_with($url, '/'))
                    ->map(fn (string $url) => trim(parse_url($url, PHP_URL_PATH) ?: '/', '/') ?: '/')
                    ->unique()
                    ->values()
                    ->all(),
            ];
        });
    }

    /**
     * The posts that belong on a user's What's New card, newest first.
     *
     * Four conditions, and each is load-bearing. The `whatsnew` tag is what
     * makes the card opt-in per post rather than a mirror of /updates. The
     * created_at comparison is what makes a new account caught up by
     * definition - you are not accountable for what shipped before you
     * existed, and it needs no registration hook to be true. The dismissal
     * check is the "seen" record. published() keeps a future-dated draft off
     * the card exactly as it keeps it off /updates.
     */
    public function scopeUnseenBy(Builder $query, User $user): Builder
    {
        return $query->published()
            ->whereJsonContains('tags', self::CARD_TAG)
            ->where('published_at', '>', $user->created_at)
            ->whereDoesntHave('interactions', fn ($q) => $q
                ->where('user_id', $user->id)
                ->whereNotNull('dismissed_at'))
            ->orderByDesc('published_at');
    }

    /**
     * The body with any CTA frontmatter removed.
     *
     * This is what the public post page renders. The stored body keeps its
     * frontmatter so the admin form still edits what was written.
     */
    public function content(): string
    {
        return Frontmatter::split((string) $this->body, self::LINK_KEYS)[1];
    }

    /**
     * The post's tags minus the plumbing ones.
     *
     * `whatsnew` says where a post is shown, not what it is about, so it has
     * no business in a share preview or an article:tag. Everything else the
     * author typed is a real topic.
     *
     * @return array<int, string>
     */
    public function topicTags(): array
    {
        return array_values(array_filter(
            $this->tags ?? [],
            fn (string $tag) => $tag !== self::CARD_TAG,
        ));
    }

    /**
     * The post's description as plain text.
     *
     * This is what a scraper reads as og:description and what the OG card
     * prints under the title, so it has to be prose - a link preview showing
     * literal `**bold**` or a stray `[label](url)` reads as broken.
     *
     * The hand-written excerpt wins, because it is the author saying what the
     * post is about. The body opening is only a fallback, so an older post
     * written before the excerpt field still gets a real description instead
     * of the site-wide default.
     *
     * Both paths get stripped: excerpt is markdown too (show.vue hands it to
     * marked, same as the body).
     */
    public function plainExcerpt(int $max = 200): string
    {
        $source = trim((string) $this->excerpt);

        if ($source === '') {
            $source = $this->content();
        }

        return self::truncateWords(self::stripMarkdown($source), $max);
    }

    /**
     * Reduce markdown to flowing prose.
     *
     * Deliberately separate from OgImageService::bodyExcerpt(), which does the
     * same job for help reference entries. That one is tuned for reference
     * bodies and is working; posts carry things it never sees (blockquotes,
     * raw HTML, markdown links) and reference entries carry things posts do
     * not. Merging them would mean changing a working card to fix a new one.
     *
     * Step order is load-bearing where noted.
     */
    private static function stripMarkdown(string $text): string
    {
        // Fences first, so HTML and tag syntax inside a code sample is gone
        // before anything below can half-interpret it.
        $text = preg_replace('/```[\s\S]*?```/', '', $text) ?? $text;
        // Line-anchored passes, while the newlines are still here.
        $text = preg_replace('/^\s*>\s?/m', '', $text) ?? $text;          // blockquote markers
        $text = preg_replace('/^#{1,6}\s+.*$/m', '', $text) ?? $text;     // whole heading lines
        $text = preg_replace('/^\s*(?:[-*+]|\d+\.)\s+/m', '', $text) ?? $text; // list bullets
        $text = preg_replace('/^\s*(?:-{3,}|\*{3,}|_{3,})\s*$/m', '', $text) ?? $text; // rules
        // Images carry no readable text; links keep their label.
        $text = preg_replace('/!\[[^\]]*]\([^)]*\)/', '', $text) ?? $text;
        $text = preg_replace('/\[([^\]]+)]\([^)]*\)/', '$1', $text) ?? $text;
        // [[[tag]]] markers - keep the tag bare. MUST run before the wikilink
        // pass below: that pattern excludes `[` from its inner class, so on
        // `[[[counter:wins]]]` it skips the first bracket and matches the
        // inner `[[counter:wins]]`, leaving a stray `[counter:wins]` behind.
        $text = preg_replace('/\[\[\[([^\[\]<>]+?)]]]/', '$1', $text) ?? $text;
        // Obsidian-style [[slug]] / [[slug|label]] - keep the label.
        $text = preg_replace_callback(
            '/\[\[([^\]|\[]+?)(?:\|([^\]]+))?]]/',
            fn ($m) => trim($m[2] ?? $m[1]),
            $text,
        ) ?? $text;
        // Real HTML tags only. `[a-zA-Z]` after the bracket is what stops this
        // eating from the `<` in "i <3 you" to the next `>` - the same trap
        // strip_tags() falls into, which is why it is not used here.
        $text = preg_replace('#</?[a-zA-Z][^<>]*>#', '', $text) ?? $text;
        $text = str_replace('`', '', $text);
        // Emphasis, after the bullet pass so a `* item` line is not read as
        // one half of an italic pair.
        $text = preg_replace('/\*\*(.+?)\*\*/s', '$1', $text) ?? $text;
        $text = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/s', '$1', $text) ?? $text;
        $text = preg_replace('/(?<![\w_])__(.+?)__(?![\w_])/s', '$1', $text) ?? $text;

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    /**
     * Clip to a length without cutting a word in half, adding an ellipsis only
     * when something was actually removed.
     */
    private static function truncateWords(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $cut = mb_substr($text, 0, $max);
        $lastSpace = mb_strrpos($cut, ' ');

        // Only honour the word boundary if it is not so early that we would
        // throw away most of the allowance (a single very long token).
        if ($lastSpace !== false && $lastSpace > $max * 0.6) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return rtrim($cut, " \t\n.,;:").'…';
    }

    /**
     * The per-row call to action, or null when the author declared none.
     *
     * Read from the projected columns rather than by re-parsing the body, so
     * building a card of five rows costs no markdown parsing at all.
     *
     * A `route:` is resolved here rather than stored as a URL, so a renamed
     * route is caught at save time by the admin validator instead of rotting
     * silently the way a pasted URL does. At render time a route that has
     * since disappeared drops the link instead of throwing - a stale CTA is
     * worth less than a dashboard that still loads.
     *
     * @return array{label:string,href:string,external:bool}|null
     */
    public function cta(): ?array
    {
        if (($this->cta_label ?? '') === '') {
            return null;
        }

        $href = $this->cta_url;

        if ($href === null && $this->cta_route !== null) {
            if (! Route::has($this->cta_route)) {
                return null;
            }

            $params = [];
            parse_str((string) $this->cta_params, $params);
            $href = route($this->cta_route, $params);
        }

        if ($href === null || $href === '') {
            return null;
        }

        return [
            'label' => $this->cta_label,
            'href' => $href,
            // An absolute URL leaves the app, so no request of ours will ever
            // observe the visit. The card marks those stale on click instead.
            'external' => $this->cta_url !== null && ! str_starts_with($this->cta_url, '/'),
        ];
    }
}
