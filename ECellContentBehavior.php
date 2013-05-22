<?php
/**
 * ECellContentBehaviour class file.
 *
 * @license http://www.yiiframework.com/license/
 */

class ECellContentBehavior extends CBehavior
{
	public function getDataCellContent($row,$data) {
		ob_start();
		$this->owner->renderDataCellContent($row,$data);
		return ob_get_clean();
	}
}
