<?php

namespace Database\Seeders;

use App\Models\Job;
use App\Services\JobCategoryClassifier;
use Illuminate\Database\Seeder;

class JobCategoryBackfillSeeder extends Seeder
{
    public function run(JobCategoryClassifier $classifier): void
    {
        Job::query()
            ->whereNull('category')
            ->select(['id', 'title', 'responsibilities'])
            ->chunkById(100, function ($jobs) use ($classifier): void {
                foreach ($jobs as $job) {
                    $classification = $classifier->classify(
                        $job->title,
                        $job->responsibilities,
                    );

                    if ($classification === null) {
                        continue;
                    }

                    Job::query()
                        ->whereKey($job->id)
                        ->whereNull('category')
                        ->update($classification);
                }
            });
    }
}
