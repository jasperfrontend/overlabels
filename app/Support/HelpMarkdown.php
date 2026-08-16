<?php

namespace App\Support;

use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

/**
 * The one markdown pipeline for every help document.
 *
 * Before this existed there were three: `Str::markdown` in HelpPage (callouts,
 * KaTeX, TOC, heading anchors), a hand-built CommonMark environment in
 * HelpReferenceService (wikilinks, click-to-copy tags), and `marked` in the
 * browser. A guide could not use a copyable tag and a reference entry could not
 * use a callout, for no reason other than which class happened to render it.
 * Every feature is now available to every document.
 *
 * Stage order is load-bearing:
 *
 *   1. extractMath      TeX leaves before CommonMark can eat `_` as emphasis
 *                       or `\` as an escape.
 *   2. rewriteWikilinks `[[slug]]` becomes a real link, outside code spans.
 *   3. convert          markdown -> HTML.
 *   4. restoreMath      placeholders become elements KaTeX renders client-side.
 *   5. transformCallouts GitHub `> [!NOTE]` blockquotes become styled boxes.
 *   6. enhanceTags      `[[[tag]]]` becomes a click-to-copy widget.
 *   7. addHeadingAnchors h2/h3 get stable ids; h2s become the table of contents.
 *
 * Steps 2 and 6 cannot be swapped: the wikilink regex relies on its negative
 * lookbehind to stay out of `[[[tag]]]`, so the tags must still be intact and
 * unwrapped when it runs.
 */
final class HelpMarkdown
{
    /**
     * Render a body into HTML plus its table of contents.
     *
     * @param  array<string,string>  $links  slug => url, for `[[wikilink]]` resolution
     * @param  bool  $softBreaks  render a single newline as `<br />`
     * @return array{0:string,1:array<int,array{id:string,text:string}>}
     */
    public static function render(string $body, array $links = [], bool $softBreaks = false): array
    {
        [$body, $math] = self::extractMath($body);

        $body = self::rewriteWikilinks($body, $links);

        $html = (string) self::converter($softBreaks)->convert($body);

        $html = self::restoreMath($html, $math);
        $html = self::transformCallouts($html);
        $html = self::enhanceTags($html);

        return self::addHeadingAnchors($html);
    }

    /**
     * Soft breaks are per-kind, not a global choice.
     *
     * Reference entries are written one statement per line, Obsidian-style, and
     * read as nonsense if consecutive lines are joined into a paragraph. Guides
     * are written as ordinary prose wrapped at ~100 columns, and would break
     * mid-sentence at every wrap point if newlines became `<br />`. Neither
     * setting is correct for both, so the caller decides.
     */
    private static function converter(bool $softBreaks): MarkdownConverter
    {
        $env = new Environment([
            'renderer' => [
                'soft_break' => $softBreaks ? "<br />\n" : "\n",
            ],
            // Nine of the guides hand-write HTML (figures, inline spans), so it
            // has to be allowed. Every document here is repo-controlled prose
            // that went through review; none of it is user input.
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);

        $env->addExtension(new CommonMarkCoreExtension);
        $env->addExtension(new GithubFlavoredMarkdownExtension);

        return new MarkdownConverter($env);
    }

    /**
     * Pull TeX out of the source and leave inert placeholders behind.
     *
     * Delimiters are `$$...$$` for display math and `\(...\)` for inline.
     * Bare `$...$` is deliberately NOT supported: other help pages talk about
     * money ("$1 or $1,000"), and a single-dollar rule would happily swallow
     * the text between two currency amounts.
     *
     * @return array{0:string,1:array<int,array{tex:string,display:bool}>}
     */
    private static function extractMath(string $body): array
    {
        $math = [];

        $stash = function (string $tex, bool $display) use (&$math): string {
            $math[] = ['tex' => trim($tex), 'display' => $display];

            return '@@OLMATH'.(count($math) - 1).'@@';
        };

        $body = preg_replace_callback(
            '/\$\$(.+?)\$\$/s',
            fn (array $m): string => $stash($m[1], true),
            $body
        );

        return [
            preg_replace_callback(
                '/\\\\\((.+?)\\\\\)/s',
                fn (array $m): string => $stash($m[1], false),
                $body
            ),
            $math,
        ];
    }

    /**
     * Swap the placeholders for elements the client renders with KaTeX.
     *
     * @param  array<int,array{tex:string,display:bool}>  $math
     */
    private static function restoreMath(string $html, array $math): string
    {
        foreach ($math as $i => $item) {
            $tag = $item['display'] ? 'div' : 'span';
            $element = sprintf(
                '<%s class="help-math" data-display="%s" data-tex="%s"></%s>',
                $tag,
                $item['display'] ? '1' : '0',
                htmlspecialchars($item['tex'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                $tag
            );

            $html = str_replace('@@OLMATH'.$i.'@@', $element, $html);
        }

        return $html;
    }

    /**
     * Turn Obsidian-style `[[slug]]` and `[[slug|label]]` into real links.
     *
     * Applied outside fenced and inline code spans so an author can show the
     * literal syntax in backticks without it being rewritten. An unknown slug
     * degrades to inline code rather than a dead link - the reference is
     * generated, so a slug can disappear between writes.
     *
     * @param  array<string,string>  $links
     */
    private static function rewriteWikilinks(string $md, array $links): string
    {
        if ($links === []) {
            return $md;
        }

        return self::outsideCode($md, function (string $text) use ($links): string {
            // Negative lookbehind/lookahead so we don't bite into the inner
            // `[[` of a triple-bracket template tag.
            return preg_replace_callback(
                '/(?<!\[)\[\[([^\]|\[]+?)(?:\|([^\]]+))?]](?!])/',
                function (array $m) use ($links): string {
                    $slug = trim($m[1]);
                    $label = trim($m[2] ?? $slug);

                    return isset($links[$slug])
                        ? "[{$label}]({$links[$slug]})"
                        : "`{$label}`";
                },
                $text,
            ) ?? $text;
        });
    }

    /**
     * Run a transform over prose only, leaving fenced blocks and inline code
     * spans byte-identical.
     *
     * @param  callable(string):string  $transform
     */
    private static function outsideCode(string $md, callable $transform): string
    {
        // Even indexes are prose, odd are fenced code left untouched.
        $fenceParts = preg_split('/(```[\s\S]*?```)/', $md, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($fenceParts === false) {
            return $md;
        }

        foreach ($fenceParts as $i => $part) {
            if ($i % 2 === 1) {
                continue;
            }

            $inlineParts = preg_split('/(`[^`\n]*`)/', $part, -1, PREG_SPLIT_DELIM_CAPTURE);

            if ($inlineParts === false) {
                continue;
            }

            foreach ($inlineParts as $j => $seg) {
                if ($j % 2 === 0) {
                    $inlineParts[$j] = $transform($seg);
                }
            }

            $fenceParts[$i] = implode('', $inlineParts);
        }

        return implode('', $fenceParts);
    }

    /**
     * Turn GitHub-style alert blockquotes into styled callouts.
     *
     *   > [!NOTE]
     *   > Snapshots are a promise in both directions.
     *
     * The syntax is deliberately GitHub's: it is widely recognised, LLMs know
     * it, and it still reads correctly as plain text when the raw .md is
     * fetched. Anything else stays an ordinary blockquote.
     */
    private static function transformCallouts(string $html): string
    {
        $kinds = [
            'NOTE' => 'note',
            'TIP' => 'tip',
            'IMPORTANT' => 'important',
            'WARNING' => 'warning',
            'CAUTION' => 'warning',
        ];

        return preg_replace_callback(
            '/<blockquote>\s*(.*?)\s*<\/blockquote>/s',
            function (array $m) use ($kinds): string {
                $inner = $m[1];

                if (! preg_match('/\[!([A-Z]+)]/', $inner, $tag)) {
                    return $m[0];
                }

                $kind = $kinds[$tag[1]] ?? null;

                if ($kind === null) {
                    return $m[0];
                }

                // Drop the marker, plus the now-empty paragraph if it was alone.
                $inner = str_replace($tag[0], '', $inner);
                $inner = preg_replace('/<p>\s*<\/p>/', '', $inner);
                $inner = preg_replace('/<p>\s*<br\s*\/?>\s*/', '<p>', $inner);

                return sprintf('<div class="help-callout help-callout--%s">%s</div>', $kind, trim($inner));
            },
            $html
        );
    }

    /**
     * Wrap every `[[[tag]]]` in a click-to-copy widget.
     *
     * Writing an overlay is mostly copying tags, so the whole corpus is now
     * one big clipboard. Single-tag inline `<code>` is unwrapped first so the
     * result is never `<code><code>`.
     */
    private static function enhanceTags(string $html): string
    {
        $out = preg_replace(
            '/<code>\s*(\[\[\[[^\[\]<>]+]]])\s*<\/code>/',
            '$1',
            $html,
        ) ?? $html;

        return preg_replace_callback(
            '/\[\[\[([^\[\]<>]+?)]]]/',
            function (array $m): string {
                $tag = "[[[{$m[1]}]]]";
                $attr = htmlspecialchars($tag, ENT_QUOTES, 'UTF-8');

                return '<code class="ov-tag" role="button" tabindex="0" data-tag="'.$attr.'" title="Click to copy">'.$tag.'</code>';
            },
            $out,
        ) ?? $out;
    }

    /**
     * Give every h2/h3 a stable id and collect the h2s as a table of contents.
     *
     * Generating the TOC removes a whole class of rot: the old .vue pages each
     * carried a hand-written list of anchors that had to be kept in step with
     * the sections by hand.
     *
     * @return array{0:string,1:array<int,array{id:string,text:string}>}
     */
    private static function addHeadingAnchors(string $html): array
    {
        $toc = [];
        $used = [];

        $html = preg_replace_callback(
            '/<h([23])>(.*?)<\/h\1>/s',
            function (array $m) use (&$toc, &$used): string {
                $level = (int) $m[1];
                $inner = $m[2];
                $text = trim(html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                // "1. Writing block CSS" -> "writing-block-css"
                $base = Str::slug(preg_replace('/^\s*\d+[.)]\s*/', '', $text)) ?: 'section';

                $id = $base;
                $n = 2;
                while (isset($used[$id])) {
                    $id = $base.'-'.$n++;
                }
                $used[$id] = true;

                if ($level === 2) {
                    $toc[] = ['id' => $id, 'text' => $text];
                }

                return sprintf('<h%d id="%s">%s</h%d>', $level, $id, $inner, $level);
            },
            $html
        );

        return [$html, $toc];
    }
}
