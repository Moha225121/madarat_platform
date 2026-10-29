<?php

// LOCAL generator only. The generated commands run from stdin with no staging.
if (PHP_SAPI !== 'cli' || $argc !== 2 || ! is_dir($argv[1])) {
    throw new RuntimeException('Pass an existing local output directory.');
}
$nonce = bin2hex(random_bytes(32));
$class = str_replace("\r\n", "\n", file_get_contents(__DIR__.'/ProductionReadOnlySurvey.php'));
$class = preg_replace('/\A<\?php\s*/', '', $class);
$files = [];
foreach (['65gtv' => '19dbe89becaa', '9t4z2' => '151c5e930b04'] as $suffix => $expectedTag) {
    $wrapper = '$nonce = '.var_export($nonce, true).";\n".'$expectedTag = '.var_export($expectedTag, true).";\n";
    $wrapper .= <<<'PHP'
try {
    $actualTag = substr(hash('sha256', gethostname()), 0, 12);
    if ($actualTag !== $expectedTag) {
        echo json_encode(['complete'=>false,'error'=>'instance_mismatch','actual_instance_tag'=>$actualTag]),PHP_EOL;
        exit(2);
    }
    $source='/workspace';
    require $source.'/vendor/autoload.php';
    $app=require $source.'/bootstrap/app.php';
    (new Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables)->bootstrap($app);
    (new Illuminate\Foundation\Bootstrap\LoadConfiguration)->bootstrap($app);
    (new Illuminate\Database\DatabaseServiceProvider($app))->register();
    $connection=$app['db']->connection();
    $identity=json_encode([$connection->getConfig('host'),$connection->getConfig('port')],JSON_THROW_ON_ERROR);
    $result=ProductionReadOnlySurvey::collect($connection->getPdo(),[
        'public'=>$source.'/storage/app/public','local'=>$source.'/storage/app/private'
    ],$nonce,$identity);
    echo json_encode($result,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT),PHP_EOL;
    if (!$result['complete']) { exit(2); }
} catch (Throwable $error) {
    echo json_encode(['complete'=>false,'error'=>'read_only_survey_failed',
        'sqlstate'=>$error instanceof PDOException ? (string)$error->getCode() : null]),PHP_EOL;
    exit(1);
}
PHP;
    $command = "php -d display_errors=0 -d log_errors=0 -d opcache.enable_cli=0 <<'MADARAT_READ_ONLY'\n<?php\n".$class."\n".$wrapper."\nMADARAT_READ_ONLY\n";
    $path = rtrim($argv[1], '/\\').'/dry-run-'.$suffix.'.sh';
    $stream = fopen($path, 'xb');
    if (! $stream || fwrite($stream, $command) !== strlen($command)) {
        throw new RuntimeException('Cannot create exclusive local command artifact.');
    }
    fclose($stream);
    $files[] = ['instance_suffix' => $suffix, 'path' => $path, 'sha256' => hash('sha256', $command)];
}
echo json_encode(['comparison_id' => hash('sha256', $nonce), 'commands' => $files], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
