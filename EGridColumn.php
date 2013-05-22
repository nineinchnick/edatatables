<?php
/**
 * EGridColumn class file.
 *
 * @license http://www.yiiframework.com/license/
 */

Yii::import('zii.widgets.grid.CGridColumn');

/**
 *
 */
abstract class EGridColumn extends CGridColumn {
	
	public $sortable = true;
	
	public function getDataCellContent($row,$data) {
		ob_start();
		$this->renderDataCellContent($row,$data);
		return ob_get_clean();
	}
}
