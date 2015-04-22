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
abstract class EGridColumn extends CGridColumn
{
    /**
     * @var bool
     */
    public $sortable = true;

    /**
     * Renders the header cell.
     */
    public function renderHeaderCell()
    {
        $this->headerHtmlOptions['id'] = $this->id;
        echo CHtml::openTag('th', $this->headerHtmlOptions);
        $this->renderHeaderCellContent();
        echo '</th>';
    }

    public function getDataCellContent($row)
    {
        if (method_exists(get_parent_class($this), 'getDataCellContent')) {
            return parent::getDataCellContent($row);
        }
        ob_start();
        $this->renderDataCellContent($row, $this->grid->dataProvider->data[$row]);

        return ob_get_clean();
    }
}
