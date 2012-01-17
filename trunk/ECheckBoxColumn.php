<?php
/**
 * ECheckBoxColumn class file.
 *
 * @license http://www.yiiframework.com/license/
 */

Yii::import('zii.widgets.grid.CCheckBoxColumn');

/**
 *
 */
class ECheckBoxColumn extends CCheckBoxColumn {
	public $sortable = false;
	
	public function getDataCellContent($row,$data) {
		ob_start();
		$this->renderDataCellContent($row,$data);
		return ob_get_clean();
	}
}
