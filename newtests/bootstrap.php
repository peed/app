<?php

/**
 * Bootstrap for PHP unit tests
 */

// Autoloader:
class CustomAutoloader {
	private $classes = array();

	private $classMapping = array(
		'F' => 'WikiaSuperFactory'
	);

	public function loadClass($className) {
		if (isset($this->classMapping[$className])) {
			$className = $this->classMapping[$className];
		}

		if (isset($this->classes[$className])) {
			require $this->classes[$className];
		}
	}

	public function __construct() {
		$it = new RecursiveDirectoryIterator(dirname(__DIR__));
		foreach (new RecursiveIteratorIterator($it) as $file) {
			$filename = $file->getFilename();
			if (strpos($filename, '.php')) {
				$className = preg_replace('/(\.class)?\.php/', '', $filename);
				$this->classes[$className] = $file->getPathname();
			}
		}
	}
}

spl_autoload_register(array(new CustomAutoloader(), 'loadClass'));

