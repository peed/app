<?php

/**
 * Supports and implements ResourceLoader enhancement to
 * combine and minify contents of articles coming from
 * local wiki as well as other wikis.
 *
 * @author Sean Colombo
 * @author Wladyslaw Bodzek
 */
class ArticlesAsResources {

	// application
	private $app;
	
	// Cached (hard-coded) IDs of popular wikis
	// (may be we should not do it)
	const COMMUNITY_WIKI_ID = 177;
	const DEV_WIKI_ID = 7931;

	// Domain suffix that is used as a fallback when finding wiki
	// by its domain name
	const WIKIA_DEFAULT_DOMAIN_SUFFIX = '.wikia.com';


	function __construct() {
		$this->app = WF::build('App');
	}

	/**
	 * Debug logging
	 *
	 * @param string $method - name of the method
	 * @param string $msg - log message to be added
	 */
	private function log($method, $msg) {
		$this->app->wf->debug($method  . ": {$msg}\n");
	}

	/**
	 * Transforms value from GET only= into corresponding
	 * ResourceLoaderWikiModule type= value.
	 *
	 * Basically it's just stripping the last "s" from the input
	 * for "scripts" and "styles".
	 *
	 * @param $type string
	 * @return bool|string
	 */
	protected function getTypeByOnly( $type ) {
		switch ( $type ) {
			case 'scripts': // from only=scripts
				return 'script'; // type=script
			case 'styles': // only=styles
				return 'style'; // type=style
		}
		return false;
	}

	/**
	 * Get wiki ID by its db name
	 *
	 * @param $dbName string Database name
	 * @return int Wiki ID
	 */
	protected function getCityIdByDbName( $dbName ) {
		$id = false;
		if ( $dbName === 'c' ) {
			$id = self::COMMUNITY_WIKI_ID;
		}
		if ( $id === false ) {
			$id = WikiFactory::DBtoID($dbName);
		}
		return $id;
	}

	/**
	 * Get wiki ID by its url. Handles both "dev.wikia.com" and "dev"
	 * (in the second pass it appends ".wikia.com" at the end of the supplied value).
	 *
	 * @param $url string
	 * @return int Wiki ID
	 */
	protected function getCityIdByUrl( $url ) {
		$id = null;
		if ( $url === 'dev' ) {
			$id = self::DEV_WIKI_ID;
		}
		if ( $id === null ) {
			$id = WikiFactory::DomainToID($url);
		}
		if ( $id === null ) {
			$id = WikiFactory::DomainToID($url . self::WIKIA_DEFAULT_DOMAIN_SUFFIX );
		}
		if ( $id === null ) {
			$id = false;
		}

		return $id;
	}

	/**
	 * Parser user-supplied list of articles in various formats
	 * to the other one that is understood by ResourceLoaderCustomWikiModule.
	 *
	 * Supported forms are:
	 * - (l|local):<page> - page from local wiki
	 * - (w|remote|external):<dbname>:<page> - search by wiki dbname
	 * - (u|url):<url_or_its_part>:<page> - search by wiki exact url or that one suffixed by ".wikia.com"
	 * - <page> - page from local wiki
	 *
	 * Output format is:
	 * - lolal wiki: array( 'title' => '<page>' )
	 * - other wiki: array( 'cityId' => <numeric_id>, 'title' => '<page>' )
	 *
	 * @param $list array
	 * @return array
	 */
	protected function parseArticleNames( $list ) {
		$articles = array();
		foreach ($list as $k => $name) {
			$matches = array();
			if (preg_match("/^(?:l|local):(.*)\$/",$name,$matches)) {
				$articles[] = array(
					'title' => $matches[1],
				);
			} elseif (preg_match("/^(?:w|remote|external):([^:]+):(.*)\$/",$name,$matches)) {
				$articles[] = array(
					'cityId' => $this->getCityIdByDbName( $matches[1] ),
					'title' => $matches[2],
				);
			} elseif (preg_match("/^(?:u|url):([^:]+):(.*)\$/",$name,$matches)) {
				$articles[] = array(
					'cityId' => $this->getCityIdByUrl( $matches[1] ),
					'title' => $matches[2],
				);
			} else {
				$articles[] = array(
					'title' => $name,
				);
			}
		}

		return $articles;
	}

	/**
	 * Hook handler.
	 *
	 * If mode equals 'articles' in the request, bootstraps fake module and reinitialize
	 * ResourceLoaderContext object to include the just-defined fake module.
	 *
	 * @param $resourceLoader ResourceLoader
	 * @param $context ResourceLoaderContext
	 * @return bool
	 */
	public function onResourceLoaderBeforeRespond( $resourceLoader, ResourceLoaderContext &$context ) {
		/* @var $request WebRequest */
		$request = $context->getRequest();
		if ( $request->getVal( 'mode' ) !== 'articles' ) {
			return true;
		}

		$only = $context->getOnly();
		$type = $this->getTypeByOnly($only);
		if ( empty( $type ) ) {
			return true;
		}

		$articles = $request->getVal('articles');
		$articles = explode('|',$articles);
		if ( empty( $articles ) ) {
			return true;
		}

		// prepare fake ResourceLoader module metadata
		$moduleName = md5( serialize( array( $articles, $only, $context->getHash() ) ) );
		$moduleFullName = 'wikia.fake.articles.' . $moduleName;
		$moduleInfo = array(
			'class' => 'ResourceLoaderCustomWikiModule',
			'articles' => $this->parseArticleNames( $articles ),
			'type' => $type,
		);

		// register new fake module
		$resourceLoader->register($moduleFullName,$moduleInfo);

		// reinitialize ResourceLoader context
		$request->setVal('modules',$moduleFullName);
		$context = new ResourceLoaderContext( $resourceLoader, $request );

		return true;
	}

	public function onTopScripts( &$vars ) {
		global $wgArticlesAsResourcesExperimentalMagic;
		if ( !empty( $wgArticlesAsResourcesExperimentalMagic ) ) {
			$vars['wgArticlesAsResources'] = true;
		}
		return true;
	}

}