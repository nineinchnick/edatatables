<?php
/**
 * ECellContentBehaviour class file.
 *
 * @license http://www.yiiframework.com/license/
 */

class ECellContentBehavior extends CBehavior
{
	public function getDataCellContent($row) {
		ob_start();
		$this->owner->renderDataCellContent($row,$this->owner->grid->dataProvider->data[$row]);
		return ob_get_clean();
	}
}
