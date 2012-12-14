<?php

class WikiaAppMockFakeTestCase extends PHPUnit_Framework_TestCase {
}

class WikiaAppMockWf {
	private $methods = [];

	public function __call($funcName, $funcArgs) {
		return call_user_func_array($this->methods[strtolower($funcName)], $funcArgs);
	}

	public function addMethod($funcName, $funcMock) {
		$this->methods[strtolower($funcName)] = [$funcMock, 'run'];
	}
}

class WikiaAppMockRegistry {
	static private $instances = [];

	static public function register($mock) {
		$key = mt_rand(0, 10000000);
		self::$instances[$key] = $mock;
		return $key;
	}

	static public function get($key) {
		return self::$instances[$key];
	}
}

class WikiaAppMock {

	/**
	 * Test case object
	 * @var PHPUnit_Framework_TestCase
	 */
	private $testCase = null;
	private $methods = array();
	private $mockedGlobals = array();

	public $wg;
	public $wf;

	public function __construct() {
		$this->testCase = new WikiaAppMockFakeTestCase();
		$globalRegistryMock = null;
		$functionWrapperMock = null;

		$globalRegistryMock = $this->testCase->getMock( 'WikiaGlobalRegistry', array( 'get', 'set' ) );
		$globalRegistryMock->expects( $this->testCase->any() )
			->method( 'get' )
			->will( $this->testCase->returnCallback(array( $this, 'getGlobalCallback')) );

		$this->wg = $globalRegistryMock;
		$this->wf = new WikiaAppMockWf();
	}

	public function init() {
	}

	public function mockGlobalVariable($globalName, $returnValue) {
		if(!in_array( 'getGlobal', $this->methods )) {
			$this->methods[] = 'getGlobal';
		}
		$this->mockedGlobals[$globalName] = array( 'value' => $returnValue );
	}

	/**
	 * @brief mock global function
	 * @param string $functionName
	 * @param mixed $returnValue
	 * @param int $callsNum
	 * @param array $inputParams  // FIXME: not used
	 *
	 * @todo support params
	 */
	public function mockGlobalFunction($functionName, $returnValue, $callsNum = 1, $inputParams = array() ) {
		$funcMock = $this->testCase->getMock('stdClass', ['run']);
		$funcMock->expects($this->testCase->any())
			->method('run')
			->will($this->testCase->returnValue($returnValue));
		$this->wf->addMethod($functionName, $funcMock);
	}

	// If the global variable is not being overridden, return the actual global variable
	public function getGlobalCallback( $globalName ) {
		return ( isset($this->mockedGlobals[$globalName]['value']) ? $this->mockedGlobals[$globalName]['value'] : $GLOBALS[$globalName] );
	}

	public function registerFapp() {
		$key = WikiaAppMockRegistry::register($this);
		runkit_method_redefine('F', 'app', '', 'return WikiaAppMockRegistry::get(' . $key . ');', (RUNKIT_ACC_PUBLIC | RUNKIT_ACC_STATIC));
	}
}
