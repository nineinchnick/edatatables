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
	/**
	 * @var boolean
	 */
	public $sortable = false;

	/**
	 * @var mixed boolean or string, which is evaluated as an expression which result must be boolean
	 */
	public $enabled = true;

	/**
	 * @var string If not null, it's evaluated as an expression and result is used instead of type property.
	 */
	public $dynamicType;

	/**
	 * Renders a data cell.
	 * @param integer $row the row number (zero-based)
	 */
	public function renderDataCell($row)
	{
		$data=$this->grid->dataProvider->data[$row];
		$options=$this->htmlOptions;
		if($this->cssClassExpression!==null)
		{
			$class=$this->evaluateExpression($this->cssClassExpression,array('row'=>$row,'data'=>$data));
			if(isset($options['class']))
				$options['class'].=' '.$class;
			else
				$options['class']=$class;
		}
		if (isset($options['onclick'])) {
			unset($options['onclick']);
		}
		echo CHtml::openTag('td',$options);
		$this->renderDataCellContent($row,$data);
		echo '</td>';
	}
	
	protected function renderDataCellContent($row,$data)
	{
		if (is_string($this->visible))
			$visible=$this->evaluateExpression($this->visible,array('data'=>$data,'row'=>$row));
		else
			$visible=$this->visible;
		if (!$visible)
			return;
		if (is_string($this->enabled))
			$enabled=$this->evaluateExpression($this->enabled,array('data'=>$data,'row'=>$row));
		else
			$enabled=$this->enabled;

		if (is_string($this->dynamicType))
			$this->type=$this->evaluateExpression($this->dynamicType,array('data'=>$data,'row'=>$row));

		if (substr($this->type,0,8) === 'readonly') {
			$this->type = lcfirst(substr($this->type,8));
			echo parent::renderDataCellContent($row,$data);
			return;
		}

		if($this->value!==null)
			$value=$this->evaluateExpression($this->value,array('data'=>$data,'row'=>$row));
		else if($this->name!==null)
			$value=CHtml::value($data,$this->name);

		// not using $this->grid->nullDisplay here because it's a &nbsp; by default
		$unformattedValue = $value;
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

		$options = $this->htmlOptions;

		if (!$enabled)
			$options['disabled']='disabled';
		$options['class'] = empty($options['class']) ? 'editable' : $options['class'].' editable';

		switch($this->type) {
			default:
			case 'readonly':
				echo parent::renderDataCellContent($row,$data);
				break;
			case 'text':
				echo CHtml::tag('input', array_merge(array('type'=>'text','id'=>$id,'value'=>$value), $options));
				break;
			case 'password':
				echo CHtml::tag('input', array_merge(array('type'=>'password','id'=>$id), $options));
				break;
			case 'integer':
			case 'double':
			case 'dec2':
			case 'dec3':
			case 'dec5':
				if ($this->grid->bootstrap) {
					$options['class'].= ' span1';
				}
				echo CHtml::tag('input', array_merge(array('type'=>'text','id'=>$id,'value'=>$value,'size'=>6,'maxlength'=>9), $options));
				break;
			case 'flags':
			case 'set':
			case 'object':
				$relations = $data->relations();
				if (($pos=strpos($this->name,'.'))!==false) {
					$pivotRelationName = substr($this->name,0,$pos); // withShops
					$pivotRelation = $relations[$pivotRelationName];
					$model = $data->$pivotRelationName;
					$staticModel = NetActiveRecord::model($pivotRelation[1]);
					// change id and name properties to fk column instead of relation name
					$id_parts = explode('.', $this->name);
					$relationName = array_pop($id_parts);
					$pivotRelations = $staticModel->relations();
					$relation = $pivotRelations[$relationName];
					$id = implode('_',$id_parts).'_'.$relation[2].'_row'.$row;
					$name = $relation[2];
				} else {
					$relation = $relations[$this->name];
					$model = $data;
					$name = $this->name;
				}
				$options['data-placeholder'] = Yii::t('EDataTables.edt','Choose').' '.NetActiveRecord::model($relation[1])->label(2).'...';
				$controller = lcfirst($relation[1]);
				echo $this->grid->owner->widget('ext.select2.ESelect2', array(
					'options' => Select2Helper::filterDefaults(CHtml::normalizeUrl('/'.$controller.'/autocomplete'), '', true, array('width'=>'15em')),
					'htmlOptions' => array_merge(array(
						'id'=>$id,
						'name'=>$name,
					), $options),
					'value'=> $model !== null ?
						(isset($relationName) ?
							($model->{$relationName} !== null ? $model->{$relationName}->getPrimaryKey() : null)
							: ($model->{$this->name} !== null ? $model->{$this->name}->getPrimaryKey() : null))
						: null,
				),true);
				break;
			case 'array':
				throw new Exception('Editable column not implemented for type '.$this->type.' ('.var_export($value,true).')');
				break;
			case 'boolean':
				echo CHtml::tag('input', array_merge(array('type'=>'checkbox','id'=>$id,'value'=>1), $unformattedValue ? array('checked'=>'checked'):array(), $options));
				//echo '<input type="hidden" value="1"/>';
				break;
			case 'date':
			case 'time':
			case 'datetime':
				$options['class'].=' '.$this->grid->id.'_datepickers';
				if ($this->grid->bootstrap) {
					$options['class'].= ' span2';
				}
				echo $this->grid->owner->widget('ext.TbDatepicker.TbDatepicker', array(
					'htmlOptions' => array_merge(array(
						'id'=>$id,
						'name'=>$this->name,
						'value'=>$value,
						'size'=>8,
						'maxlength'=>17,
						'style' => 'width: 6em;',
					), $options),
				), true);
				break;
			case 'image':
				throw new Exception('Editable column not implemented for type '.$this->type.' ('.var_export($value,true).')');
				break;
		}
	}
}
