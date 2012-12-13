<?php

/**
 * Bootstrap for PHP unit tests
 */

// Autoloader:
class CustomAutoloader {
	private static $classes = array();

	private $classMapping = array(
		'F' => 'WikiaSuperFactory'
	);

	public function loadClass($className) {
		$this->getClasses();

		if (isset($this->classMapping[$className])) {
			$className = $this->classMapping[$className];
		}

		if (isset(self::$classes[$className])) {
			require self::$classes[$className];
		}
	}

	private function getClasses() {
		if (empty(self::$classes)) {
			$it = new RecursiveDirectoryIterator(dirname(__DIR__));
			foreach (new RecursiveIteratorIterator($it) as $file) {
				$filename = $file->getFilename();
				if (strpos($filename, '.php')) {
					$className = preg_replace('/(\.class)?\.php/', '', $filename);
					self::$classes[$className] = $file->getPathname();
				}
			}
		}
	}
}

spl_autoload_register(array(new CustomAutoloader(), 'loadClass'));

