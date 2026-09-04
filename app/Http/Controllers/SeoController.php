<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Announcement;
use App\Models\JobListing;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function robots(): Response
    {
        $baseUrl = rtrim((string) (config('app.url') ?: url('/')), '/');
        $robots = "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /admin/\nDisallow: /chat\nDisallow: /check-in\nDisallow: /api/\nDisallow: /export/\nDisallow: /.env\nDisallow: /storage/\nDisallow: /config/\n\nSitemap: {$baseUrl}/sitemap.xml\n";

        return response($robots, 200)
            ->header('Content-Type', 'text/plain');
    }

    public function sitemap(): Response
    {
        $baseUrl = rtrim((string) (config('app.url') ?: url('/')), '/');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Static pages
        $staticPages = [
            ['loc' => '/', 'priority' => '1.0', 'changefreq' => 'daily'],
            ['loc' => '/activities', 'priority' => '0.9', 'changefreq' => 'daily'],
            ['loc' => '/jobs', 'priority' => '0.8', 'changefreq' => 'daily'],
            ['loc' => '/map', 'priority' => '0.7', 'changefreq' => 'weekly'],
            ['loc' => '/login', 'priority' => '0.5', 'changefreq' => 'monthly'],
            ['loc' => '/register', 'priority' => '0.5', 'changefreq' => 'monthly'],
            ['loc' => '/announcements', 'priority' => '0.7', 'changefreq' => 'daily'],
        ];

        foreach ($staticPages as $page) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . $baseUrl . $page['loc'] . '</loc>' . "\n";
            $xml .= '    <changefreq>' . $page['changefreq'] . '</changefreq>' . "\n";
            $xml .= '    <priority>' . $page['priority'] . '</priority>' . "\n";
            $xml .= '  </url>' . "\n";
        }

        // Activity pages
        $activities = Activity::where('status', '!=', 'cancelled')
            ->select('id', 'updated_at')
            ->orderByDesc('updated_at')
            ->limit(500)
            ->get();

        foreach ($activities as $activity) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . $baseUrl . '/activities/' . $activity->id . '</loc>' . "\n";
            $xml .= '    <lastmod>' . $activity->updated_at->format('Y-m-d') . '</lastmod>' . "\n";
            $xml .= '    <changefreq>weekly</changefreq>' . "\n";
            $xml .= '    <priority>0.6</priority>' . "\n";
            $xml .= '  </url>' . "\n";
        }

        // Job pages
        $jobs = JobListing::where('status', 'open')
            ->select('id', 'updated_at')
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get();

        foreach ($jobs as $job) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . $baseUrl . '/jobs/' . $job->id . '</loc>' . "\n";
            $xml .= '    <lastmod>' . $job->updated_at->format('Y-m-d') . '</lastmod>' . "\n";
            $xml .= '    <changefreq>weekly</changefreq>' . "\n";
            $xml .= '    <priority>0.6</priority>' . "\n";
            $xml .= '  </url>' . "\n";
        }

        // Announcement pages
        $announcements = Announcement::where('is_active', true)
            ->select('id', 'updated_at')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        foreach ($announcements as $announcement) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . $baseUrl . '/announcements/' . $announcement->id . '</loc>' . "\n";
            $xml .= '    <lastmod>' . $announcement->updated_at->format('Y-m-d') . '</lastmod>' . "\n";
            $xml .= '    <changefreq>monthly</changefreq>' . "\n";
            $xml .= '    <priority>0.5</priority>' . "\n";
            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200)
            ->header('Content-Type', 'application/xml');
    }
}
