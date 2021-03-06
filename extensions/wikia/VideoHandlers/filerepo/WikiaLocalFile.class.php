<?php

/* Wikia wrapper on LocalFile.
 * Alter some functionality allow using thumbnails as a representative of videos in file structure.
 * Works as interface, logic should go to WikiaLocalFileShared
 */

class WikiaLocalFile extends LocalFile {

	protected $oLocalFileLogic = null; // Leaf object

	// obligatory contructors

	/**
	 * Create a LocalFile from a title
	 * Do not call this except from inside a repo class.
	 *
	 * Note: $unused param is only here to avoid an E_STRICT
	 */
	static function newFromTitle( $title, $repo, $unused = null ) {
		return new static( $title, $repo );
	}

	/**
	 * Create a LocalFile from a title
	 * Do not call this except from inside a repo class.
	 */
	static function newFromRow( $row, $repo ) {
		$title = Title::makeTitle( NS_FILE, $row->img_name );
		$file = new static( $title, $repo );
		$file->loadFromRow( $row );
		return $file;
	}

	/**
	 * Create a LocalFile from a SHA-1 key
	 * Do not call this except from inside a repo class.
	 */
	static function newFromKey( $sha1, $repo, $timestamp = false ) {

		wfProfileIn( __METHOD__ );

		$dbr = $repo->getSlaveDB();

		# Polymorphic function name to distinguish foreign and local fetches

		$conds = array( 'img_sha1' => $sha1 );
		if( $timestamp ) {
			$conds['img_timestamp'] = $timestamp;
		}
		$row = $dbr->selectRow( 'image', self::getCacheFields( 'img_' ), $conds, __METHOD__ );

		wfProfileOut( __METHOD__ );

		if( $row ) {
			return static::newFromRow( $row, $repo );
		} else {
			return false;
		}
	}

	/* Composite/Leaf interface
	 *
	 * if no method of var found in current object tries to get it from $this->oLocalFileLogic
	 */

	function __construct( $title, $repo ){
		parent::__construct( $title, $repo );

	}

	function  __call( $name, $arguments ){
		if ( method_exists( $this->getLocalFileLogic(), $name ) ){
			return call_user_func_array( array( $this->getLocalFileLogic(), $name ), $arguments );
		} else {
			throw new Exception( 'Method ' .get_class( $this->getLocalFileLogic() ).'::' . $name . ' does not exist' );
		}
	}

	function __set( $name, $value ){
		if ( !isset( $this->$name ) && isset( $this->getLocalFileLogic()->$name ) ){
			$this->getLocalFileLogic()->$name = $value;
		} else {
			$this->$name = $value;
		}
	}

	function __get( $name ){
		if( isset( $this->$name ) ) {
			return $this->$name;
		} else if ( isset( $this->getLocalFileLogic()->$name ) ) {
			return $this->getLocalFileLogic()->$name;
		} else {
			return false;
		}
	}

	protected function getLocalFileLogic() {
		if ( empty( $this->oLocalFileLogic ) ){
			$this->oLocalFileLogic = F::build( 'WikiaLocalFileShared', array( $this ) );
		}
		return $this->oLocalFileLogic;
	}

	// No everything can be transparent, because __CALL skips already defined methods.
	// These methods work as a layer of communication between this class and SharedLogic

	function getHandler(){
		wfProfileIn( __METHOD__ );
		if ( !isset( $this->handler ) ) {
			parent::getHandler();
			$this->getLocalFileLogic()->afterGetHandler($this->handler);
		}
		wfProfileOut( __METHOD__ );
		return $this->handler;
	}

	function setProps( $info ) {
		wfProfileIn( __METHOD__ );
		parent::setProps( $info );
		$this->getLocalFileLogic()->afterSetProps();
		wfProfileOut( __METHOD__ );
	}

	function loadFromFile() {
		wfProfileIn( __METHOD__ );
		$this->getLocalFileLogic()->beforeLoadFromFile();
		parent::loadFromFile();
		$this->getLocalFileLogic()->afterLoadFromFile();
		wfProfileOut( __METHOD__ );
	}
}