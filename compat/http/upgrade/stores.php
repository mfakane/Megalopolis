<?php
declare(strict_types=1);

require '/app/vendor/autoload.php';
use Megalopolis\Tests\Support\UpgradeFixtures;

// Fixed, isolated Docker volume paths only. Never replace an existing file.
function writeNew(string $path, string $bytes, bool $ownedByApp = true): void
{
    $stream = fopen($path, 'xb');
    if ($stream === false) throw new RuntimeException('Refusing to overwrite: ' . $path);
    if (fwrite($stream, $bytes) !== strlen($bytes)) throw new RuntimeException('Short write: ' . $path);
    fclose($stream);
    if ($ownedByApp) {
        chown($path, 1000);
        chgrp($path, 1000);
    }
}

foreach (['/baseline', '/candidate', '/restored'] as $directory) {
    chown($directory, 1000);
    chgrp($directory, 1000);
}
foreach (['data.sqlite', 'search.sqlite'] as $name) {
    switch ($argv[1] ?? '') {
        case 'seed':
            foreach (['/baseline/', '/candidate/'] as $prefix) {
                writeNew($prefix . $name, UpgradeFixtures::bytes($name));
            }
            break;
        case 'snapshot':
            $bytes = file_get_contents('/baseline/' . $name);
            if (hash('sha256', $bytes) !== UpgradeFixtures::HASHES[$name]) {
                throw new RuntimeException('Backup must precede HTTP / migration: ' . $name);
            }
            writeNew('/results/pre-upgrade-' . $name, $bytes, false);
            break;
        case 'restore':
            $bytes = file_get_contents('/results/pre-upgrade-' . $name);
            if (hash('sha256', $bytes) !== UpgradeFixtures::HASHES[$name]) {
                throw new RuntimeException('Pre-upgrade backup changed: ' . $name);
            }
            writeNew('/restored/' . $name, $bytes);
            break;
        default:
            throw new RuntimeException('Expected seed, snapshot or restore');
    }
}
echo ($argv[1] ?? '') . ": data and search SQLite databases verified\n";
