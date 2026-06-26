<?php

$src = __DIR__ . '/app/Filament/Admin/Resources/ViaTransitos';
$dest = __DIR__ . '/app/Filament/Admin/Resources/RegistroTransitos';

function copy_dir($src, $dst) {
    $dir = opendir($src);
    @mkdir($dst);
    while (false !== ( $file = readdir($dir)) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
            if ( is_dir($src . '/' . $file) ) {
                copy_dir($src . '/' . $file, $dst . '/' . $file);
            }
            else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

if (!is_dir($dest)) {
    copy_dir($src, $dest);
}

function process_dir($dir) {
    $files = glob($dir . '/*');
    foreach ($files as $file) {
        if (is_dir($file)) {
            process_dir($file);
        } else {
            $content = file_get_contents($file);
            $content = str_replace('ViaTransito', 'RegistroTransito', $content);
            $content = str_replace('viaTransito', 'registroTransito', $content);
            $content = str_replace('via_transito', 'registro_transito', $content);
            file_put_contents($file, $content);

            $newName = str_replace('ViaTransito', 'RegistroTransito', basename($file));
            if ($newName !== basename($file)) {
                rename($file, dirname($file) . '/' . $newName);
            }
        }
    }
}

process_dir($dest);
echo "Done!\n";
