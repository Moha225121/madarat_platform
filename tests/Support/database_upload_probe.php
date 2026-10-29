<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;

// Separate application process, restricted to the disposable local upload DBs.
if (getenv('APP_ENV') !== 'testing' || getenv('DB_HOST') !== '127.0.0.1' || getenv('DB_PORT') !== '55432'
    || ! in_array(getenv('DB_DATABASE'), ['madarat_upload_verify', 'madarat_upload_restore'], true)) {
    fwrite(STDERR, "Synthetic local probe only.\n");
    exit(1);
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->useStoragePath($argv[3]);
$app->make(Kernel::class)->bootstrap();
$disk = Storage::disk('documents');
if ($argv[1] === 'put') {
    $disk->put($argv[2], file_get_contents($argv[4]), ['visibility' => 'private', 'mime_type' => 'application/pdf']);
}
$stream = $disk->readStream($argv[2]);
$contents = stream_get_contents($stream);
fclose($stream);
echo json_encode(['pid' => getmypid(), 'bytes' => strlen($contents), 'sha256' => hash('sha256', $contents),
    'driver' => config('filesystems.disks.documents.driver'), 'local_files' => count(glob($argv[3].'/*') ?: [])]), PHP_EOL;
