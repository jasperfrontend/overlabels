<?php

namespace App\Http\Controllers;

use App\Models\Update;
use App\Support\HelpCorpus;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    private const BASE_URL = 'https://overlabels.com';

    /**
     * Public, indexable URLs only. Auth'd surfaces (dashboard, settings,
     * template management) are excluded - they require login and have no
     * SEO value.
     */
    /**
     * Paths with no file behind them. Everything under /help is derived from
     * the corpus instead - see below.
     */
    private const STATIC_PATHS = [
        ['path' => '/', 'priority' => '1.0', 'changefreq' => 'weekly'],
        // Not an HTML page, but a sitemap may list any URL on the site, and this
        // is the one file we most want a crawler to find on its own. Ranked just
        // under the homepage: a sitemap priority is relative within the site, and
        // nothing here matters more to a machine reader. Its explainer page at
        // /help/llms-txt is what actually links to it.
        ['path' => '/llms.txt', 'priority' => '0.9', 'changefreq' => 'monthly'],
        ['path' => '/updates', 'priority' => '0.8', 'changefreq' => 'weekly'],
        ['path' => '/privacy', 'priority' => '0.3', 'changefreq' => 'yearly'],
        ['path' => '/terms', 'priority' => '0.3', 'changefreq' => 'yearly'],
        ['path' => '/help/reference', 'priority' => '0.8', 'changefreq' => 'weekly'],
        // The two help pages that are Vue components rather than markdown, so
        // HelpCorpus does not know about them.
        ['path' => '/help/integration-presets', 'priority' => '0.6', 'changefreq' => 'monthly'],
        ['path' => '/help/gamejam', 'priority' => '0.6', 'changefreq' => 'monthly'],
    ];

    /**
     * Priority by kind. Tutorials outrank guides because they are the pages
     * someone arrives wanting, and reference entries sit lowest only because
     * there are 147 of them and a flat 0.8 would say nothing.
     */
    private const KIND_PRIORITY = [
        HelpCorpus::KIND_TUTORIAL => '0.8',
        HelpCorpus::KIND_GUIDE => '0.7',
        HelpCorpus::KIND_DEEP_DIVE => '0.7',
        HelpCorpus::KIND_REFERENCE => '0.6',
    ];

    public function __invoke(): Response
    {
        $urls = [];

        foreach (self::STATIC_PATHS as $row) {
            $urls[] = [
                'loc' => self::BASE_URL.$row['path'],
                'changefreq' => $row['changefreq'],
                'priority' => $row['priority'],
            ];
        }

        /*
         * Every help document, derived rather than listed.
         *
         * This used to be a hand-maintained array, and by the time it was
         * replaced it had rotted by fourteen pages: /help/chat, /help/builder,
         * /help/blocks, /help/lists, /help/expressions, /help/rendering,
         * /help/testing, /help/tokens, /help/overlays-vs-alerts,
         * /help/for-creators, /help/for-designers, /help/lists-realtime and
         * both /help/bot/* subpages were all missing. Writing a page is now
         * the whole job of getting it indexed.
         */
        foreach (HelpCorpus::all() as $doc) {
            $urls[] = [
                'loc' => self::BASE_URL.$doc['url'],
                'changefreq' => 'monthly',
                'priority' => self::KIND_PRIORITY[$doc['kind']] ?? '0.6',
            ];
        }

        /*
         * Every published announcement post. These were missing entirely -
         * the whole of /updates was invisible to the sitemap, which is the
         * one part of the public site that gains new URLs on its own.
         *
         * published() is what keeps a future-dated draft out, exactly as it
         * keeps it off the page itself. Submitting a URL that answers 404 to
         * the crawler that follows it is worse than not listing it.
         */
        foreach (Update::published()->orderByDesc('published_at')->get() as $update) {
            $urls[] = [
                'loc' => self::BASE_URL.'/updates/'.$update->slug,
                'lastmod' => ($update->updated_at ?? $update->published_at)->toAtomString(),
                'changefreq' => 'monthly',
                'priority' => '0.7',
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $u) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.htmlspecialchars($u['loc'], ENT_XML1).'</loc>'."\n";
            // Element order is fixed by the sitemap schema: loc, lastmod,
            // changefreq, priority. Only rows with a real date carry one -
            // a made-up lastmod is worse than none.
            if (isset($u['lastmod'])) {
                $xml .= '    <lastmod>'.$u['lastmod'].'</lastmod>'."\n";
            }
            $xml .= '    <changefreq>'.$u['changefreq'].'</changefreq>'."\n";
            $xml .= '    <priority>'.$u['priority'].'</priority>'."\n";
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>'."\n";

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
