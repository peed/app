<?php
/**
 * Parser class for InWikiGame parser tag
 * @author Andrzej 'nAndy' Łukaszewski
 * @author Marcin Maciejewski
 * @author Sebastian Marzjan
 */
class InWikiGameParserTag {
	private static $instanceCounter = 1;
	
	/**
	 * @param Parser $parser MW Parser instance
	 * @return bool
	 */
	public function onParserFirstCallInit(Parser $parser) {
		$parser->setHook('inwikigame', array($this, 'renderTag'));
		return true;
	}

	/**
	 * @param $input
	 * @param $params
	 * @return string
	 */
	public function renderTag($input, $params) {
		$app = F::app();

		$html = F::app()->renderView('InWikiGame', 'Index', array('inWikiGameId' => self::$instanceCounter++));

		if (!empty($app->wg->RTEParserEnabled)) {
			return $html;
		} else {
			return '<nowiki>' . trim($html) . '</nowiki>';
		}
	}
}