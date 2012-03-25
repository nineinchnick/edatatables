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

	public $viewButtonIcon = 'eye-open';
	public $updateButtonIcon = 'pencil';
	public $deleteButtonIcon = 'trash';

	/**
	 * Initializes the default buttons (view, update and delete).
	 */
	protected function initDefaultButtons()
	{
		if (!$this->grid->bootstrap) {
			$this->viewButtonIcon = 'search';
		}

		parent::initDefaultButtons();

        if ($this->viewButtonIcon !== false && !isset($this->buttons['view']['icon']))
            $this->buttons['view']['icon'] = $this->viewButtonIcon;
        if ($this->updateButtonIcon !== false && !isset($this->buttons['update']['icon']))
            $this->buttons['update']['icon'] = $this->updateButtonIcon;
        if ($this->deleteButtonIcon !== false && !isset($this->buttons['delete']['icon']))
            $this->buttons['delete']['icon'] = $this->deleteButtonIcon;
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
				$options['class'] .=' view left ui-button ui-widget ui-state-default ui-corner-all ui-button-text-icon-primary ui-button-icon-primary ui-icon '.$button['icon']; 
				if (isset($button['imageUrl']) && is_string($button['imageUrl']))
					echo CHtml::link(CHtml::image($button['imageUrl'], $label), $url, $options);
				else
					echo CHtml::link($label, $url, $options);
			}
		}
		else if (isset($button['imageUrl']) && is_string($button['imageUrl']))
			echo CHtml::link(CHtml::image($button['imageUrl'], $label), $url, $options);
		else
			echo CHtml::link($label, $url, $options);
	}
	
	public function getDataCellContent($row,$data) {
		ob_start();
		$this->renderDataCellContent($row,$data);
		return ob_get_clean();
	}
}
