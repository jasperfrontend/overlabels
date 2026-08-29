<?php

namespace App\Http\Controllers;

use App\Models\Update;
use App\Services\OgImageService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UpdateController extends Controller
{
    public function __construct(
        private readonly OgImageService $og,
    ) {}

    public function index(Request $request): Response
    {
        $updates = Update::query()
            ->published()
            ->when($request->input('search'), function ($query, $search) {
                $term = '%'.strtolower($search).'%';
                $query->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(title) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(excerpt) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(body) LIKE ?', [$term]);
                });
            })
            ->when($request->input('tag'), function ($query, $tag) {
                $query->whereJsonContains('tags', $tag);
            })
            ->when($request->input('from'), function ($query, $from) {
                $query->where('published_at', '>=', $from);
            })
            ->when($request->input('to'), function ($query, $to) {
                $query->where('published_at', '<=', $to.' 23:59:59');
            })
            ->orderByDesc('published_at')
            ->paginate(15)
            ->withQueryString();

        $this->shareIndexMeta($request);

        return Inertia::render('updates/index', [
            'updates' => $updates,
            'filters' => $request->only(['search', 'tag', 'from', 'to']),
            'allTags' => $this->collectTags(),
        ]);
    }

    public function show(string $slug): Response
    {
        $update = Update::query()
            ->where('slug', $slug)
            ->published()
            ->firstOrFail();

        // The body is served without its CTA frontmatter, because show.vue
        // hands it straight to marked and a leaked block renders as a large
        // heading of `route: ...` lines. Stripped here rather than in the
        // browser so there is one answer to "what is this post's body", and
        // so the admin form - which loads the model directly - still edits
        // the real thing.
        $this->sharePostMeta($update);

        $update->setAttribute('body', $update->content());

        return Inertia::render('updates/show', [
            'update' => $update,
        ]);
    }

    /**
     * Server-render this post's social and search metadata.
     *
     * This page is Inertia, and its <Head> block only runs once Vue has
     * mounted. No link scraper executes JavaScript, so before this existed
     * every post shipped the site-wide default card - same title, same
     * description, same image, and an og:url pointing at the homepage, which
     * told every scraper the canonical thing behind the link was the front
     * page rather than the post.
     *
     * app.blade.php already had the seam for this (see the $og block there);
     * nothing new is needed beyond filling it in.
     */
    private function sharePostMeta(Update $update): void
    {
        $canonical = route('updates.show', $update->slug);
        $description = $update->plainExcerpt();
        $tags = $update->topicTags();

        // urlForUpdate returns a site-relative path, and og:image must be
        // absolute for every scraper that reads it.
        $image = url($this->og->urlForUpdate($update, $canonical));

        view()->share('og', [
            'type' => 'article',
            'url' => $canonical,
            'title' => $update->title,
            'description' => $description,
            'image' => $image,
            'image_alt' => $update->title,
            'image_width' => 1200,
            'image_height' => 630,
            'image_type' => 'image/png',
            'twitter_card' => 'summary_large_image',
            'published_time' => $update->published_at?->toIso8601String(),
            'modified_time' => $update->updated_at?->toIso8601String(),
            'tags' => $tags,
        ]);

        view()->share('canonical', $canonical);
        view()->share('pageTitle', "{$update->title} - Overlabels");

        view()->share('jsonLd', [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            // Google drops an Article rich result whose headline runs past
            // 110 characters, so this is clipped rather than left to chance.
            'headline' => mb_substr($update->title, 0, 110),
            'description' => $description,
            'image' => [$image],
            'url' => $canonical,
            'datePublished' => $update->published_at?->toIso8601String(),
            'dateModified' => ($update->updated_at ?? $update->published_at)?->toIso8601String(),
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonical],
            'isPartOf' => ['@type' => 'Blog', 'name' => 'Overlabels Updates', '@id' => route('updates.index')],
            'author' => $this->publisher(),
            'publisher' => $this->publisher(),
            'keywords' => $tags === [] ? null : implode(', ', $tags),
        ]);
    }

    /**
     * Metadata for the index. Without it the listing claimed to be the
     * homepage in exactly the way a post did.
     */
    private function shareIndexMeta(Request $request): void
    {
        $canonical = route('updates.index');
        $description = 'What is new on Overlabels - features, fixes, tips and kits, written up as they ship.';

        view()->share('og', [
            'type' => 'website',
            'url' => $canonical,
            'title' => 'Updates - Overlabels',
            'description' => $description,
            'image' => url('/ogimage.jpg'),
            'image_alt' => 'Overlabels - write HTML and CSS, bind live Twitch data with triple-bracket tags',
            'twitter_card' => 'summary_large_image',
        ]);

        view()->share('pageTitle', 'Updates - Overlabels');

        // Filtered and paginated views are the same collection in a different
        // order, so they all point at the clean listing rather than competing
        // with it. Page 2 of a search result has no business being indexed
        // separately.
        view()->share('canonical', $canonical);

        if ($request->hasAny(['search', 'tag', 'from', 'to', 'page'])) {
            view()->share('robots', 'noindex, follow');
        }

        view()->share('jsonLd', [
            '@context' => 'https://schema.org',
            '@type' => 'Blog',
            'name' => 'Overlabels Updates',
            'description' => $description,
            'url' => $canonical,
            'publisher' => $this->publisher(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function publisher(): array
    {
        return [
            '@type' => 'Organization',
            'name' => 'Overlabels',
            'url' => url('/'),
            'logo' => [
                '@type' => 'ImageObject',
                'url' => url('/favicon.png'),
            ],
        ];
    }

    /**
     * Collect distinct tags across all published updates so the list page can
     * surface them as filter chips. Cheap because tags is a small JSON array.
     *
     * @return array<int, string>
     */
    private function collectTags(): array
    {
        return Update::published()
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->unique()
            ->filter()
            ->values()
            ->all();
    }
}
