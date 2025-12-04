<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class ArticulateViewerController extends Controller
{
    /**
     * Serve Articulate content files using a signed token
     */
    public function show(Request $request, string $token, string $path = '')
    {
        // Validate the token
        $tokenData = cache()->get("articulate_token_{$token}");

        if (!$tokenData) {
            abort(404);
        }

        // Check if token has expired
        if (time() > $tokenData['expires_at']) {
            cache()->forget("articulate_token_{$token}");
            abort(404);
        }

        $basePath = $tokenData['path'];

        // If no specific path is provided, serve the index file
        if (empty($path)) {
            $filePath = $basePath;
        } else {
            // Clean the path to prevent directory traversal
            $path = str_replace(['../', '.\\', '\\'], '', $path);
            $extractDir = dirname($basePath);
            $filePath = $extractDir . '/' . $path;
        }

        // Check if file exists
        if (!file_exists($filePath)) {
            return response()->view('errors.404', [], 404);
        }

        // Get file content and MIME type
        $content = file_get_contents($filePath);
        $mimeType = $this->getMimeType($filePath);

        // For HTML files, we need to modify relative URLs to include the token
        if ($mimeType === 'text/html') {
            $content = $this->modifyHtmlUrls($content, $token);
        }

        return Response::make($content, 200, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'no-cache, must-revalidate',
            'Expires' => 'Mon, 01 Jan 1990 00:00:00 GMT'
        ]);
    }

    /**
     * Modify HTML content to include token in relative URLs
     */
    private function modifyHtmlUrls(string $content, string $token): string
    {
        // Replace relative URLs in common HTML attributes
        $baseUrl = url("/articulate-viewer/{$token}");

        // Replace src attributes
        $content = preg_replace(
            '/src=["\'](?!http|\/|#)([^"\']+)["\']/i',
            'src="' . $baseUrl . '/$1"',
            $content
        );

        // Replace href attributes (for CSS, JS, and other resources)
        $content = preg_replace(
            '/href=["\'](?!http|\/|#|javascript:|mailto:)([^"\']+)["\']/i',
            'href="' . $baseUrl . '/$1"',
            $content
        );

        // Replace background images in CSS
        $content = preg_replace(
            '/url\(["\']?(?!http|\/|#)([^"\')\s]+)["\']?\)/i',
            'url("' . $baseUrl . '/$1")',
            $content
        );

        return $content;
    }

    /**
     * Get MIME type for a file
     */
    private function getMimeType(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $mimeTypes = [
            'html' => 'text/html',
            'htm' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
}
