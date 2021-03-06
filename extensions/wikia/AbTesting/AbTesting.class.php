<?php
/**
 * @author Sean Colombo
 * @author Kyle Florence
 * @author Władysław Bodzek
 */

class AbTesting extends WikiaObject {

	const VARNISH_CACHE_TIME = 900; // 15 minutes - depends on Resource Loader settings for non-versioned requests
	const CACHE_TTL = 300; // 5 minutes (quite short to minimize impact of caching issues)
	const VERSION = 3;

	const FLAG_GA_TRACKING = 1;
	const FLAG_DW_TRACKING = 2;
	const FLAG_FORCED_GA_TRACKING_ON_LOAD = 4;
	const DEFAULT_FLAGS = 7;

	static public $flags = array(
		self::FLAG_GA_TRACKING => 'ga_tracking',
		self::FLAG_DW_TRACKING => 'dw_tracking',
		self::FLAG_FORCED_GA_TRACKING_ON_LOAD => 'forced_ga_tracking_on_load',
	);

	static protected $initialized = false;

	function __construct() {
		parent::__construct();

		if ( !self::$initialized ) {
			// Nirvana singleton, please use F::build
			F::setInstance( __CLASS__, $this );
			self::$initialized = true;
		}
	}

	// Keeping the response size (assets minification) and the number of external requests low (aggregation)
	public function onWikiaMobileAssetsPackages( Array &$jsHeadPackages, Array &$jsBodyPackages, Array &$scssPackages ) {
		array_unshift( $jsHeadPackages, 'abtesting' );
		return true;
	}

	public function onOasisSkinAssetGroupsBlocking( &$jsAssetGroups ) {
		array_unshift( $jsAssetGroups, 'abtesting' );
		return true;
	}

	public function onWikiaSkinTopModules( &$scriptModules, $skin ) {
		if ( $this->app->checkSkin( 'oasis', $skin ) ) {
			array_unshift( $scriptModules, 'wikia.ext.abtesting' );
		}
		return true;
	}

	protected function getMemcKey() {
		return $this->wf->sharedMemcKey('abtesting','config',self::VERSION);
	}

	protected function getFlagsInObject( $flags ) {
		$obj = new stdClass();
		foreach (self::$flags as $flag => $key) {
			$obj->$key = $flags & $flag ? 1 : 0;
		}
		return $obj;
	}

	public function getTimestampForUTCDate( $date ) {
		return strtotime($date);
	}

	protected function generateConfigScript( $data ) {
		$config = array();

		foreach ($data as $exp) {
			$expName = $this->normalizeName($exp['name']);

			$config[$expName] = array(
				'id' => intval($exp['id']),
				'name' => $expName,
				'versions' => array(),
			);
			$versions = &$config[$expName]['versions'];
			foreach ($exp['versions'] as $ver) {
				$version = array(
					'startTime' => $this->getTimestampForUTCDate($ver['start_time']),
					'endTime' => $this->getTimestampForUTCDate($ver['end_time']),
					'gaSlot' => $ver['ga_slot'],
					'flags' => $this->getFlagsInObject( $ver['flags'] ),
					'groups' => array(),
				);
				$groups = &$version['groups'];
				foreach ($ver['group_ranges'] as $grn) {
					$group = $exp['groups'][$grn['group_id']];
					$groupName = $this->normalizeName($group['name']);
					$groups[$groupName] = array(
						'id' => intval($group['id']),
						'name' => $groupName,
						'ranges' => $this->parseRanges($grn['ranges']),
					);
				}
				$versions[] = $version;
			}
		}

		$expConfig = array(
			'experiments' => $config
		);

		return sprintf("Wikia.AbTestConfig = %s;\n",json_encode($expConfig));
	}

	protected function getConfig() {
		$dataClass = new AbTestingData();
		$memcKey = $this->getMemcKey();
		$data = $this->wg->memc->get($memcKey);
		if ( empty( $data ) ) {
			// find last modification time
			$lastModified = $dataClass->getLastEffectiveChangeTime(self::VARNISH_CACHE_TIME);
			$lastModified = $lastModified
				? $this->getTimestampForUTCDate($lastModified) : null;

			// find time of next config change
			$nextModification = $dataClass->getNextEffectiveChangeTime(self::VARNISH_CACHE_TIME);
			$nextModification = $nextModification
				? $this->getTimestampForUTCDate($nextModification) : null;

			// calculate proper TTL
			$ttl = self::CACHE_TTL;
			if ( $nextModification ) {
				$ttl = max( 1, min( $ttl, $nextModification - time() + 1 ) );
			}

			$data = array(
				'modifiedTime' => $lastModified,
				'script' => $this->generateConfigScript($dataClass->getCurrent()),
			);
			$this->wg->memc->set($memcKey,$data,$ttl);
		}
		return $data;
	}

	public function getConfigScript() {
		$data = $this->getConfig();
		return $data['script'];
	}

	public function getConfigModifiedTime() {
		$data = $this->getConfig();
		return $data['modifiedTime'] ? $data['modifiedTime'] : 1;
	}

	public function invalidateCache() {
		$this->wg->memc->delete($this->getMemcKey());
	}

	/**
	 * Given a string of ranges, returns an array of range hashes. For example:
	 * "0-10,15-25,40" => array(
	 *     array( "min" => 0, "max" => 10 ),
	 *     array( "min" => 15, "max" => 25 ),
	 *     array( "min" => 40, "max" => 40 )
	 * )
	 */
	public function parseRanges( $ranges, $failOnError = false ) {
		$rangesArray = array();

		if ( strlen( $ranges ) ) {
			$min = $max = 0;
			foreach ( explode( ',', $ranges ) as $i => $range ) {
				$hasError = false;
				if ( preg_match( '/^(\d+)-(\d+)$/', $range, $matches ) ) {
					$min = intval( $matches[1] );
					$max = intval( $matches[2] );
				} elseif ( preg_match( '/^(\d+)$/', $range, $matches ) ) {
					$min = $max = intval( $matches[1] );
				} else {
					$hasError = true;
				}
				if ( $min < 0 || $min > 99 ) $hasError = true;
				if ( $max < 0 || $max > 99 ) $hasError = true;
				if ( $min > $max ) $hasError = true;

				if ( $hasError ) {
					if ( $failOnError ) {
						return false;
					}
					break;
				}

				$rangesArray[] = array(
					'min' => $min,
					'max' => $max
				);
			}
		}

		return $rangesArray;
	}

	/**
	 * Normalizes the names of experiments and treatment groups into an
	 * uppercased string with spaces replaced by underscores.
	 */
	public function normalizeName( $name ) {
		$name = str_replace( ' ', '_', $name );
		$name = strtoupper( preg_replace( '/[^a-z0-9_]/i', '', $name ) );

		return $name;
	}

	public function getFlagState( $flags, $flag ) {
		return ($flags & $flag) > 0;
	}

}
