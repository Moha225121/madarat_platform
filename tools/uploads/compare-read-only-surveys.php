<?php

require_once __DIR__.'/ProductionReadOnlySurvey.php';
if (PHP_SAPI !== 'cli' || $argc !== 3) {
    throw new RuntimeException('Pass the two sanitized JSON outputs.');
}
$first = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$second = json_decode(file_get_contents($argv[2]), true, flags: JSON_THROW_ON_ERROR);
echo json_encode(ProductionReadOnlySurvey::compare($first, $second), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), PHP_EOL;
