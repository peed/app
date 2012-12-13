<?php


class App {
	public $wf;
	public $wg;

	public function __construct() {
		$this->wf = new WikiaFunctionWrapper();
	}
}

class WikiaFunctionWrapper {
	public function __call($method, $args) {
		return $args;
	}
}

/**
 * Bootstrap for PHP unit tests
 */

// Autoloader:
class CustomAutoloader {
	private $classes = array();

	private $classMapping = array(
		'F' => 'WikiaSuperFactory',
		'DatabaseBase' => 'Database',
		'Field' => 'DatabaseUtility'
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

require __DIR__ . '/../includes/Defines.php';
require __DIR__ . '/../includes/Exception.php';

