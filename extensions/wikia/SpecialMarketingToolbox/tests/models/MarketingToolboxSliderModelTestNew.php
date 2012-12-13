<?

class App {
	public $wf;
	public $wg;
}

class MarketingToolboxSliderModelTest extends PHPUnit_Framework_TestCase {


	/**
	 * @group SaneTest
	 */
	public function testGetSlidesCount() {
		$model = new MarketingToolboxSliderModel();

		$this->assertEquals(5, $model->getSlidesCount());
	}
}