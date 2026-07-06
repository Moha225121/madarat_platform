<?php

namespace App\Http\Controllers;

use App\Models\Job;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Welcome', [
            'featuredJobs' => Job::with('companyProfile')->where('status', 'published')->latest()->take(6)->get(),
        ]);
    }
}
