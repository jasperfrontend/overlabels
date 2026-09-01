{{--
    The one search box for the whole corpus - tutorials, guides, deep dives and
    reference entries alike. Included exactly once per page: in the hero on the
    landing page, in the nav everywhere else. Results open in a panel under the
    input rather than replacing the sidebar, because the landing has no sidebar.

    `compact` picks the nav size; the behaviour is identical.
--}}
<div @class(['help-search', 'help-search--compact' => $compact, 'help-search--hero' => !$compact])>
    <label for="help-search" class="sr-only">Search the documentation</label>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" class="help-search-icon" aria-hidden="true">
        <circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.2" y2="16.2"></line>
    </svg>
    <input
        id="help-search"
        type="text"
        placeholder="Search the docs"
        autocomplete="off"
        spellcheck="false"
        aria-controls="help-search-results"
    />
    <button
        id="help-search-clear"
        type="button"
        aria-label="Clear search"
        class="help-search-clear hidden"
    >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>
    <kbd class="help-search-kbd" title="Alt+R focuses search from any help page">Alt R</kbd>
    <div id="help-search-results" class="help-search-results hidden" role="listbox" aria-label="Search results"></div>
</div>
