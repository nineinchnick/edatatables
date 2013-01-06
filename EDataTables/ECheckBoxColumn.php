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

	protected function renderHeaderCellContent()
	{
		if(trim($this->headerTemplate)==='')
		{
			echo $this->grid->blankDisplay;
			return;
		}

		$item = '';
		if($this->selectableRows===null && $this->grid->selectableRows>1)
			$item = CHtml::checkBox($this->id.'_all',false,
					array('class'=>'select-on-check-all dropdown-toggle',
						"data-toggle"=>"dropdown",));
		else if($this->selectableRows>1)
			$item = CHtml::checkBox($this->id.'_all',false);
		else
		{
			ob_start();
			parent::renderHeaderCellContent();
			$item = ob_get_clean();
		}

		echo "<div class=\"dropdown\">";
		echo strtr($this->headerTemplate,array(
			'{item}'=>$item,
		));
		echo "<ul class=\"dropdown-menu\" aria-labelledby=\"{$this->id}_all\" role=\"menu\"><li>";
		echo CHtml::link('<i class="icon-ok"></i> Zaznacz wszystkie', '#',
				array(
					'id'	=> "{$this->id}-select-all",
					'class'	=> "dropdown-select-all"));
		echo "</li><li>";
		echo CHtml::link('<i class="icon-remove"></i> Odznacz wszystkie', '#',
				array(
					'id'	=> "{$this->id}-deselect-all",
					'class'	=> 'dropdown-deselect-all'));
		echo "</li><li class=\"divider\"></li><li>";
		echo CHtml::link('<i class="icon-ok"></i> Zaznacz na stronie', '#',
				array(
					'id'	=> "{$this->id}-select-page",
					'class'	=> 'dropdown-select-page'));
		echo "</li><li>";
		echo CHtml::link('<i class="icon-remove"></i> Odznacz na stronie', '#',
				array(
					'id'	=> "{$this->id}-deselect-page",
					'class'	=> 'dropdown-deselect-page'));
		echo "</li></ul></div>";
		
	}
}
