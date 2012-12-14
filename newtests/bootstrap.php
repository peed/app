<?php

/**
 * Bootstrap for PHP unit tests
 */

// Media wiki stuff:
$IP = dirname(__DIR__);
require __DIR__ . '/../includes/AutoLoader.php';
require __DIR__ . '/../includes/Defines.php';
define('MEDIAWIKI', 1);

// Libs autoloaders
require '../extensions/wikia/Search/Solarium/Autoloader.php';
Solarium_Autoloader::register();

// Class autoloader:
class CustomAutoloader {
	private $classes = array();

	private $classMapping = array(
		'F' => 'WikiaSuperFactory',
	);

	public function loadClass($className) {
		if (isset($this->classMapping[$className])) {
			$className = $this->classMapping[$className];
		}

		if (isset($this->classes[$className])) {
			require_once $this->classes[$className];
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

// Hack for App;
class App extends WikiaAppMock {
}
F:app();
