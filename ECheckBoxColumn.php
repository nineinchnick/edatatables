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

	protected function renderHeaderCellContent() {
		if (trim($this->headerTemplate)==='') {
			echo $this->grid->blankDisplay;
			return;
		}

		$item = '';
		if ($this->selectableRows===null && $this->grid->selectableRows>1) {
			$item = CHtml::checkBox($this->id.'_all', false, array(
				'class'=>'select-on-check-all dropdown-toggle',
				'data-toggle'=>'dropdown',
			));
		} else if ($this->selectableRows>1) {
			$item = CHtml::checkBox($this->id.'_all', false);
		} else {
			ob_start();
			parent::renderHeaderCellContent();
			$item = ob_get_clean();
		}

?>
		<div class="dropdown">
			<?php echo strtr($this->headerTemplate,array('{item}'=>$item)); ?>
			<ul class="dropdown-menu" aria-labelledby="<?php echo $this->id;?>_all" role="menu">
				<li>
					<?php echo CHtml::link('<i class="icon-ok"></i> '.Yii::t('EDataTables.edt', 'Select all'), '#', array(
						'id'	=> "{$this->id}-select-all",
						'class'	=> "dropdown-select-all",
					)); ?>
				</li>
				<li>
					<?php echo CHtml::link('<i class="icon-remove"></i> '.Yii::t('EDataTables.edt', 'Deselect all'), '#', array(
						'id'	=> "{$this->id}-deselect-all",
						'class'	=> 'dropdown-deselect-all',
					)); ?>
				</li>
				<li class="divider"></li>
				<li>
					<?php echo CHtml::link('<i class="icon-ok"></i> '.Yii::t('EDataTables.edt', 'Select on page'), '#', array(
						'id'	=> "{$this->id}-select-page",
						'class'	=> 'dropdown-select-page',
					)); ?>
				</li>
				<li>
					<?php echo CHtml::link('<i class="icon-remove"></i> '.Yii::t('EDataTables.edt', 'Deselect on page'), '#', array(
						'id'	=> "{$this->id}-deselect-page",
						'class'	=> 'dropdown-deselect-page',
					)); ?>
				</li>
			</ul>
		</div>
<?php
	}
}
