{{--
    The sidebar tree, for both corpora. Shape comes from HelpNav.

    Groups and sections are native <details>, so which branch is open is decided
    on the server and needs no script. A group with no children is a plain link
    (the Reference row on the prose side).
--}}
<nav class="flex flex-col gap-1" aria-label="Documentation">
    @foreach ($groups as $group)
        @if (empty($group['items']) && empty($group['sections']))
            <a href="{{ $group['url'] }}" class="help-tree-row help-tree-row--link cursor-pointer">
                <span class="help-tree-caret help-tree-caret--static" aria-hidden="true"></span>
                <span class="flex-1 truncate">{{ $group['label'] }}</span>
                <span class="help-tree-count">{{ $group['count'] }}</span>
            </a>
        @else
            <details class="help-tree-group" @if ($group['open']) open @endif>
                <summary class="help-tree-row cursor-pointer">
                    <span class="help-tree-caret" aria-hidden="true"></span>
                    <span class="flex-1 truncate">{{ $group['label'] }}</span>
                    <span class="help-tree-count">{{ $group['count'] }}</span>
                </summary>
                <div class="help-tree-children">
                    @foreach ($group['sections'] as $section)
                        <details class="help-tree-section" @if ($section['open']) open @endif>
                            <summary class="help-tree-row help-tree-row--section cursor-pointer">
                                <span class="help-tree-caret" aria-hidden="true"></span>
                                <span class="flex-1 truncate">{{ $section['label'] }}</span>
                            </summary>
                            <div class="help-tree-children help-tree-children--deep">
                                @foreach ($section['items'] as $item)
                                    <a
                                        href="{{ $item['url'] }}"
                                        @if (!empty($item['active'])) data-help-active aria-current="page" @endif
                                        @class(['help-tree-link cursor-pointer', 'is-active' => !empty($item['active'])])
                                    >{{ $item['title'] }}</a>
                                @endforeach
                            </div>
                        </details>
                    @endforeach
                    @foreach ($group['items'] as $item)
                        <a
                            href="{{ $item['url'] }}"
                            @if (!empty($item['active'])) data-help-active aria-current="page" @endif
                            @class(['help-tree-link cursor-pointer', 'font-mono text-xs' => !empty($group['mono']), 'is-active' => !empty($item['active'])])
                        >{{ $item['title'] }}</a>
                    @endforeach
                </div>
            </details>
        @endif
    @endforeach
</nav>
