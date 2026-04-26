<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PerfilEspecialista;
use App\Models\TipoConsulta;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $frontendUrl = rtrim((string) config('services.frontend_url', $appUrl), '/');

        $staticUrls = [
            ['loc' => $frontendUrl . '/', 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['loc' => $frontendUrl . '/servicios', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['loc' => $frontendUrl . '/especialistas', 'priority' => '0.8', 'changefreq' => 'weekly'],
        ];

        $servicios = TipoConsulta::query()->where('activo', true)->get(['slug', 'updated_at']);
        $especialistas = PerfilEspecialista::query()->where('activo', true)->get(['slug', 'updated_at']);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($staticUrls as $url) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>{$url['loc']}</loc>\n";
            $xml .= "    <priority>{$url['priority']}</priority>\n";
            $xml .= "    <changefreq>{$url['changefreq']}</changefreq>\n";
            $xml .= "  </url>\n";
        }

        foreach ($servicios as $s) {
            $loc = $frontendUrl . '/servicios/' . e($s->slug);
            $lastmod = $s->updated_at?->toDateString() ?? now()->toDateString();
            $xml .= "  <url>\n    <loc>{$loc}</loc>\n    <lastmod>{$lastmod}</lastmod>\n    <priority>0.7</priority>\n  </url>\n";
        }

        foreach ($especialistas as $p) {
            $loc = $frontendUrl . '/especialistas/' . e($p->slug);
            $lastmod = $p->updated_at?->toDateString() ?? now()->toDateString();
            $xml .= "  <url>\n    <loc>{$loc}</loc>\n    <lastmod>{$lastmod}</lastmod>\n    <priority>0.7</priority>\n  </url>\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function robots(): Response
    {
        $frontendUrl = rtrim((string) config('services.frontend_url', config('app.url')), '/');
        $content = "User-agent: *\nAllow: /\nSitemap: {$frontendUrl}/sitemap.xml\n";

        return response($content, 200, ['Content-Type' => 'text/plain']);
    }
}
