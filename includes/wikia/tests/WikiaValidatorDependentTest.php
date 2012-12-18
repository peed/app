<?php

class WikiaValidatorDependentTest extends WikiaBaseTest {
	
	public function testIsValidWithInvalidConfigParams() {
		$wikiaValidatorStringStub = $this->getMock('WikiaValidatorString');
		
		//no dependentField passed
		$validator = new WikiaValidatorDependent(array(
			'required' => false,
			'ownValidator' => $wikiaValidatorStringStub,
			'dependencyData' => array(),
			'dependentFields' => array()
		));
		$this->setExpectedException('Exception');
		$validator->isValid('testValue');

		//no ownValidator passed
		$validator = new WikiaValidatorDependent(array(
			'required' => false,
			'dependencyData' => array(),
			'dependentFields' => array('aField' => array()),
		));
		$this->setExpectedException('Exception');
		$validator->isValid('testValue');

		//invalid ownValidator passed
		$validator = new WikiaValidatorDependent(array(
			'required' => false,
			'ownValidator' => new stdClass(),
			'dependencyData' => array(),
			'dependentFields' => array('aField' => array()),
		));
		$this->setExpectedException('Exception');
		$validator->isValid('testValue');
	}

	public function testIsValid() {
		$wikiaValidatorStringStub = $this->getMock('WikiaValidatorString');
		$wikiaValidatorStringStub->expects($this->any())->method('isValid')->will($this->returnValue(true));

		//validate the field only if "aFormFieldName" value is not empty ('dependentFieldCondition' => WikiaValidatorDependent::CONDITION_NOT_EMPTY) 
		$validator = new WikiaValidatorDependent(array(
			'required' => false,
			'ownValidator' => $wikiaValidatorStringStub,
			'dependencyData' => array(),
			'dependentFields' => array('aField' => array('validator' => $wikiaValidatorStringStub))
		));

		//CORRECT dependent field not empty, so we validate & own validator's method isValid() returns true
		$this->assertEquals(true, $validator->isValid('aValue'));

		//CORRECT -- dependent field empty, so we won't validate -- no error
		$wikiaValidatorStringStub->expects($this->any())->method('isValid')->will($this->returnValue(false));
		$this->assertEquals(true, $validator->isValid('aValue'));

		//FAILED -- dependent field not empty but own validator's method isValid() returns false
		$wikiaValidatorStringStub->expects($this->any())->method('isValid')->will($this->returnValue(true));
		$internalWikiaValidatorStringStub = $this->getMock('WikiaValidatorString');
		$internalWikiaValidatorStringStub->expects($this->any())->method('isValid')->will($this->returnValue(false));
		$validator = new WikiaValidatorDependent(array(
			'required' => false,
			'ownValidator' => $internalWikiaValidatorStringStub,
			'dependencyData' => array(),
			'dependentFields' => array('aField' => array('validator' => $wikiaValidatorStringStub))
		));

		$this->assertEquals(false, $validator->isValid('aValue'));
	}
	
}