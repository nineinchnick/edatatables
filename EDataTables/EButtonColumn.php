<?php
/**
 * EButtonColumn class file.
 *
 * @license http://www.yiiframework.com/license/
 */

Yii::import('zii.widgets.grid.CButtonColumn');

/**
 *
 */
class EButtonColumn extends CButtonColumn {
	
	public $sortable = false;
	/**
	 * @var string the label for the history button. Defaults to "history".
	 * Note that the label will not be HTML-encoded when rendering.
	 */
	public $historyButtonLabel;
	/**
	 * @var string the image URL for the history button. If not set, an integrated image will be used.
	 * You may set this property to be false to render a text link instead.
	 */
	public $historyButtonImageUrl=false;
	/**
	 * @var string a PHP expression that is evaluated for every history button and whose result is used
	 * as the URL for the history button. In this expression, the variable
	 * <code>$row</code> the row number (zero-based); <code>$data</code> the data model for the row;
	 * and <code>$this</code> the column object.
	 */
	public $historyButtonUrl='Yii::app()->controller->createUrl("history",array("id"=>$data->primaryKey))';
	/**
	 * @var array the HTML options for the history button tag.
	 */
	public $historyButtonOptions=array('class'=>'history');

	public $viewButtonIcon = 'eye-open';
	public $updateButtonIcon = 'pencil';
	public $deleteButtonIcon = 'trash';
	public $historyButtonIcon = 'calendar';

	/**
	 * Initializes the default buttons (view, update and delete).
	 */
	protected function initDefaultButtons()
	{
		if (!$this->grid->bootstrap) {
			$this->viewButtonIcon = 'search';
		}

		if($this->deleteConfirmation===null)
			$this->deleteConfirmation=Yii::t('zii','Are you sure you want to delete this item?');

		if(!isset($this->buttons['delete']['click']))
		{
			if(is_string($this->deleteConfirmation))
				$confirmation="if(!confirm(".CJavaScript::encode($this->deleteConfirmation).")) return false;";
			else
				$confirmation='';

			if(Yii::app()->request->enableCsrfValidation)
			{
				$csrfTokenName = Yii::app()->request->csrfTokenName;
				$csrfToken = Yii::app()->request->csrfToken;
				$csrf = "\n\t\tdata:{ '$csrfTokenName':'$csrfToken' },";
			}
			else
				$csrf = '';

			if($this->afterDelete===null)
				$this->afterDelete='function(){}';

			$this->buttons['delete']['click']=<<<JavaScript
function() {
	$confirmation
	jQuery('#{$this->grid->id}').eDataTables('refresh');
	return false;
}
JavaScript;
		}

		parent::initDefaultButtons();

		// history button
		if($this->historyButtonLabel===null)
			$this->historyButtonLabel=Yii::t('EDataTables.edt','History');
		if($this->historyButtonImageUrl===null)
			$this->historyButtonImageUrl=$this->grid->baseScriptUrl.'/history.png';
		$button=array(
			'label'=>$this->historyButtonLabel,
			'url'=>$this->historyButtonUrl,
			'imageUrl'=>$this->historyButtonImageUrl,
			'options'=>$this->historyButtonOptions,
		);
		if(isset($this->buttons['history']))
			$this->buttons['history']=array_merge($button,$this->buttons['history']);
		else
			$this->buttons['history']=$button;

        if ($this->viewButtonIcon !== false && !isset($this->buttons['view']['icon']))
            $this->buttons['view']['icon'] = $this->viewButtonIcon;
        if ($this->updateButtonIcon !== false && !isset($this->buttons['update']['icon']))
            $this->buttons['update']['icon'] = $this->updateButtonIcon;
        if ($this->deleteButtonIcon !== false && !isset($this->buttons['delete']['icon']))
            $this->buttons['delete']['icon'] = $this->deleteButtonIcon;
        if ($this->historyButtonIcon !== false && !isset($this->buttons['history']['icon']))
            $this->buttons['history']['icon'] = $this->historyButtonIcon;
	}

	/**
	 * Renders a link button.
	 * @param string $id the ID of the button
	 * @param array $button the button configuration which may contain 'label', 'url', 'imageUrl' and 'options' elements.
	 * @param integer $row the row number (zero-based)
	 * @param mixed $data the data object associated with the row
	 */
	protected function renderButton($id, $button, $row, $data)
	{
		if (isset($button['visible']) && !$this->evaluateExpression($button['visible'], array('row'=>$row, 'data'=>$data)))
			return;

		$label = isset($button['label']) ? $button['label'] : $id;
		$url = isset($button['url']) ? $this->evaluateExpression($button['url'], array('data'=>$data, 'row'=>$row)) : '#';
		$options = isset($button['options']) ? $button['options'] : array();

		if (!isset($options['title']))
			$options['title'] = $label;

		if ($this->grid->bootstrap) {
			if (!isset($options['rel']))
				$options['rel'] = 'tooltip';
		}

		if (isset($button['icon']))
		{
			if ($this->grid->bootstrap) {
				if (strpos($button['icon'], 'icon') === false)
					$button['icon'] = 'icon-'.implode(' icon-', explode(' ', $button['icon']));

				echo CHtml::link('<i class="'.$button['icon'].'"></i>', $url, $options);
			} else {
				if (strpos($button['icon'], 'icon') === false)
					$button['icon'] = 'ui-icon-'.implode(' ui-icon-', explode(' ', $button['icon']));
				if (!isset($options['class']))
					$options['class']='';
				$options['class'] .=' view left ui-button ui-widget ui-state-default ui-corner-all ui-button-text-icon-primary ui-button-icon-primary ui-icon '.$button['icon']; 
				if (isset($button['imageUrl']) && is_string($button['imageUrl']))
					echo CHtml::link(CHtml::image($button['imageUrl'], $label), $url, $options);
				else
					echo CHtml::link($label, $url, $options);
			}
		} else if (isset($button['imageUrl']) && is_string($button['imageUrl'])) {
			echo CHtml::link(CHtml::image($button['imageUrl'], $label), $url, $options);
		} else {
			echo CHtml::link($label, $url, $options);
		}
	}
	
	public function getDataCellContent($row,$data) {
		ob_start();
		$this->renderDataCellContent($row,$data);
		return ob_get_clean();
	}
}
