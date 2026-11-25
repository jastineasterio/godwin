<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Banner;
use App\Models\SchoolEvent;
use App\Models\SchoolIntroVideo;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public marketing website — hero, vision/mission/motto, programs,
 * news & events, contact.
 */
class HomeController extends Controller
{
    public function index(): Response
    {
        $banners = Banner::query()
            ->active()
            ->get(['id', 'title', 'subtitle', 'image_path', 'link', 'cta_label'])
            ->map(fn (Banner $b) => [
                'id' => $b->id,
                'title' => $b->title,
                'subtitle' => $b->subtitle,
                'image_path' => $this->resolveImagePath($b->image_path),
                'link' => $b->link,
                'cta_label' => $b->cta_label,
            ]);

        $video = SchoolIntroVideo::query()->active()->first();
        $introVideo = $video ? [
            'url' => $video->url(),
            'original_name' => $video->original_name,
            'mime_type' => $video->mime_type,
            'file_size' => $video->file_size,
        ] : null;

        return Inertia::render('Public/Home', [
            'banners' => $banners,

            'news' => Announcement::query()
                ->published()
                ->latest('published_at')
                ->take(6)
                ->get(['id', 'type', 'title', 'slug', 'excerpt', 'cover_image', 'published_at']),

            'events' => SchoolEvent::query()
                ->published()
                ->upcoming()
                ->take(5)
                ->get(['id', 'title', 'description', 'start_date', 'start_time', 'location', 'color']),

            'intro_video' => $introVideo,
        ]);
    }

    /**
     * Convert a stored image path to a fully-qualified URL.
     *
     * Handles:
     *  - Full URLs (pass through unchanged).
     *  - Absolute storage paths like "/storage/posters/abc.jpg".
     *  - Relative paths like "banners/admission-2026.svg" (seeder data).
     */
    private function resolveImagePath(?string $path): string
    {
        if (!$path) {
            return '';
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        if (Str::startsWith($path, 'storage/')) {
            return asset($path);
        }

        return asset('storage/' . ltrim($path, '/'));
    }
}
