<?php

/**
 * @group SaneTest
 */
class WikiaValidatorUrlTest extends PHPUnit_Framework_TestCase {

	/* @var $validator WikiaValidatorUrl */
	private $validator;

	protected function setUp() {
		$this->validator = new WikiaValidatorUrl();
		runkit_function_add('wfMsg', '$msg', 'return $msg;');
	}

	protected function tearDown() {
		runkit_function_remove('wfMsg');
	}

	/**
	 * @dataProvider UrlsDataProvider
	 */
	public function testUrls($string, $isUrl) {
		$result = $this->validator->isValid($string);
		$this->assertEquals($isUrl, $result);
	}

	public function UrlsDataProvider() {
		return array(
			array('http://www.wikia.com',true),
			array('www.wikia.com',true),
			array('wikia.com',true),
			array('http://www.wikia.com/?action=purge',true),
			array('http://www.wikia.com/#wiki',true),
			array('http://pl.callofduty.wikia.com/wiki/Call_of_Duty:_Black_Ops',true),
			array('https://www.wikia.com/Wikia',true),
			array('www.aol',true), // this is ok for regexp validation
			array('wikia',false),
			array('http://wikia',false),
		);
	}
}
