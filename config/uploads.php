<?php

return [
    // Local previews may read existing CVs until they have been copied privately.
    // Production must import the approved copies before switching to this code.
    'legacy_cv_reads' => env('UPLOADS_LEGACY_CV_READS', false),
    'limits_kb' => [
        'cv' => (int) env('UPLOAD_MAX_CV_KB', 15360),
        'image' => (int) env('UPLOAD_MAX_IMAGE_KB', 2048),
        'course' => (int) env('UPLOAD_MAX_COURSE_KB', 4096),
        'advertisement' => (int) env('UPLOAD_MAX_ADVERTISEMENT_KB', 5120),
    ],
];
