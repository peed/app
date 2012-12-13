<?php

/**
 * Bootstrap for PHP unit tests
 */

// Autoloader:
class CustomAutoloader {
    public function loadClass($className) {
        $it = new RecursiveDirectoryIterator(dirname(__DIR__));
        $display = Array ( 'jpeg', 'jpg' );
        foreach (new RecursiveIteratorIterator($it) as $file) {
            $filename = $file->getFilename();
            if ($filename === $className . '.php'
                || $filename === $className . '.class.php'
            ) {
                require($file->getPathname());
                break;
            }
        }
    }
}

spl_autoload_register(array(new CustomAutoloader(), 'loadClass'));

