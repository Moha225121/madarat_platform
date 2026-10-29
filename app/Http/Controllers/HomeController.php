<?php

namespace App\Http\Controllers;

use App\Models\HomepageAdvertisement;
use App\Models\Job;
use App\Services\UploadStorage;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $advertisements = HomepageAdvertisement::query()
            ->orderBy('id')
            ->get(['id', 'image_path', 'alt_text'])
            ->filter(fn (HomepageAdvertisement $advertisement): bool => HomepageAdvertisement::isManagedImagePath($advertisement->image_path))
            ->map(fn (HomepageAdvertisement $advertisement): array => [
                'id' => $advertisement->id,
                'image_url' => UploadStorage::mediaUrl($advertisement->image_path),
                'alt_text' => $advertisement->alt_text,
            ])
            ->values();

        return Inertia::render('Welcome', [
            'featuredJobs' => Job::with('companyProfile')->where('status', 'published')->latest()->take(6)->get(),
            'advertisements' => $advertisements,
        ]);
    }
}
