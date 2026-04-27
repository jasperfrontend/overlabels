<?php

namespace App\Http\Controllers;

use App\Services\HelpReferenceService;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    private const BASE_URL = 'https://overlabels.com';

    /**
     * Public, indexable URLs only. Auth'd surfaces (dashboard, settings,
     * template management) are excluded - they require login and have no
     * SEO value.
     */
    private const STATIC_PATHS = [
        ['path' => '/', 'priority' => '1.0', 'changefreq' => 'weekly'],
        ['path' => '/privacy', 'priority' => '0.3', 'changefreq' => 'yearly'],
        ['path' => '/terms', 'priority' => '0.3', 'changefreq' => 'yearly'],
        ['path' => '/help', 'priority' => '0.8', 'changefreq' => 'monthly'],
        ['path' => '/help/conditionals', 'priority' => '0.7', 'changefreq' => 'monthly'],
        ['path' => '/help/controls', 'priority' => '0.7', 'changefreq' => 'monthly'],
        ['path' => '/help/formatting', 'priority' => '0.7', 'changefreq' => 'monthly'],
        ['path' => '/help/math', 'priority' => '0.7', 'changefreq' => 'monthly'],
        ['path' => '/help/resources', 'priority' => '0.7', 'changefreq' => 'monthly'],
        ['path' => '/help/why-kofi', 'priority' => '0.6', 'changefreq' => 'monthly'],
        ['path' => '/help/why-overlabels', 'priority' => '0.7', 'changefreq' => 'monthly'],
        ['path' => '/help/manifesto', 'priority' => '0.6', 'changefreq' => 'monthly'],
        ['path' => '/help/bot', 'priority' => '0.7', 'changefreq' => 'monthly'],
        ['path' => '/help/bot/commands', 'priority' => '0.7', 'changefreq' => 'monthly'],
        ['path' => '/help/gamejam', 'priority' => '0.6', 'changefreq' => 'monthly'],
        ['path' => '/help/reference', 'priority' => '0.8', 'changefreq' => 'weekly'],
    ];

    public function __invoke(HelpReferenceService $service): Response
    {
        $today = now()->toDateString();
        $urls = [];

        foreach (self::STATIC_PATHS as $row) {
            $urls[] = [
                'loc' => self::BASE_URL.$row['path'],
                'lastmod' => $today,
                'changefreq' => $row['changefreq'],
                'priority' => $row['priority'],
            ];
        }

        foreach ($service->all() as $entry) {
            $mtime = @filemtime($entry['path']);
            $urls[] = [
                'loc' => self::BASE_URL."/help/reference/{$entry['category']}/{$entry['slug']}",
                'lastmod' => $mtime ? date('Y-m-d', $mtime) : $today,
                'changefreq' => 'monthly',
                'priority' => '0.6',
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $u) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.htmlspecialchars($u['loc'], ENT_XML1).'</loc>'."\n";
            $xml .= '    <lastmod>'.$u['lastmod'].'</lastmod>'."\n";
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
