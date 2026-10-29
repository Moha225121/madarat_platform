<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\JobSeekerProfile;
use App\Services\UploadStorage;
use App\Storage\DatabaseFilesystemAdapter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UploadController extends Controller
{
    public function media(string $path, UploadStorage $storage): StreamedResponse
    {
        abort_unless(UploadStorage::safePath($path, UploadStorage::MEDIA_DIRECTORIES), 404);
        abort_unless(in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'webp', 'gif'], true), 404);
        $disk = $storage->media();
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, headers: ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'public, max-age=300']);
    }

    public function cv(Request $request, JobSeekerProfile $profile, UploadStorage $storage): StreamedResponse
    {
        $actor = $request->user();
        abort_unless($profile->user?->role === 'job_seeker', 404);
        $allowed = $actor->role === 'admin'
            || ($actor->role === 'job_seeker' && $profile->user_id === $actor->id)
            || ($actor->role === 'employer' && Application::query()
                ->where('user_id', $profile->user_id)
                ->whereHas('job.companyProfile', fn ($query) => $query->where('user_id', $actor->id))->exists());
        abort_unless($allowed, 403);

        $disk = $storage->cvDisk($profile->cv_path ?? '');
        abort_unless($disk, 404);
        $filename = 'cv.'.pathinfo($profile->cv_path, PATHINFO_EXTENSION);
        if ($disk->getAdapter() instanceof DatabaseFilesystemAdapter) {
            abort_unless($disk->getAdapter()->ownedBy($profile->cv_path, $profile->user_id), 404);
            $filename = $disk->getAdapter()->originalName($profile->cv_path);
        }

        return $disk->download($profile->cv_path, $filename, [
            'Cache-Control' => 'private, no-store',
            'Content-Type' => UploadStorage::cvMimeType($profile->cv_path),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
