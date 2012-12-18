<?php

/**
 * @desc Validates a field only if other field in the form is/is not empty
 */
class WikiaValidatorDependent extends WikiaValidator {
	protected function config( array $options = array() ) {
		$this->setOption('ownValidator', 0);
		$this->setOption('dependencyData', array());
		$this->setOption('dependentFields', array());
	}

	public function isValidInternal($value = null) {
		$ownValidator = $this->getOption('ownValidator');

		if( $ownValidator === null || !($ownValidator instanceof WikiaValidator) ) {
			throw new Exception( 'WikiaValidatorDepend: own validator is not an instance of WikiaValidator' );
		}

		if( $this->areDependentFieldsValid(null) && !$ownValidator->isValid($value) ) {
			$this->setError( $ownValidator->getError() );
			return false;
		}
		
		return true;
	}

	public function isValid($value = null) {
		return $this->isValidInternal($value);
	}
	
	protected function areDependentFieldsValid($dependentFieldValue) {
		$validationConditions = $this->getOption('dependentFields');
		$data = $this->getOption('dependencyData');
		$valid = true;

		foreach ($validationConditions as $fieldName => $field) {
			if (!empty($field['validator'])) {
				$fieldData = isset($data[$fieldName]) ? $data[$fieldName] : null;

				if (!$field['validator']->isValid($fieldData)) {
					$valid = false;
				}
			}
		}

		return $valid;
	}

}
