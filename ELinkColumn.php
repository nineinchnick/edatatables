<?php
/**
 * EDataColumn class file.
 *
 * @license http://www.yiiframework.com/license/
 */

Yii::import('zii.widgets.grid.CGridColumn');

/**
 *
 */
class ELinkColumn extends CLinkColumn
{
	public function getDataCellContent($row,$data)
	{
        ob_start();
        $this->renderDataCellContent($row,$data);
        return ob_get_clean();
    }
}
