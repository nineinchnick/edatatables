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
class EDataColumn extends CDataColumn {
	/**
	 * @var boolean If true, column header will contain a dropdown menu.
	 * Its contents should be rendered using JavaScript.
	 */
	public $dropdown;

	/**
	 * Renders the header cell.
	 */
	public function renderHeaderCell()
	{
		$this->headerHtmlOptions['id']=$this->id;
		echo CHtml::openTag('th',$this->headerHtmlOptions);
		if ($this->dropdown) {
			echo '<div class="btn-group">
				<a class="btn btn-mini dropdown-toggle" data-toggle="dropdown" href="#"><span class="caret"></span></a>
				<ul class="dropdown-menu"></ul>
			</div> ';
		}
		$this->renderHeaderCellContent();
		echo "</th>";
	}

	/**
	 * Renders the header cell content.
	 * This method will render a link that can trigger the sorting if the column is sortable.
	 */
	protected function renderHeaderCellContent() {
		if($this->name!==null && $this->header===null)
		{
			if($this->grid->dataProvider instanceof CActiveDataProvider)
				echo CHtml::encode($this->grid->dataProvider->model->getAttributeLabel($this->name));
			else
				echo CHtml::encode($this->name);
		}
		else
			echo trim($this->header)!=='' ? $this->header : $this->grid->blankDisplay;
			//parent::renderHeaderCellContent();
	}

	protected function renderDataCellContent($row,$data) {
		if (!$this->visible)
			return $this->grid->nullDisplay;
		else
			return parent::renderDataCellContent($row,$data);
	}

	public function getDataCellContent($row) {
        if (method_exists(get_parent_class($this), 'getDataCellContent'))
            return parent::getDataCellContent($row);
		ob_start();
		$this->renderDataCellContent($row,$this->grid->dataProvider->data[$row]);
		return ob_get_clean();
	}
}
