<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Update;
use App\Services\AdminAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AdminUpdateController extends Controller
{
    public function __construct(private readonly AdminAuditService $audit) {}

    public function index(Request $request): Response
    {
        $query = Update::query();

        if ($search = $request->input('search')) {
            $term = '%'.strtolower($search).'%';
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(title) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(slug) LIKE ?', [$term]);
            });
        }

        $updates = $query->orderByDesc('published_at')->paginate(25)->withQueryString();

        return Inertia::render('admin/updates/index', [
            'updates' => $updates,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/updates/edit', [
            'update' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $update = Update::create([
            'title' => $data['title'],
            'slug' => Update::makeUniqueSlug($data['slug'] ?: $data['title']),
            'tags' => $this->normalizeTags($data['tags'] ?? null),
            'excerpt' => $data['excerpt'] ?? null,
            'body' => $data['body'],
            'compiled_css' => $data['compiled_css'] ?? null,
            'published_at' => $data['published_at'] ?? now(),
        ]);

        $this->audit->log($request->user(), 'update.created', 'Update', $update->id, [
            'slug' => $update->slug,
            'title' => $update->title,
        ], $request);

        return redirect()
            ->route('admin.updates.index')
            ->with('message', 'Update published.');
    }

    public function edit(Update $update): Response
    {
        return Inertia::render('admin/updates/edit', [
            'update' => $update,
        ]);
    }

    public function update(Request $request, Update $update): RedirectResponse
    {
        $data = $this->validated($request, $update->id);

        $newSlug = $data['slug'] ?: $update->slug;
        if ($newSlug !== $update->slug) {
            $newSlug = Update::makeUniqueSlug($newSlug, $update->id);
        }

        $update->update([
            'title' => $data['title'],
            'slug' => $newSlug,
            'tags' => $this->normalizeTags($data['tags'] ?? null),
            'excerpt' => $data['excerpt'] ?? null,
            'body' => $data['body'],
            'compiled_css' => $data['compiled_css'] ?? null,
            'published_at' => $data['published_at'] ?? $update->published_at,
        ]);

        $this->audit->log($request->user(), 'update.updated', 'Update', $update->id, [
            'slug' => $update->slug,
            'title' => $update->title,
        ], $request);

        return redirect()
            ->route('admin.updates.index')
            ->with('message', 'Update saved.');
    }

    public function destroy(Request $request, Update $update): RedirectResponse
    {
        $this->audit->log($request->user(), 'update.deleted', 'Update', $update->id, [
            'slug' => $update->slug,
            'title' => $update->title,
        ], $request);

        $update->delete();

        return redirect()
            ->route('admin.updates.index')
            ->with('message', 'Update deleted.');
    }

    /**
     * @return array{title: string, slug: ?string, tags: ?array, excerpt: ?string, body: string, published_at: ?Carbon}
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:64',
            'excerpt' => 'nullable|string',
            'body' => 'required|string',
            'compiled_css' => 'nullable|string',
            'published_at' => 'nullable|date',
        ]);
    }

    /**
     * Strip HTML, trim, and de-dupe while preserving the case the user typed.
     *
     * @param  array<int, string>|null  $tags
     * @return array<int, string>|null
     */
    private function normalizeTags(?array $tags): ?array
    {
        if (! $tags) {
            return null;
        }

        $clean = collect($tags)
            ->map(fn ($t) => trim(strip_tags((string) $t)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $clean ?: null;
    }
}
