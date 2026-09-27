<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Banner;
use App\Models\SchoolEvent;
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
        return Inertia::render('Public/Home', [
            'banners' => Banner::query()
                ->active()
                ->get(['id', 'title', 'subtitle', 'image_path', 'link', 'cta_label']),

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
        ]);
    }
}
