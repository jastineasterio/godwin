<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HOME PAGE POSTERS — admin-managed hero carousel images.
 *
 * Only active users with the Administrator role may reach these actions.
 * The backend enforces a maximum of 4 active posters.
 */
class BannerController extends Controller
{
    /**
     * Admin poster management screen.
     */
    public function index(): Response
    {
        $banners = Banner::query()
            ->orderBy('sort_order')
            ->get([
                'id',
                'title',
                'subtitle',
                'image_path',
                'link',
                'cta_label',
                'is_active',
                'sort_order',
            ])
            ->map(fn (Banner $b) => [
                'id' => $b->id,
                'title' => $b->title,
                'subtitle' => $b->subtitle,
                'image_url' => $this->resolveUrl($b->image_path),
                'link' => $b->link,
                'cta_label' => $b->cta_label,
                'is_active' => $b->is_active,
                'sort_order' => $b->sort_order,
            ]);

        $activeCount = Banner::query()
            ->where('is_active', true)
            ->count();

        return Inertia::render('Comms/Banners', [
            'banners' => $banners,
            'activeCount' => $activeCount,
            'maxBanners' => 4,
        ]);
    }

    /**
     * Store a newly uploaded poster.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $activeCount = Banner::query()
            ->where('is_active', true)
            ->count();

        if ($activeCount >= 4) {
            return back()->with(
                'error',
                'Maximum of 4 posters reached. Delete an existing poster first.'
            );
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'subtitle' => ['nullable', 'string', 'max:300'],
            'image' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:5120',
            ],
            'link' => ['nullable', 'url', 'max:500'],
            'cta_label' => ['nullable', 'string', 'max:50'],
        ]);

        $sortOrder = (Banner::query()->max('sort_order') ?? 0) + 1;

        /*
         * Store poster on Laravel's public disk.
         *
         * Example:
         * storage/app/public/posters/abc123.jpg
         *
         * We store only the relative disk path in the database.
         */
        $path = $request->file('image')->store('posters', 'public');

        Banner::create([
            'title' => $validated['title'],
            'subtitle' => $validated['subtitle'] ?? null,
            'image_path' => $path,
            'link' => $validated['link'] ?? null,
            'cta_label' => $validated['cta_label'] ?? null,
            'is_active' => true,
            'sort_order' => $sortOrder,
        ]);

        return back()->with(
            'success',
            'Poster uploaded successfully.'
        );
    }

    /**
     * Permanently remove a poster and its associated storage file.
     */
    public function destroy(Banner $banner): \Illuminate\Http\RedirectResponse
    {
        $this->deleteStorageFile($banner->image_path);

        $banner->delete();

        return back()->with(
            'success',
            'Poster removed successfully.'
        );
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Return a fully-qualified public URL for an image.
     *
     * Supports:
     *  - Relative public-disk paths: posters/example.jpg
     *  - Legacy paths: banners/example.jpg
     *  - Existing absolute URLs.
     */
    private function resolveUrl(?string $path): string
    {
        if (!$path) {
            return '';
        }

        if (
            str_starts_with($path, 'http://') ||
            str_starts_with($path, 'https://')
        ) {
            return $path;
        }

        return asset(
            'storage/' . ltrim($path, '/')
        );
    }

    /**
     * Delete a file from Laravel's public storage disk.
     *
     * Handles both new relative paths and legacy full URLs.
     */
    private function deleteStorageFile(?string $path): void
    {
        if (!$path) {
            return;
        }

        /*
         * New records:
         * posters/example.jpg
         */
        if (
            !str_starts_with($path, 'http://') &&
            !str_starts_with($path, 'https://')
        ) {
            Storage::disk('public')->delete(
                ltrim($path, '/')
            );

            return;
        }

        /*
         * Legacy records:
         * http://127.0.0.1:8000/storage/posters/example.jpg
         */
        $parsedPath = parse_url($path, PHP_URL_PATH);

        if (!$parsedPath) {
            return;
        }

        if (str_starts_with($parsedPath, '/storage/')) {
            $storagePath = ltrim(
                substr($parsedPath, strlen('/storage/')),
                '/'
            );

            Storage::disk('public')->delete($storagePath);
        }
    }
}
