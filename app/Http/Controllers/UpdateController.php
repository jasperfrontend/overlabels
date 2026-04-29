<?php

namespace App\Http\Controllers;

use App\Models\Update;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UpdateController extends Controller
{
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

        return Inertia::render('updates/show', [
            'update' => $update,
        ]);
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
