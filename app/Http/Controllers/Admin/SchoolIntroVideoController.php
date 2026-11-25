<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolIntroVideo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SCHOOL INTRODUCTION VIDEO — admin-managed media for the public website.
 *
 * Only one intro video may be active at a time. Uploading a new video
 * automatically deactivates the previous one. Files live on Laravel's public
 * storage disk; only the path + metadata are stored in the database.
 */
class SchoolIntroVideoController extends Controller
{
    /** Show the current intro video (edit/replace/remove UI). */
    public function show(): Response
    {
        $video = SchoolIntroVideo::query()->active()->first();

        return Inertia::render('Admin/Settings/Video', [
            'video' => $video ? [
                'id' => $video->id,
                'path' => $video->path,
                'url' => $video->url(),
                'original_name' => $video->original_name,
                'mime_type' => $video->mime_type,
                'file_size' => $video->file_size,
                'is_active' => $video->is_active,
            ] : null,
        ]);
    }

    /** Upload (or replace) the school introduction video. */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'video' => [
                'required',
                'file',
                'mimetypes:video/mp4,video/webm',
                'max:15360', // 15 MB = 15 * 1024 KB
            ],
        ]);

        $file = $request->file('video');

        // Deactivate any existing active video so only one is active.
        SchoolIntroVideo::query()->where('is_active', true)->update(['is_active' => false]);

        // Store on the public disk under videos/ with a safe generated name.
        $storedPath = $file->store('videos', 'public');

        // Detect the real MIME type from the file contents.
        $mimeType = $file->getMimeType();

        $video = SchoolIntroVideo::create([
            'path' => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $mimeType,
            'file_size' => $file->getSize(),
            'is_active' => true,
        ]);

        return back()->with(
            'success',
            'School introduction video uploaded successfully.'
        );
    }

    /** Replace the existing video with a new upload. */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'video' => [
                'required',
                'file',
                'mimetypes:video/mp4,video/webm',
                'max:15360', // 15 MB
            ],
        ]);

        $file = $request->file('video');

        $video = SchoolIntroVideo::query()->active()->first();

        if ($video) {
            // Remove the old physical file before storing the replacement.
            $video->deleteFile();
            $video->update([
                'path' => $file->store('videos', 'public'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'is_active' => true,
            ]);
        } else {
            SchoolIntroVideo::create([
                'path' => $file->store('videos', 'public'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'is_active' => true,
            ]);
        }

        return back()->with(
            'success',
            'School introduction video replaced successfully.'
        );
    }

    /** Remove the intro video and delete its storage file. */
    public function destroy(): RedirectResponse
    {
        $video = SchoolIntroVideo::query()->active()->first();

        if ($video) {
            $video->deleteFile();
            $video->delete();

            return back()->with(
                'success',
                'School introduction video removed successfully.'
            );
        }

        return back()->with(
            'error',
            'No video was found to remove.'
        );
    }
}
