<?= $app->renderView(
	'MarketingToolbox',
	'Header',
	$headerData
) ?>

<section class="MarketingToolboxMain WikiaGrid">
	<div class="grid-2">
		<?= $app->renderView('LeftMenu',
			'Index',
			array('menuItems' => $leftMenuItems)
		) ?>
	</div>
	<div class="grid-4 alpha">
		<? if (!empty($flashMessage)): ?>
			<p class="success"><?=$flashMessage ?></p>
		<? endif ?>

		<? if (!empty($errorMessage)): ?>
			<p class="error"><?=$errorMessage ?></p>
		<? endif ?>

		<form id="marketing-toolbox-form" data-module-name="<?= $moduleName ?>" method="post" name="upload-tool" class="WikiaForm" enctype="multipart/form-data">
			<?=$moduleContent?>

			<div class="submits">
				<input type="submit" value="<?= wfMsg('marketing-toolbox-edithub-save-button'); ?>" />
				<input id="marketing-toolbox-clearall" name="clearall" class="secondary" type="button" value="<?= $wf->msg('marketing-toolbox-edithub-clearall-button'); ?>" />
			</div>
		</form>
	</div>
</section>

<?= $app->renderView(
	'MarketingToolbox',
	'Footer'
) ?>