<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DashboardHighlight;
use App\Services\AdminAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard highlights, at /admin/highlights: short notes about features
 * worth knowing, each with one link into the app. Shown on every dashboard,
 * enabled ones only, by sort order.
 */
class AdminHighlightController extends Controller
{
    public function __construct(private readonly AdminAuditService $audit) {}

    public function index(): Response
    {
        return Inertia::render('admin/highlights/index', [
            'highlights' => DashboardHighlight::orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/highlights/edit', [
            'highlight' => null,
            'nextSortOrder' => (int) DashboardHighlight::max('sort_order') + 10,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $highlight = DashboardHighlight::create($this->validated($request));
        $this->audit->log($request->user(), 'highlight.created', 'DashboardHighlight', $highlight->id, ['link_url' => $highlight->link_url], $request);

        return redirect()->route('admin.highlights.index')->with('message', 'Highlight added.');
    }

    public function edit(DashboardHighlight $highlight): Response
    {
        return Inertia::render('admin/highlights/edit', [
            'highlight' => $highlight,
            'nextSortOrder' => $highlight->sort_order,
        ]);
    }

    public function update(Request $request, DashboardHighlight $highlight): RedirectResponse
    {
        $highlight->update($this->validated($request));
        $this->audit->log($request->user(), 'highlight.updated', 'DashboardHighlight', $highlight->id, ['link_url' => $highlight->link_url], $request);

        return redirect()->route('admin.highlights.index')->with('message', 'Highlight saved.');
    }

    public function destroy(Request $request, DashboardHighlight $highlight): RedirectResponse
    {
        $this->audit->log($request->user(), 'highlight.deleted', 'DashboardHighlight', $highlight->id, ['link_url' => $highlight->link_url], $request);
        $highlight->delete();

        return redirect()->route('admin.highlights.index')->with('message', 'Highlight deleted.');
    }

    /**
     * The link stays inside Overlabels: a path starting with one slash. A
     * second slash would make it a link to another site (//example.com).
     *
     * @return array{body: string, link_url: string, link_label: string, sort_order: int, enabled: bool}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
            'link_url' => ['required', 'string', 'max:255', 'regex:#^/(?!/)[^\s]*$#'],
            'link_label' => ['required', 'string', 'max:80'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'enabled' => ['required', 'boolean'],
        ], [
            'link_url.regex' => 'Use a path inside Overlabels that starts with /, like /settings/title.',
        ]);
        $data['enabled'] = (bool) $data['enabled'];

        return $data;
    }
}
