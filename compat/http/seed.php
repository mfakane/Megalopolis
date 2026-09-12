<?php
// Run once, before either application starts. Never overwrite a used test volume.
foreach (['/baseline', '/candidate'] as $directory) {
    foreach (['data.sqlite', 'search.sqlite'] as $name) {
        $target = $directory . '/' . $name;
        if (file_exists($target)) {
            throw new RuntimeException('Refusing to overwrite ' . $target);
        }
        $input = gzopen('/app/tests/Fixtures/r46/' . $name . '.gz', 'rb');
        $output = fopen($target, 'xb');
        if ($input === false || $output === false || stream_copy_to_stream($input, $output) === false) {
            throw new RuntimeException('Cannot restore ' . $target);
        }
        gzclose($input);
        fclose($output);
        chown($target, 1000);
        chgrp($target, 1000);
    }
    chown($directory, 1000);
    chgrp($directory, 1000);
}
