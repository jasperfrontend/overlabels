<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TemplateTag;
use App\Models\TemplateTagCategory;
use App\Services\AdminAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminTemplateTagController extends Controller
{
    public function __construct(private readonly AdminAuditService $audit) {}

    public function index(Request $request): Response
    {
        $tags = TemplateTag::with(['category:id,name', 'user:id,name'])
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('admin/tags/index', [
            'tags' => $tags,
            'filters' => $request->only(['search']),
        ]);
    }

    public function update(Request $request, TemplateTag $tag): RedirectResponse
    {
        $request->validate([
            'display_name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $tag->update($request->only(['display_name', 'description', 'is_active']));

        $this->audit->log($request->user(), 'tag.updated', 'TemplateTag', $tag->id, [
            'tag_name' => $tag->tag_name,
        ], $request);

        return back()->with('message', 'Tag updated.');
    }

    public function destroy(Request $request, TemplateTag $tag): RedirectResponse
    {
        $this->audit->log($request->user(), 'tag.deleted', 'TemplateTag', $tag->id, [
            'tag_name' => $tag->tag_name,
        ], $request);

        $tag->delete();

        return back()->with('message', 'Tag deleted.');
    }

    public function indexCategories(Request $request): Response
    {
        $categories = TemplateTagCategory::withCount('templateTags')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('admin/tags/index', [
            'categories' => $categories,
            'view' => 'categories',
        ]);
    }

    public function updateCategory(Request $request, TemplateTagCategory $category): RedirectResponse
    {
        $request->validate([
            'display_name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        $category->update($request->only(['display_name', 'description', 'sort_order']));

        return back()->with('message', 'Category updated.');
    }

    public function destroyCategory(Request $request, TemplateTagCategory $category): RedirectResponse
    {
        if ($category->templateTags()->where('is_active', true)->exists()) {
            return back()->withErrors(['category' => 'Cannot delete a category with active tags.']);
        }

        $this->audit->log($request->user(), 'tag_category.deleted', 'TemplateTagCategory', $category->id, [
            'name' => $category->name,
        ], $request);

        $category->delete();

        return back()->with('message', 'Category deleted.');
    }
}
