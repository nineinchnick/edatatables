<?php
/**
 * EEditableColumn class file.
 *
 * @license http://www.yiiframework.com/license/
 */

/**
 * @todo handle null values
 */
class EEditableColumn extends EDataColumn {
	public $class = 'editable';

	public $sortable = false;
	
	protected function renderDataCellContent($row,$data)
	{
		if($this->value!==null)
			$value=$this->evaluateExpression($this->value,array('data'=>$data,'row'=>$row));
		else if($this->name!==null)
			$value=CHtml::value($data,$this->name);
		// not using $this->grid->nullDisplay here because it's a &nbsp; by default
		$value = $value===null ? '' : $this->grid->getFormatter()->format($value,$this->type);
		//! @todo check type, set different controls or attributes for input tag
		//! @todo use a different formatter? ex. an image type
		/**
		 * @todo in views we use pre-generated controls, the dev can choose
		 *       from different ones - here we generate them
		 *       on the fly - is this the best way?
		 *       We COULD provide separate view for every attribute, but
		 *       wouldn't it be an overkill?
		 */
		$id = str_replace('.','_',$this->name).'_row'.$row;
		switch($this->type) {
			default:
			case 'text':
				echo '<input class="'.$this->class.'" id="'.$id.'" type="text" value="'.$value.'"/>';
				break;
			case 'password':
				echo '<input class="'.$this->class.'" id="'.$id.'" type="text" value=""/>';
				break;
			case 'integer':
			case 'double':
			case 'dec2':
				echo '<input class="'.$this->class.'" id="'.$id.'" type="text" value="'.$value.'" size="6" maxlength="9"/>';
				break;
			case 'flags':
			case 'set':
			case 'object':
			case 'array':
				throw new Exception('Editable column not implemented for type '.$this->type.' ('.var_export($value,true).')');
			case 'boolean':
				echo '<input class="'.$this->class.'" id="'.$id.'" type="checkbox" value="1" '.($value?' checked="checked"':'').'/>';
				//echo '<input type="hidden" value="1"/>';
				break;
			case 'date':
			case 'time':
			case 'datetime':
				//echo '<input class="'.$this->class.'" id="'.$id.'" type="text" value="'.$value.'" maxlength="17"/>';
				echo '<input class="'.$this->class.' '.$this->grid->id.'_datepickers" id="'.$id.'" type="text" value="'.$value.'" maxlength="17"/>';
				Yii::app()->getClientScript()->registerScript(__CLASS__.'#'.$this->grid->id.'_datepickers', '$(\'.'.$this->grid->id.'_datepickers\').datepicker(jQuery.extend(jQuery.datepicker.regional[\'pl\'], {constrainInput:false,changeMonth:true,changeYear:true,dateFormat:\'yy-mm-dd\', yearRange: \'c-60:c+15\'}));');
				/*
				$this->grid->getOwner()->widget('zii.widgets.jui.CJuiDatePicker', array(
					'name'	=> 'publishDate',
					'id'	=> $id,
					'value'	=> $value,
					'options'=>array(
						'changeMonth'=>true,
						'changeYear'=>true,
					),
					'htmlOptions'=>array(
						'class'=>$this->class,
					)
				));
				 */
				break;
			case 'image':
				throw new Exception('Editable column not implemented for type '.$this->type.' ('.var_export($value,true).')');
				break;
		}
	}
}
