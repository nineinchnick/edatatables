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

	/**
	 * Renders the data cell content.
	 * This method evaluates {@link value} or {@link name} and renders the result.
	 * @param integer $row the row number (zero-based)
	 * @param mixed $data the data associated with the row
	 */
	protected function renderDataCellContent($row,$data,$return=false)
	{
		if($this->value!==null)
			$value=$this->evaluateExpression($this->value,array('data'=>$data,'row'=>$row));
		else if($this->name!==null)
			$value=CHtml::value($data,$this->name);
		if ($return)
			return $value===null ? $this->grid->nullDisplay : $this->grid->getFormatter()->format($value,$this->type);
		else
			echo $value===null ? $this->grid->nullDisplay : $this->grid->getFormatter()->format($value,$this->type);
	}

	public function getDataCellContent($row,$data) {
		return $this->renderDataCellContent($row,$data,true);
	}
}
