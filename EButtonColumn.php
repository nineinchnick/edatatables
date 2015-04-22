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
class EButtonColumn extends CButtonColumn
{
    public $sortable = false;
    /**
     * @var string the label for the history button. Defaults to "history".
     *             Note that the label will not be HTML-encoded when rendering.
     */
    public $historyButtonLabel;
    /**
     * @var string the image URL for the history button. If not set, an integrated image will be used.
     *             You may set this property to be false to render a text link instead.
     */
    public $historyButtonImageUrl = false;
    /**
     * @var string a PHP expression that is evaluated for every history button and whose result is used
     *             as the URL for the history button. In this expression, the variable
     *             <code>$row</code> the row number (zero-based); <code>$data</code> the data model for the row;
     *             and <code>$this</code> the column object.
     */
    public $historyButtonUrl = 'Yii::app()->controller->createUrl("history",array("id"=>$data->primaryKey))';
    /**
     * @var array the HTML options for the history button tag.
     */
    public $historyButtonOptions = array('class' => 'history');

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

    /**
     * Initializes the default buttons (view, update and delete).
     */
    protected function initDefaultButtons()
    {
        if ($this->deleteConfirmation === null) {
            $this->deleteConfirmation = Yii::t('zii', 'Are you sure you want to delete this item?');
        }

        if (!isset($this->buttons['delete']['click'])) {
            if (is_string($this->deleteConfirmation)) {
                $confirmation = 'if(!confirm('.CJavaScript::encode($this->deleteConfirmation).')) return false;';
            } else {
                $confirmation = '';
            }

            if (Yii::app()->request->enableCsrfValidation) {
                $csrfTokenName = Yii::app()->request->csrfTokenName;
                $csrfToken = Yii::app()->request->csrfToken;
                $csrf = "\n\t\tdata:{ '$csrfTokenName':'$csrfToken' },";
            } else {
                $csrf = '';
            }

            if ($this->afterDelete === null) {
                $this->afterDelete = 'function(){}';
            }

            $this->buttons['delete']['click'] = <<<JavaScript
function() {
	$confirmation
	var th = this,
		afterDelete = $this->afterDelete;
	$.getJSON($(this).attr('href'), function(data){
		jQuery('#{$this->grid->id}').eDataTables('refresh');
		afterDelete(th, true, data);
	});
	return false;
}
JavaScript;
        }

        parent::initDefaultButtons();

        // history button
        if ($this->historyButtonLabel === null) {
            $this->historyButtonLabel = Yii::t('EDataTables.edt', 'History');
        }
        if ($this->historyButtonImageUrl === null) {
            $this->historyButtonImageUrl = $this->grid->baseScriptUrl.'/history.png';
        }
        $button = array(
            'label' => $this->historyButtonLabel,
            'url' => $this->historyButtonUrl,
            'imageUrl' => $this->historyButtonImageUrl,
            'options' => $this->historyButtonOptions,
        );
        $this->buttons['history'] = !isset($this->buttons['history']) ? $button : array_merge($button, $this->buttons['history']);
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
