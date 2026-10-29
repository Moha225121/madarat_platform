<?php

// Local artifact generator: six reviewed code files only, never env/user data.
if (PHP_SAPI !== 'cli' || $argc !== 2) {
    throw new RuntimeException('Pass a fresh local installer output path.');
}
$files = ['app/Storage/StoredFileSchema.php', 'app/Storage/StoredFileRepository.php', 'app/Storage/StoredFileRescue.php',
    'tools/uploads/database-rescue.php', 'tools/uploads/snapshot.php', 'tools/uploads/compare.php'];
$payload = [];
foreach ($files as $relative) {
    $code = file_get_contents(__DIR__.'/../../'.$relative);
    $payload[$relative] = ['sha256' => hash('sha256', $code), 'code' => base64_encode($code)];
}
$installer = "<?php\n// CODE STAGING ONLY. Does not connect to a database or run the rescue.\n";
$installer .= '$payload = '.var_export($payload, true).";\n";
$installer .= <<<'CODE'
umask(0077);
$root = sys_get_temp_dir().'/madarat-storage-rescue-'.bin2hex(random_bytes(16));
if (!mkdir($root,0700)) { throw new RuntimeException('Cannot create staging directory.'); }
foreach ($payload as $relative=>$entry) {
    $code=base64_decode($entry['code'],true);
    if ($code===false || hash('sha256',$code)!==$entry['sha256']) { throw new RuntimeException('Code digest mismatch.'); }
    $path=$root.'/'.$relative;
    if (!is_dir(dirname($path)) && !mkdir(dirname($path),0700,true)) { throw new RuntimeException('Cannot create private code directory.'); }
    $stream=fopen($path,'xb');
    if (!$stream || fwrite($stream,$code)!==strlen($code)) { throw new RuntimeException('Incomplete code staging.'); }
    fclose($stream);
    if (hash_file('sha256',$path)!==$entry['sha256']) { throw new RuntimeException('Staged code mismatch.'); }
}
echo "Reviewed code staged only: ".$root.PHP_EOL;
echo "No database or source-file action executed.".PHP_EOL;
CODE;
$output = fopen($argv[1], 'xb');
if (! $output || fwrite($output, $installer) !== strlen($installer)) {
    throw new RuntimeException('Cannot create exclusive installer artifact.');
}
fclose($output);
echo json_encode(['installer' => $argv[1], 'code_files' => count($files), 'sha256' => hash('sha256', $installer), 'bytes' => strlen($installer)]), PHP_EOL;
