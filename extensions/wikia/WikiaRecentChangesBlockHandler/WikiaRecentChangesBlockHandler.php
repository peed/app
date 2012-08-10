<?php
class WikiaRecentChangesBlockHandler extends Service {

	/**
	 * @brief Adjusting title of a block group on RecentChanges page
	 *
	 * @param ChangeList $oChangeList
	 * @param string $r
	 * @param array $oRCCacheEntryArray an array of RCCacheEntry instances
	 *
	 * @author Andrzej 'nAndy' Łukaszewski
	 */
	public function onChangesListHeaderBlockGroup($oChangeList, &$r, &$oRCCacheEntryArray) {
		wfProfileIn(__METHOD__);

		$headerTitle = null;
		$oRCCacheEntry = null;

		if ( !empty($oRCCacheEntryArray) ) {
			$oRCCacheEntry = $oRCCacheEntryArray[0];

			if( !is_null($oRCCacheEntry) ) {
				$oTitle = $oRCCacheEntry->getTitle();

				if( $oTitle instanceof Title ) {
					$changeRecentChangesHeader = false;

					wfRunHooks('WikiaRecentChangesBlockHandlerChangeHeaderBlockGroup', array($oChangeList, $r, $oRCCacheEntryArray, &$changeRecentChangesHeader, $oTitle, &$headerTitle));

					if( $changeRecentChangesHeader ) {
						$app = F::app();
						$cnt = count($oRCCacheEntryArray);
						$cntChanges = wfMsgExt( 'nchanges', array( 'parsemag', 'escape' ), $app->wg->Lang->formatNum( $cnt ) );
						$timestamp = null;

						$userlinks = array();
						foreach( $oRCCacheEntryArray as $id => $oRCCacheEntry ) {
							$u = $oRCCacheEntry->userlink;
							if( !isset($userlinks[$u]) ) {
								$userlinks[$u] = 0;
							}
							$userlinks[$u]++;
							if( is_null($timestamp) ) $timestamp = $oRCCacheEntry->timestamp;
						}

						$users = array();
						foreach( $userlinks as $userlink => $count) {
							$text = $userlink;
							$text .= $app->wg->ContLang->getDirMark();
							if( $count > 1 ) {
								$text .= ' (' . $app->wg->Lang->formatNum($count) . '×)';
							}
							array_push($users, $text);
						}

						$vars = array(
							'cntChanges'	=> $cntChanges,
							'hdrtitle'		=> $headerTitle,
							'inx'			=> $oChangeList->rcCacheIndex,
							'users'			=> $users,
							'wgStylePath'	=> $app->wg->StylePath,
							'title'			=> $oTitle,
							'timestamp'		=> $timestamp,
						);

						$oTmpl = new EasyTemplate( dirname(__FILE__) . "/templates/" );
						$oTmpl->set_vars($vars);

						$r = $oTmpl->execute('RecentChangesHeaderBlock');
					}
				}
			}
		}

		wfProfileOut(__METHOD__);
		return true;
	}
}