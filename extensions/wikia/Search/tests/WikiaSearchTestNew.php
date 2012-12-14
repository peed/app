<?php

require_once( 'WikiaSearchBaseTest.php' );

/**
 * @group SaneTest
 */
class WikiaSearchTest extends WikiaSearchBaseTest {

	// bugid: 64199 -- reset language code
	public function setUp() {
		parent::setUp();
		global $wgLanguageCode;
		$this->defaultLanguageCode = $wgLanguageCode;
		$wgLanguageCode = 'en';
	}
	public function tearDown() {
		global $wgLanguageCode;
		$wgLanguageCode = $this->defaultLanguageCode;
		parent::tearDown();
	}

	/**
	 * @covers WikiaSearch::getFilterQueryString
	 */
	public function testGetFilterQueryString()
	{
		$appMock = new WikiaAppMock();
		$appMock->mockGlobalVariable('wgLanguageCode', 'pl');
		$appMock->mockGlobalVariable('wgWikiaSearchSupportedLanguages', array('pl', 'en'));
		$appMock->registerFapp();

		$solarMock = $this->getMock('Solarium_Client', array('setAdapter'));

		//$this->mockClass( 'Solarium_Client', $this->getMock( 'Solarium_Client', array('setAdapter') ) );

		$mockCityId 	= 123;
		$mockHub		= 'Games';
		$wikiaSearch	= new WikiaSearch($solarMock);
		$searchConfig	= F::build( 'WikiaSearchConfig' );
		$searchConfig	->setCityId( $mockCityId );


		$method = new ReflectionMethod( 'WikiaSearch', 'getFilterQueryString' );
		$method->setAccessible( true );

		$this->assertEquals( "(wid:{$mockCityId}) AND (is_redirect:false)", $method->invoke( $wikiaSearch, $searchConfig ),
							'The default behavior for on-wiki search should be to filter query for wiki ID and against redirects.' );

		$searchConfig->setIncludeRedirects( true );

		$this->assertEquals( "(wid:{$mockCityId})", $method->invoke( $wikiaSearch, $searchConfig ),
							'If we have redirects configured to be included, we should not be filter against them in the filter query.' );

		$searchConfig->setVideoSearch( true );
		$searchConfig->setIncludeRedirects( false );
		$searchConfig->setIsInterWiki( true );

		$this->assertEquals( '(iscontent:true) AND (is_redirect:false)', $method->invoke( $wikiaSearch, $searchConfig),
							'An interwiki search should filter for content pages only.' );

		$searchConfig->setHub( $mockHub );

		$this->assertEquals( '(iscontent:true) AND (hub:Games) AND (is_redirect:false)', $method->invoke( $wikiaSearch, $searchConfig ),
							'An interwiki search with a hub should include the hub in the filter query.' );

	}

	/**
	 * @covers WikiaSearch::getBoostQueryString
	 */
	public function testGetBoostQueryString() {
		$appMock = new WikiaAppMock();
		$appMock->mockGlobalVariable('wgLanguageCode', 'en');
		$appMock->mockGlobalVariable('wgWikiaSearchSupportedLanguages', array('pl', 'en'));
		$appMock->registerFapp();


		$solarMock = $this->getMock( 'Solarium_Client', array('setAdapter') );

		$wikiaSearch	= new WikiaSearch($solarMock);
		$searchConfig	= F::build( 'WikiaSearchConfig' );

		$method = new ReflectionMethod( 'WikiaSearch', 'getBoostQueryString' );
		$method->setAccessible( true );

		$searchConfig->setQuery('foo bar');
		$this->assertEquals( '(html_en:\"foo bar\")^5 (title_en:\"foo bar\")^10',
							$method->invoke( $wikiaSearch, $searchConfig ),
							'WikiaSearch::getBoostQueryString should boost exact-match in quotes for html and title field'
							);

		$searchConfig->setQuery('"foo bar"');
		$this->assertEquals( '(html_en:\"foo bar\")^5 (title_en:\"foo bar\")^10',
					        $method->invoke( $wikiaSearch, $searchConfig ),
					        'WikiaSearch::getBoostQueryString should strip quotes from original query'
							);

		$searchConfig->setQuery("'foo bar'");
		$this->assertEquals( '(html_en:\"foo bar\")^5 (title_en:\"foo bar\")^10',
							 $method->invoke( $wikiaSearch, $searchConfig ),
					        'WikiaSearch::getBoostQueryString should strip quotes from original query'
							);

		$searchConfig	->setQuery		('foo bar wiki')
						->setIsInterWiki(true)
		;
		$this->assertEquals( '(html_en:\"foo bar\")^5 (title_en:\"foo bar\")^10 (wikititle_en:\"foo bar\")^15 -(host:answers)^10 -(host:respuestas)^10',
					        $method->invoke( $wikiaSearch, $searchConfig ),
					        'WikiaSearch::getBoostQueryString should remove "wiki" from searches,, include wikititle, and remove answers wikis'
							);
	}

	/**
	 * @covers WikiaSearch::sanitizeQuery
	 */
	public function testSanitizeQuery() {
		$solariumMock = $this->getMock( 'Solarium_Client', array('setAdapter') );

		$wikiaSearch	= new WikiaSearch($solariumMock);

		$method = new ReflectionMethod( 'WikiaSearch', 'sanitizeQuery' );
		$method->setAccessible( true );

		$this->assertEquals( '123 foo', $method->invoke( $wikiaSearch, '123foo' ),
							 'WikiaSearch::sanitizeQuery should split numbers and letters.'
							);

		$this->assertEquals( '\\+\\-\\&&\\||\\!\\(\\)\\{\\}\\[\\]\\^\\"\\~\\*\\?\\:\\\\',
							$method->invoke( $wikiaSearch, '+-&&||!(){}[]^"~*?:\\' ),
							'WikiaSearch::sanitizeQuery should escape lucene special characters.'
							);

		$this->assertEquals( '\"fame & glory\"',
							$method->invoke( $wikiaSearch, '&quot;fame &amp; glory&quot;' ),
							'WikiaSearch::sanitizeQuery shoudl decode HTML entities and escape any entities that are also Lucene special characters.'
							);
	}

	/**
	 * @covers WikiaSearch::getQueryClausesString
	 * @covers WikiaSearch::getInterWikiSearchExcludedWikis
	 */
	public function testGetQueryClausesString() {
		$expectedLanguageCode	= 'en';
		$mockContLang 			= new stdClass();
		$mockContLang->mCode	= $expectedLanguageCode;
		$mockPrivateWiki		= new stdClass();
		$mockPrivateWiki->cv_id	= 0;
		$mockCityId				= 123;

		$memcacheMock = $this->getMock( 'stdClass', array( 'get', 'set' ) );
		$memcacheMock
			->expects	( $this->any() )
			->method	( 'get' )
			->will		( $this->returnValue( null ) )
		;
		$memcacheMock
			->expects	( $this->any() )
			->method	( 'set' )
			->will		( $this->returnValue( null ) )
		;

		$wikiFactoryMock = $this->getMock( 'WikiFactory', array('getCityIDsFromVarValue', 'getVarByName' ) );
		$wikiFactoryMock
			->expects	( $this->any() )
			->method	( 'getCityIDsFromVarValue' )
			->will		( $this->returnValue( null ) )
		;
		$wikiFactoryMock
			->expects	( $this->any() )
			->method	( 'getVarByName' )
			->will		( $this->returnValue( $mockPrivateWiki ) )
		;

		$solariumMock = $this->getMock( 'Solarium_Client', array('setAdapter') ) ;
		$this->mockClass			( 'WikiFactory',						$wikiFactoryMock );


		$appMock = new WikiaAppMock();
		$appMock->mockGlobalFunction	( 'GetDb', 'asd');
		$appMock->mockGlobalFunction	( 'SharedMemcKey', 'asd');
		$appMock->mockGlobalVariable	( 'wgContLang',							$mockContLang );
		$appMock->mockGlobalVariable	( 'wgIsPrivateWiki',					false );
		$appMock->mockGlobalVariable	( 'wgCrossWikiaSearchExcludedWikis',	array( 123, 234 ) );
		$appMock->mockGlobalVariable	( 'wgCityId',							$mockCityId );
		$appMock->mockGlobalVariable	( 'wgMemc',								$memcacheMock );
		$appMock->mockGlobalVariable	( 'wgWikiaSearchSupportedLanguages', array('pl', 'en'));
		$appMock->registerFapp();


		$wikiaSearch	= new WikiaSearch($solariumMock);

		$searchConfig	= F::build( 'WikiaSearchConfig' );

		$method = new ReflectionMethod( 'WikiaSearch', 'getQueryClausesString' );
		$method->setAccessible( true );

		$searchConfig->setNamespaces( array(1, 2, 3) );

		$this->assertEquals(
				'((wid:123) AND ((ns:1) OR (ns:2) OR (ns:3)) AND (is_redirect:false))',
				$method->invoke( $wikiaSearch, $searchConfig ),
				'WikiaSearch::getQueryClauses by default should query for namespaces and wiki ID.'
		);

		$searchConfig->setVideoSearch( true );

		$expectedWithVideo = '(((wid:123) OR (wid:'.WikiaSearch::VIDEO_WIKI_ID.')) AND (is_video:true) AND ((ns:'.NS_FILE.')) AND (is_redirect:false))';
		$this->assertEquals(
				$expectedWithVideo,
				$method->invoke( $wikiaSearch, $searchConfig ),
				'WikiaSearch::getQueryClauses should search only for video namespaces in video search, and should only search for videos'
		);

		$searchConfig	->setVideoSearch	( false )
						->setIsInterWiki	( true );

		$this->assertEquals(
				'(-(wid:123) AND -(wid:234) AND (lang:en) AND (iscontent:true) AND (is_redirect:false))',
				$method->invoke( $wikiaSearch, $searchConfig ),
        		'WikiaSearch::getQueryClauses should exclude bad wikis, require the language of the wiki, and require content'
		);

		$searchConfig->setHub( 'Entertainment' );

		$this->assertEquals(
				'(-(wid:123) AND -(wid:234) AND (lang:en) AND (iscontent:true) AND (hub:Entertainment) AND (is_redirect:false))',
				$method->invoke( $wikiaSearch, $searchConfig ),
				'WikiaSearch::getQueryClauses by default should query for namespaces and wiki ID.'
		);

	}

	/**
	 * @covers WikiaSearch::getArticleMatch
	 */
	public function testGetArticleMatchHasMatch() {

		$solariumMock = $this->getMock( 'Solarium_Client') ;
		$wikiaSearch		= new WikiaSearch($solariumMock);
		$mockSearchConfig	= $this->getMock( 'WikiaSearchConfig', array( 'getOriginalQuery', 'hasArticleMatch', 'getArticleMatch' ) );
		$mockTitle			= $this->getMock( 'Title', array( 'getNamespace' ) );
		$mockArticle		= $this->getMock( 'Article', array(), array( $mockTitle ) );
		$mockArticleMatch	= $this->getMock( 'WikiaSearchArticleMatch', array(), array( $mockArticle ) );
		$mockTerm			= 'foo';

		// If there's already an article match set in the search config, return that
		$mockSearchConfig
			->expects	( $this->any() )
			->method	( 'getOriginalQuery' )
			->will		( $this->returnValue( $mockTerm ) )
		;
		$mockSearchConfig
			->expects	( $this->any() )
			->method	( 'hasArticleMatch' )
			->will		( $this->returnValue( true ) )
		;
		$mockSearchConfig
			->expects	( $this->any() )
			->method	( 'getArticleMatch' )
			->will		( $this->returnValue( $mockArticleMatch ) )
		;
		$this->assertInstanceOf( 'WikiaSearchArticleMatch', $wikiaSearch->getArticleMatch( $mockSearchConfig ),
								'A searchconfig with an article match should return its article match during WikiaSearch::getArticleMatch()' );
	}

	/**
	 * @covers WikiaSearch::getArticleMatch
	 */
	public function testGetArticleMatchWithNoMatch() {

		$solariumMock = $this->getMock( 'Solarium_Client') ;
		$wikiaSearch		= new WikiaSearch($solariumMock);
		$mockSearchConfig	= $this->getMock( 'WikiaSearchConfig', array( 'getOriginalQuery', 'hasArticleMatch', 'getArticleMatch' ) );
		$mockTitle			= $this->getMock( 'Title', array( 'getNamespace' ) );
		$mockArticle		= $this->getMock( 'Article', array(), array( $mockTitle ) );
		$mockArticleMatch	= $this->getMock( 'WikiaSearchArticleMatch', array(), array( $mockArticle ) );
		$mockSearchEngine	= $this->getMock( 'stdClass', array( 'getNearMatch' ) );
		$mockTerm			= 'foo';

		$mockSearchConfig
			->expects	( $this->any() )
			->method	( 'getOriginalQuery' )
			->will		( $this->returnValue( $mockTerm ) )
		;
		$mockSearchConfig
			->expects	( $this->any() )
			->method	( 'hasArticleMatch' )
			->will		( $this->returnValue( false ) )
		;
		$mockSearchConfig
			->expects	( $this->any() )
			->method	( 'getArticleMatch' )
			->will		( $this->returnValue( null ) )
		;
		$mockSearchEngine
			->expects	( $this->any() )
			->method	( 'getNearMatch' )
			->with		( $mockTerm )
			->will		( $this->returnValue( null ) )
		;

		$this->mockClass( 'Title',				$mockTitle );
		$this->mockClass( 'Article',			$mockArticle );
		$this->mockClass( 'ArticleMatch',		$mockArticleMatch );
		$this->mockClass( 'SearchEngine',		$mockSearchEngine );

		$this->assertNull( $wikiaSearch->getArticleMatch( $mockSearchConfig ),
		        			'A query term that does not produce a near title match should return null from WikiaSearch::getArticleMatch' );
	}

	/**
	 * @covers WikiaSearch::getArticleMatch
	 */
	public function testGetArticleMatchWithMatchFirstCall() {
		$solariumMock = $this->getMock( 'Solarium_Client') ;
		$wikiaSearch		= new WikiaSearch($solariumMock);
		$mockSearchConfig	= $this->getMock( 'WikiaSearchConfig', array( 'getArticleMatch', 'setArticleMatch', 'getNamespaces', 'getOriginalQuery' ) );
		$mockTitle			= $this->getMock( 'Title', array( 'getNamespace' ) );
		$mockArticle		= $this->getMock( 'Article', array(), array( $mockTitle ) );
		$mockArticleMatch	= $this->getMock( 'WikiaSearchArticleMatch', array(), array( $mockArticle ) );
		$mockSearchEngine	= $this->getMock( 'stdClass', array( 'getNearMatch' ) );
		$mockTerm			= 'foo';

		$mockSearchEngine
			->expects	( $this->any() )
			->method	( 'getNearMatch' )
			->with		( $mockTerm )
			->will		( $this->returnValue( $mockTitle ) )
		;
		$mockTitle
			->expects	( $this->any() )
			->method	( 'getNamespace' )
			->will		( $this->returnValue( 1 ) )
		;
		$mockSearchConfig
			->expects	( $this->any() )
			->method	( 'getOriginalQuery' )
			->will		( $this->returnValue( $mockTerm ) )
		;
		$mockSearchConfig
			->expects	( $this->any() )
			->method	( 'setArticleMatch' )
			->will		( $this->returnValue( $mockSearchConfig ) )
		;
		$mockSearchConfig
			->expects	( $this->any() )
			->method	( 'getNamespaces' )
			->will		( $this->returnValue( array( 1 ) ) )
		;
		$mockArticle
			->expects	( $this->any() )
			->method	( 'newFromTitle' )
			->will		( $this->returnValue( $mockArticle ) )
		;

		$this->mockClass( 'Title',				$mockTitle );
		$this->mockClass( 'Article',			$mockArticle );
		$this->mockClass( 'ArticleMatch',		$mockArticleMatch );
		$this->mockClass( 'SearchEngine',		$mockSearchEngine );
		$this->mockApp();
		F::setInstance( 'Article', $mockArticle );

		$this->assertInstanceOf( 'WikiaSearchArticleMatch', $wikiaSearch->getArticleMatch( $mockSearchConfig ),
		        				'A query term that is a near title match should result in the creation, storage, and return of an instance of WikiaArticleMatch' );

	}

	/**
	 * @covers WikiaSearch::getArticleMatch
	 */
	public function testGetArticleMatchWithMatchFirstCallMismatchedNamespace() {
		$solariumMock = $this->getMock( 'Solarium_Client') ;
	    $wikiaSearch		= new WikiaSearch($solariumMock);
	    $mockSearchConfig	= $this->getMock( 'WikiaSearchConfig', array( 'getOriginalQuery', 'getNamespaces', 'hasArticleMatch' ) );
		$mockTitle			= $this->getMock( 'Title', array( 'getNamespace' ) );
		$mockArticle		= $this->getMock( 'Article', array(), array( $mockTitle ) );
		$mockArticleMatch	= $this->getMock( 'WikiaSearchArticleMatch', array(), array( $mockArticle ) );
	    $mockSearchEngine	= $this->getMock( 'stdClass', array( 'getNearMatch' ) );
	    $mockTerm			= 'foo';

	    $mockSearchEngine
		    ->expects	( $this->any() )
		    ->method	( 'getNearMatch' )
		    ->with		( $mockTerm )
		    ->will		( $this->returnValue( $mockTitle ) )
	    ;
	    $mockTitle
		    ->expects	( $this->any() )
		    ->method	( 'getNamespace' )
		    ->will		( $this->returnValue( 1 ) )
	    ;
	    $mockSearchConfig
	    	->expects	( $this->any() )
	    	->method	( 'hasArticleMatch' )
	    	->will		( $this->returnValue( false ) )
    	;
	    $mockSearchConfig
		    ->expects	( $this->any() )
		    ->method	( 'getOriginalQuery' )
		    ->will		( $this->returnValue( $mockTerm ) )
	    ;
	    $mockSearchConfig
		    ->expects	( $this->any() )
		    ->method	( 'getNamespaces' )
		    ->will		( $this->returnValue( array( 0 ) ) )
	    ;

	    $this->mockClass( 'Title',				$mockTitle );
	    $this->mockClass( 'Article',			$mockArticle );
	    $this->mockClass( 'ArticleMatch',		$mockArticleMatch );
	    $this->mockClass( 'SearchEngine',		$mockSearchEngine );

	    $this->assertNull( $wikiaSearch->getArticleMatch( $mockSearchConfig ),
	            			'A query term that is a near title match should still return null if it is not in the searched-for namespaces' );

	}
}
