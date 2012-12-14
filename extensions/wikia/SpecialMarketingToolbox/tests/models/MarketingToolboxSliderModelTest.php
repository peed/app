<?

class MarketingToolboxSliderModelTest extends PHPUnit_Framework_TestCase {


	/**
	 * @group Kosher
	 */
	public function testGetSlidesCount() {
		$model = new MarketingToolboxSliderModel();

		$this->assertEquals(5, $model->getSlidesCount());
	}
}