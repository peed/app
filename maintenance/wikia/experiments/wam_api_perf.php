<?php

require_once( dirname(__FILE__) . '/../../commandLine.inc' );

$app = F::app();
$starttime = microtime(true);

//$dataMartService = new DataMartService();
//echo $dataMartService->getCurrentWamScoreForWiki(2233);
//die;

$wamIndex = $app->sendRequest('WAMApiController', 'getWAMIndex', array(
	'wam_day' => 1352505600,
	'wam_previous_day' => 1352505600,
	'fetch_admins' => true,
	'fetch_wiki_images' => true,
	'sort_direction' => 'DESC'
))->getData();

$endtime = microtime(true);

echo "\n\nElapsed time: " . ($endtime - $starttime) . "\n";
var_dump(count($wamIndex['wam_index']));


/*
for ($i = 0; $i < 20; $i++) {
	$starttime = microtime(true);
	$wamIndex = $app->sendRequest('WAMApiController', 'getWAMIndex', array(
		'wam_day' => 1352505600,
		'wam_previous_day' => 1352505600,
		//'fetch_admins' => true,
		'fetch_wiki_images' => true,
		'sort_direction' => 'DESC'
	))->getData();

	$endtime = microtime(true);
	$averages [] = $endtime - $starttime;
}
echo "\n\nElapsed time: " . array_sum($averages) . ', average: ', array_sum($averages)/count($averages) . "\n";
var_dump(count($wamIndex['wam_index']));

*/