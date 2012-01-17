<?php
/**
 * CButtonColumn class file.
 *
 * @license http://www.yiiframework.com/license/
 */

Yii::import('zii.widgets.grid.CButtonColumn');

/**
 *
 */
class EButtonColumn extends CButtonColumn {
	
	public $sortable = false;
	
	public $updateButtonUrl='Yii::app()->controller->createUrl("edit",array("id"=>$data->primaryKey))';
	public function getDataCellContent($row,$data) {
		ob_start();
		$this->renderDataCellContent($row,$data);
		return ob_get_clean();
	}
}
