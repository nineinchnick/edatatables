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
	
	/**
	 * @var boolean
	 */
	public $sortable = true;

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
		/*

    <div class="btn-group">
		<a class="btn btn-mini dropdown-toggle" data-toggle="dropdown" href="#">
			<span class="caret"></span>
		</a>
		<ul class="dropdown-menu">
			<li><a href="#"><i class="icon-remove"></i> hide</a></li>
			<li class="dropdown-submenu"><a href="#"><i class="icon-ok"></i> grouping column</a>
				<ul class="dropdown-menu">
					<li><a href="#"><i class="icon-ok"></i> no round</a></li>
				</ul>
			</li>
			<li class="dropdown-submenu"><a href="#"><i class=""></i> data column</a>
				<ul class="dropdown-menu">
					<li><a href="#">sum</a></li>
					<li><a href="#">avg</a></li>
					<li><a href="#">count</a></li>
				</ul>
			</li>
		</ul>
	</div>*/
		$this->renderHeaderCellContent();
		echo "</th>";
	}
	
	public function getDataCellContent($row,$data) {
		ob_start();
		$this->renderDataCellContent($row,$data);
		return ob_get_clean();
	}
}
