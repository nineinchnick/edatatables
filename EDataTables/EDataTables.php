<?php
/**
 * EDataTables class file.
 *
 * @author Jan Was <janek.jan@gmail.com>
 * @link http://www.yiiframework.com/
 * @copyright Copyright &copy; 2011-2012 Jan Was
 * @license http://www.yiiframework.com/license/
 */

Yii::import('zii.widgets.grid.CGridView');
Yii::import('ext.EDataTables.*');

/**
 * EDataTables does the same thing as CGridView, but using the datatables.net control.
 * @todo translate original properties (events like beforeAjaxUpdate) to dataTables equivalents
 * @todo check for other features of CGridView (HTML classes, filters in headers, translations, pagers, summary etc.)
 * @todo refactor serverData, filterForm and filterColumnsMap properties
 * @todo implement renderAdvancedFilters
 * @todo a different set of columns for filters (filter by invisible columns)
 * @todo bbq support in DataTables
 *
 * docs todo:
 * @todo document alignment of numeric columns
 * @todo document usage of toolbar and its buttons (refresh, export, plot, new)
 * @todo document usage of filters
 * @todo document usage of checked rows with examples of server-side processing
 * @todo i18n support
 *
 * @author Jan Was <jwas@nets.com.pl>
 */
class EDataTables extends CGridView
{
	const FILTER_POS_EXTERNAL='external';
	private $_formatter;

	/**
	 * @var string Form selector. If set it tries to map columns to fields in that form, and if found it uses their values for filtering.
	 */
	public $filterForm = null;

	/**
	 * @var array Map column indexes to field ids from the filter form.
	 */
	public $filterColumnsMap = array();
	
	/**
	 * @var array Additional key/value pairs to be sent to the server data backend.
	 * If the value string starts with 'js:' it will be included as a callback, without quotes.
	 */
	public $serverData;
	/**
	 * @var string the text to be displayed in a data cell when a data value is null. This property will NOT be HTML-encoded
	 * when rendering. Defaults to an HTML blank.
	 */
	public $nullDisplay=null;

	public $template="{advancedFilters}\n{items}";
	public $datatableTemplate;
	public $tableBodyCssClass;
	public $newAjaxUrl;
	public $selectionChanged;
	
	public $options = array();
	/**
	 * @var array Array of unsaved changes, must be filled when redrawing a form containing this control after an unsuccessful save (failed validation).
	 */
	public $unsavedChanges = array('selected'=>'','deselected'=>'','values'=>'');
	public $buttons = array();

	/**
	 * @var boolean if true, bootstrap style will be used, jquery UI themeroller otherwise, which is the default
	 */
	public $bootstrap = false;

	/**
	 * @var array if not null, FixedHeader plugin will be initialized using this property as options
	 */
	public $fixedHeaders;

	public function init() {
		// check if a cookie exist holding some options and explode it into REQUEST
		// must be done before parent::init(), because it calls initColumns and it calls dataProvider->getData()
		// not done if options are passed through GET/POST
		if (isset($this->options['bStateSave']) && $this->options['bStateSave']) {
			self::restoreState($this->getId(), isset($this->options['sCookiePrefix']) ? $this->options['sCookiePrefix'] : 'edt_');
		}
		// this needs to be set before parent::init, where grid-view would be added and break bootstrap
		if($this->bootstrap && !isset($this->htmlOptions['class']))
            $this->htmlOptions['class']='';
		parent::init();
		/**
		 * @todo apparently CGridView wasn't meant to be inherited from
		 */
		$this->baseScriptUrl=Yii::app()->getAssetManager()->publish(Yii::getPathOfAlias('ext.EDataTables').'/assets');
		// append display to the table class, so JUI style on datatable will display properly
		if ($this->bootstrap) {
			$this->itemsCssClass='table table-striped table-bordered '.$this->itemsCssClass;
		} else {
			$this->itemsCssClass='display '.$this->itemsCssClass;
		}
	}

	/**
	 * If bStateSave was set when initializing widget it saves sorting and pagination into cookies.
	 * This method should be called before preparing a dataProvider,
	 * as it uses CSort which read $_REQUEST.
	 */
	public static function restoreState($id, $prefix = 'edt_') {
		if (!isset($_COOKIE) || isset($_REQUEST['iSortingCols'])) return;

		foreach($_COOKIE as $key=>$value) {
			if (strpos($key,$prefix)!==0 && substr($key, -strlen($id)) !== $id) continue;
			$options = json_decode($value, true);
			// for now, extract only sorting information
			if (isset($options['aaSorting']) && is_array($options['aaSorting'])) {
				$i=0;
				foreach($options['aaSorting'] as $sort) {
					if (!isset($sort[0]) || !isset($sort[1])) continue;
					$_REQUEST['iSortCol_'.$i] = $sort[0];
					$_REQUEST['sSortDir_'.$i++] = $sort[1];
				}
				$_REQUEST['iSortingCols']=$i;
			}
		}
	}

	/**
	 * Creates column objects and initializes them.
	 */
	protected function initColumns()
	{
		if($this->columns===array())
		{
			if($this->dataProvider instanceof CActiveDataProvider)
				$this->columns=$this->dataProvider->model->attributeNames();
			else if($this->dataProvider instanceof IDataProvider)
			{
				// use the keys of the first row of data as the default columns
				$data=$this->dataProvider->getData();
				if(isset($data[0]) && is_array($data[0]))
					$this->columns=array_keys($data[0]);
			}
		}
		$id=$this->getId();
		foreach($this->columns as $i=>$column)
		{
			if(is_string($column))
				$column=$this->createDataColumn($column);
			else
			{
				if(!isset($column['class'])) {
					$column['class']='ext.EDataTables.EDataColumn';
					$column=Yii::createComponent($column, $this);
				} else {
					//$exceptionParams = array('{class}' => $column['class'], '{column}' => $column['name']);
					$column=Yii::createComponent($column, $this);
					if (!method_exists($column,'getDataCellContent')) {
						$column->attachBehavior('cellContentBehavior','ext.EDataTables.ECellContentBehavior');
					}
				}
			}
			if($column->id===null)
				$column->id=$id.'_c'.$i;
			if (isset($column->type) && in_array($column->type,array('dec2','dec3','dec5','integer','number'))) {
				// all numeric types gets aligned right by default
				//if (!isset($column->headerHtmlOptions['class']))
				//	$column->headerHtmlOptions['class'] = 'ralign';
				if (!isset($column->htmlOptions['class']))
					$column->htmlOptions['class'] = 'ralign';
			}
			$this->columns[$i]=$column;
		}

		foreach($this->columns as $column)
			$column->init();
	}

	/**
	 * Creates a {@link CDataColumn} based on a shortcut column specification string.
	 * @param string $text the column specification string
	 * @return CDataColumn the column instance
	 */
	protected function createDataColumn($text)
	{
		if(!preg_match('/^([\w\.]+)(:(\w*))?(:(.*))?$/',$text,$matches))
			throw new CException(Yii::t('zii','The column must be specified in the format of "Name:Type:Label", where "Type" and "Label" are optional.'));
		$column=new EDataColumn($this);
		$column->name=$matches[1];
		if(isset($matches[3]) && $matches[3]!=='')
			$column->type=$matches[3];
		if(isset($matches[5]))
			$column->header=$matches[5];
		return $column;
	}

	protected function initColumnsJS() {
		$columnDefs = array();
		if ($this->selectableRows) {
			$columnDefs[] = array(
				"sWidth"		=> '20px',
				"bSearchable"	=> false,
				"bSortable"		=> false,
				/*
				"fnRender"		=> 'js:function(oObj){return $.eDataTables.renderCheckBoxCell(\''.$this->getId().'\',oObj);}',
				 */
				"aTargets"		=> array(0),
			);
		}
		if (isset($this->editableColumns) && !empty($this->editableColumns)) {
			$columnDefs[] = array(
				"fnRender"	=> 'js:function(oObj) {return $(\'#'.$this->getId().'\').eDataTables(\'renderEditableCell\', \''.$this->getId().'\',oObj);}',
				"aTargets"	=> $this->editableColumns,
			);
		}
		$sort = $this->dataProvider->getSort();
		$defaultOrder = array();
		if ($sort instanceof CSort && is_array($sort->defaultOrder)) {
			$defaultOrder = $sort->defaultOrder;
		}
		$hiddenColumns = array();
		$nonsortableColumns = array();
		$sortedColumns = array('asc'=>array(),'desc'=>array(),'all'=>array());
		$cssClasses = array();
		$groupColumns = array();
		foreach($this->columns as $i=>$column) {
			if(!$column->visible) {
				$hiddenColumns[] = $i;
			}
			if (!property_exists($column,'sortable') || !$column->sortable){
				$nonsortableColumns[] = $i;
			} else if (isset($defaultOrder[$column->name])) {
				$sortedColumns[$defaultOrder[$column->name] == CSort::SORT_ASC ? 'asc' : 'desc'][] = $i;
				$sortedColumns['all'][] = array($i, $defaultOrder[$column->name] == CSort::SORT_ASC ? 'asc' : 'desc');
			}
			if (isset($column->htmlOptions) && isset($column->htmlOptions['class'])) {
				if (!isset($cssClasses[$column->htmlOptions['class']])) $cssClasses[$column->htmlOptions['class']] = array();
				$cssClasses[$column->htmlOptions['class']][] = $i;
			}
		}
		/*
		if (!empty($sortedColumns['asc']))
			$columnDefs[] = array( "asSorting" => array('asc'), "aTargets" => $sortedColumns['asc'] );
		if (!empty($sortedColumns['desc']))
			$columnDefs[] = array( "asSorting" => array('desc'), "aTargets" => $sortedColumns['desc'] );
		 */
		if (!empty($sortedColumns['all']) && !isset($this->options['aaSorting'])) {
			$this->options['aaSorting'] = $sortedColumns['all'];
		}
		if (!empty($hiddenColumns))
			$columnDefs[] = array( "bVisible" => false, "aTargets" => $hiddenColumns );
		if (!empty($nonsortableColumns))
			$columnDefs[] = array( "bSortable" => false, "aTargets" => $nonsortableColumns );
		if (!empty($cssClasses)) {
			foreach($cssClasses as $cssClass => $targets) {
				$columnDefs[] = array( "sClass" => $cssClass, "aTargets" => $targets );
			}
		}
		if (isset($this->editableColumns) && !empty($this->editableColumns)) {
			/**
			 * @todo allow null values where permitted by column definition (tri-state checkbox, two checkboxes or three radios)
			 */
		}
		return $columnDefs;
	}

	/**
	 * Registers necessary client scripts.
	 */
	public function registerClientScript()
	{
		$id=$this->getId();
		$columnDefs = $this->initColumnsJS();
		if (isset($this->options['aoColumnDefs'])) {
			$this->options['aoColumnDefs'] = array_merge($columnDefs, $this->options['aoColumnDefs']);
		}
		$defaultOptions = array(
			'baseUrl'			=> CJSON::encode(Yii::app()->baseUrl),
			'bootstrap'			=> $this->bootstrap,
			// options inherited from CGridView JS scripts
			'ajaxUpdate'		=> $this->ajaxUpdate===false ? false : array_unique(preg_split('/\s*,\s*/',$this->ajaxUpdate.','.$id,-1,PREG_SPLIT_NO_EMPTY)),
			'ajaxOpts'			=> $this->serverData,
			'pagerClass'		=> $this->bootstrap ? 'paging_bootstrap pagination' : $this->pagerCssClass,
			'loadingClass'		=> $this->loadingCssClass,
			'filterClass'		=> $this->filterCssClass,
			//'tableClass'		=> $this->itemsCssClass,
			'selectableRows'	=> $this->selectableRows,
			// dataTables options
			'asStripClasses'	=> $this->rowCssClass,
			'iDeferLoading'		=> $this->dataProvider->getTotalItemCount(),
			'sAjaxSource'		=> CHtml::normalizeUrl($this->ajaxUrl),
			'aoColumnDefs'		=> $columnDefs,
			'sDom'				=> $this->bootstrap ? "<'row'<'span3'l><'dataTables_toolbar'><'pull-right'f>r>t<'row'<'span3'i><'pull-right'p>>" : "<'H'l<'dataTables_toolbar'>fr>t<'F'ip>",
			'bScrollCollapse'	=> false,
			'bStateSave'		=> false,
			'bPaginate'			=> true,
			'sCookiePrefix'		=> 'edt_',
			'bJQueryUI'			=> !$this->bootstrap,
		);
		if (Yii::app()->getLanguage() !== 'en_us') {
			// those are the defaults in the DataTables plugin JS source,
			// we don't need to set them if app language is already en_us
			$defaultOptions['oLanguage'] = array(
				"oAria" => array(
					"sSortAscending" => Yii::t('EDataTables.edt',": activate to sort column ascending"),
					"sSortDescending" => Yii::t('EDataTables.edt',": activate to sort column descending"),
				),
				"oPaginate" => array(
					"sFirst" => Yii::t('EDataTables.edt',"First"),
					"sLast" => Yii::t('EDataTables.edt',"Last"),
					"sNext" => Yii::t('EDataTables.edt',"Next"),
					"sPrevious" => Yii::t('EDataTables.edt',"Previous"),
				),
				"sEmptyTable" => Yii::t('EDataTables.edt',"No data available in table"),
				"sInfo" => Yii::t('EDataTables.edt',"Showing _START_ to _END_ of _TOTAL_ entries"),
				"sInfoEmpty" => Yii::t('EDataTables.edt',"Showing 0 to 0 of 0 entries"),
				"sInfoFiltered" => Yii::t('EDataTables.edt',"(filtered from _MAX_ total entries)"),
				//"sInfoPostFix" => "",
				//"sInfoThousands" => ",",
				"sLengthMenu" => Yii::t('EDataTables.edt',"Show _MENU_ entries"),
				"sLoadingRecords" => Yii::t('EDataTables.edt',"Loading..."),
				"sProcessing" => Yii::t('EDataTables.edt',"Processing..."),
				"sSearch" => Yii::t('EDataTables.edt',"Search:"),
				//"sUrl" => "",
				"sZeroRecords" => Yii::t('EDataTables.edt',"No matching records found"),
			);
			$localeSettings = localeconv();
			if (!empty($localeSettings['decimal_point'])) {
				$defaultOptions['oLanguage']["sInfoThousands"] = $localeSettings['decimal_point'];
			}
		}
		$options=array_merge($defaultOptions, $this->options);
		if($this->newAjaxUrl!==null)
			$options['newUrl']=CHtml::normalizeUrl($this->newAjaxUrl);
		if($this->ajaxUrl!==null)
			$options['url']=CHtml::normalizeUrl($this->ajaxUrl);
		if($this->updateSelector!==null)
			$options['updateSelector']=$this->updateSelector;
		if($this->enablePagination)
			$options['pageVar']=$this->dataProvider->getPagination()->pageVar;
		if($this->bootstrap)
			$options['sPaginationType'] = 'bootstrap';
		if ($this->datatableTemplate)
			$options['sDom'] = $this->datatableTemplate;
		// not used in jdatatables.js, used in fnServerData set below
		//! @todo as of datatables 1.9.0 fnServerData could be simplified, since we only modify aoData's properties
		//if($this->beforeAjaxUpdate!==null)
		//	$options['beforeAjaxUpdate']=(strpos($this->beforeAjaxUpdate,'js:')!==0 ? 'js:' : '').$this->beforeAjaxUpdate;
		if($this->afterAjaxUpdate!==null)
			$options['afterAjaxUpdate']=(strpos($this->afterAjaxUpdate,'js:')!==0 ? 'js:' : '').$this->afterAjaxUpdate;
		if($this->ajaxUpdateError!==null)
			$options['ajaxUpdateError']=(strpos($this->ajaxUpdateError,'js:')!==0 ? 'js:' : '').$this->ajaxUpdateError;
		if($this->selectionChanged!==null)
			$options['selectionChanged']=(strpos($this->selectionChanged,'js:')!==0 ? 'js:' : '').$this->selectionChanged;
		$options['buttons']=array_merge(array(
			'refresh' => array(
				'label' => 'Odśwież',
				'text' => false,
				'htmlClass' => 'refreshButton',
				'icon' => $this->bootstrap ? 'icon-refresh' : 'ui-icon-refresh',
				'callback' => null //default will be used, if possible
			),
			'print' => array(
				'label' => 'Drukuj',
				'text' => false,
				'htmlClass' => 'printButton',
				'icon' => $this->bootstrap ? 'icon-print' : 'ui-icon-print',
				'callback' => null //default will be used, if possible
			),
			'export' => array(
				'label' => 'CSV',
				'text' => false,
				'htmlClass' => 'exportButton',
				'icon' => $this->bootstrap ? 'icon-download-alt' : 'ui-icon-disk',
				'callback' => null //default will be used, if possible
			),
			'new' => array(
				'label' => 'Dodaj',
				'text' => true,
				'htmlClass' => 'newButton',
				'icon' => $this->bootstrap ? 'icon-plus' : 'ui-icon-document',
				'callback' => null //default will be used, if possible
			)
		),$this->buttons);

		/**
		 * unserialize unsaved data into JS data structures, ready to be binded to DOM elements through .data()
		 */
		$values = array();
		parse_str($this->unsavedChanges['values'], $values);
		$us = trim($this->unsavedChanges['selected'],',');
		$ud = trim($this->unsavedChanges['deselected'],',');
		$options['unsavedChanges'] = array(
			'selected' => !empty($us) ? array_fill_keys(explode(',',$us),true) : array(),
			'deselected' => !empty($ud) ? array_fill_keys(explode(',',$ud),true) : array(),
			'values' => $values,
		);

		$baseUrl = Yii::app()->baseUrl;

		$serverData = array(
			"aoData.push({'name': '".$this->ajaxVar."', 'value': '".$this->getId()."'});"
		);
		if (isset($this->serverData) && is_array($this->serverData)) {
			foreach($this->serverData as $k => $s) {
				$serverData[] = "aoData.push({'name': '$k', 'value': ".(substr($s,0,3) === 'js:' ? substr($s,3) : "'$s'")."});";
			}
		}
		$formData = '';
		if ($this->filterForm !== null) {
				$formData .= <<<EOT
			$.merge(aoData,$('{$this->filterForm}').serializeArray());
			var csrfToken = $('{$this->filterForm} input[name=YII_CSRF_TOKEN]');
			if (csrfToken.length > 0)
				aoData.push({'name': 'YII_CSRF_TOKEN','value':csrfToken.val()});
			aoData.push({'name':'submit','value':true});
EOT;
		}
		if (!empty($this->filterColumnsMap)) {
			$columnsMap = array();
			foreach($this->filterColumnsMap as $idx => $filter_id) {
				if (is_numeric($idx)) {
					$columnsMap[] = "case 'sSearch_$idx': aoData[i].value = $('#$filter_id').val(); break;";
				} else {
					$name = strpos($idx,'js:')===0 ? substr($idx,3) : "'$idx'";
					$serverData[] = "aoData.push({'name': $name, 'value': $('#$filter_id').val()});";
				}
			}
			if (!empty($columnsMap)) {
				$columnsMap = implode("\n\t\t\t\t",$columnsMap);
				$formData .= <<<EOT
			for(var i in aoData) {
				switch(aoData[i].name) {
					default: break;
					$columnsMap
				}
			}
EOT;
			}
		}
		$options['fnServerData'] = "js:function ( sSource, aoData, fnCallback ) {
			".implode("\n\t\t\t",$serverData).<<<EOT
			$formData
			var settings = $.fn.eDataTables.settings['{$this->getId()}'];
			if(settings.beforeAjaxUpdate !== undefined)
				settings.beforeAjaxUpdate('{$this->getId()}');
			$.ajax( {
				'dataType': 'json',
				'type': 'POST',
				'url': sSource,
				'data': aoData,
				'success': [function(data){return $('#{$this->getId()}').eDataTables('ajaxSuccess', data);},fnCallback],
				'error': function(XHR, textStatus, errorThrown){return \$.fn.eDataTables.ajaxError(XHR, textStatus, errorThrown, settings)}
			} );
		}
EOT;
		
		$options=CJavaScript::encode($options);
		self::initClientScript($this->bootstrap, $this->fixedHeaders !== null);
		$cs=Yii::app()->getClientScript();
		$cs->registerScript(__CLASS__.'#'.$id,"jQuery('#$id').eDataTables($options);");
		if ($this->fixedHeaders !== null) {
			//$cs->registerScript(__CLASS__.'#'.$id.'_fixedheader',"new FixedHeader( $.fn.eDataTables.tables['$id'], ".CJavaScript::encode($this->fixedHeaders)." );");
			//$cs->registerScript(__CLASS__.'#'.$id.'_fixedheader',"new FixedColumns( $.fn.eDataTables.tables['$id'], ".CJavaScript::encode($this->fixedHeaders)." );");
		}
	}
	
	public static function initClientScript($bootstrap=false, $fixedHeaders=false){
		$baseScriptUrl = Yii::app()->getAssetManager()->publish(Yii::getPathOfAlias('ext.EDataTables').'/assets');

		$cs=Yii::app()->getClientScript();
		$cs->registerCoreScript('jquery');
		if ($bootstrap) {
			//$cs->registerCssFile($baseScriptUrl.'/jquery.dataTables.css');
			$cs->registerCssFile($baseScriptUrl.'/bootstrap.dataTables.css');
		} else {
			$cs->registerCssFile($baseScriptUrl.'/demo_table_jui.css');
			$cs->registerCssFile($baseScriptUrl.'/jquery.dataTables_themeroller.css');
			$cs->registerCssFile($baseScriptUrl.'/smoothness/jquery-ui-1.8.17.custom.css');
			$cs->registerCoreScript('jquery.ui');
		}
		$cs->registerScriptFile($baseScriptUrl.'/jquery.dataTables'.(YII_DEBUG ? '' : '.min' ).'.js');
		$cs->registerScriptFile($baseScriptUrl.'/jquery.fnSetFilteringDelay.js');
		$cs->registerScriptFile($baseScriptUrl.'/jdatatable.js',CClientScript::POS_END);
		if ($fixedHeaders !== null) {
			//$cs->registerScriptFile($baseScriptUrl.'/FixedHeader'.(YII_DEBUG ? '' : '.min').'.js');
			//$cs->registerScriptFile($baseScriptUrl.'/FixedColumns'.(YII_DEBUG ? '' : '.min').'.js');
		}
	}

	public function renderAdvancedFilters()
	{
		if($this->filterPosition===self::FILTER_POS_EXTERNAL && $this->filter!==null) {
			echo "<div class=\"{$this->filterCssClass}\">\n";
			/**
			 * @todo choose what filters to display
			 */
			foreach($this->columns as $column)
				$column->renderFilterCell();
			echo "</div>\n";
		}
	}

    /**
     * Renders the table body.
     */ 
    public function renderTableBody()
    {   
        $data=$this->dataProvider->getData();
        $n=count($data);
        echo "<tbody".($this->tableBodyCssClass !== null ? ' class="'.$this->tableBodyCssClass.'"' : '').">\n";
            
		// unlike in CGridView, here we don't render a special row when table is empty - it breaks the datatables
		for($row=0;$row<$n;++$row)
			$this->renderTableRow($row);
        echo "</tbody>\n";
    }
	
	public function renderKeys() {
		// base class code + choosing keys
		echo CHtml::openTag('div',array(
			'class'=>'keys',
			'style'=>'display:none',
			'title'=>Yii::app()->getRequest()->getUrl(),
		));
		//! @todo we don't use getKeys() here which caches keys in _keys property -> check consequences
		$hasKeyAttribute=property_exists(get_class($this->dataProvider),'keyAttribute');
		foreach($this->dataProvider->getData() as $i=>$data) {
			// check dataProvider compatibility
			if (!$hasKeyAttribute && !method_exists($data,'getPrimaryKey')) {
				continue;
			}
			$key=!$hasKeyAttribute || $this->dataProvider->keyAttribute===null ? $data->getPrimaryKey() : $data->{$this->dataProvider->keyAttribute};
			$key=is_array($key) ? implode(',',$key) : $key;
			echo "<span>".CHtml::encode($key)."</span>";
		}
		echo "</div>\n";
		// extra code
		if ($this->selectableRows) {
			echo '<input type="hidden" name="'.$this->getId().'-selected" id="'.$this->getId().'-selected" value="'.$this->unsavedChanges['selected'].'"/>';
			echo '<input type="hidden" name="'.$this->getId().'-deselected" id="'.$this->getId().'-deselected" value="'.$this->unsavedChanges['deselected'].'"/>';
		}
		echo '<input type="hidden" name="'.$this->getId().'-values" id="'.$this->getId().'-values" value="'.$this->unsavedChanges['values'].'"/>';
	}

	/**
	 * @return CFormatter the formatter instance. Defaults to the 'format' application component.
	 */
	public function getFormatter()
	{
		if($this->_formatter===null)
			$this->_formatter=Yii::app()->format;
		return $this->_formatter;
	}

	/**
	 * @param CFormatter $value the formatter instance
	 */
	public function setFormatter($value)
	{
		$this->_formatter=$value;
	}

	/**
	 * Returns formatted dataset from dataProvider in an array
	 * instead of rendering a HTML table. @see renderTableBody
	 * 
	 * @access public
	 * @param int $sEcho
	 * @return void
	 */
	public function getFormattedData($sEcho) {
		$result = array();

        $data=$this->dataProvider->getData();
		$n=count($data);
		for($row=0; $row<$n; ++$row) {
			$dataRow = $data[$row];
			$currentRow = array();
			foreach($this->columns as $column) {
				$currentRow[] = $column->getDataCellContent($row,$dataRow);
			}
			$result[$row] = $currentRow;
		}
		$keys=array();
		$hasKeyAttribute=property_exists(get_class($this->dataProvider),'keyAttribute');
		foreach($this->dataProvider->getData() as $i=>$data) {
			// check dataProvider compatibility
			if (!$hasKeyAttribute && !method_exists($data,'getPrimaryKey')) {
				continue;
			}
			$key=!$hasKeyAttribute || $this->dataProvider->keyAttribute===null ? $data->getPrimaryKey() : $data->{$this->dataProvider->keyAttribute};
			$keys[]=is_array($key) ? implode(',',$key) : $key;
		}
		return array(
			'sEcho'					=> $sEcho,
			'iTotalRecords'			=> $this->dataProvider->getTotalItemCount(),
			'iTotalDisplayRecords'	=> $this->dataProvider->getTotalItemCount(),
			'aaData'				=> $result,
			'keys'					=> $keys,
		);
	}
}
