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
 * @todo bbq support in DataTables
 *
 * docs todo:
 * @todo document alignment of numeric columns
 * @todo document usage of toolbar and its buttons (refresh, export, plot, new)
 * @todo document usage of filters
 * @todo document usage of checked rows with examples of server-side processing
 *
 * @author Jan Was <jwas@nets.com.pl>
 */
class EDataTables extends CGridView
{
	// reset the template, pagination and other elements are drawn by dataTables JS plugin
	public $template="{items}";
	/**
	 * @var string Form selector. If set it tries to map columns to fields in that form, and if found it uses their values for filtering.
	 */
	public $filterForm = null;

	/**
	 * @var array Additional key/value pairs to be sent to the server data backend.
	 * If the value string starts with 'js:' it will be included as a callback, without quotes.
	 */
	public $serverData = array();
	/**
	 * @var string the text to be displayed in a data cell when a data value is null. This property will NOT be HTML-encoded
	 * when rendering. Defaults to an HTML blank.
	 */
	public $nullDisplay=null;

	/**
	 * @var string template used by dataTables, defaults are:
	 * - for bootstrap theme: <><'row'<'span3'l><'dataTables_toolbar'><'pull-right'f>r>t<'row'<'span3'i><'pull-right'p>>
	 * - for jQuery UI theme: <><'H'l<'dataTables_toolbar'>fr>t<'F'ip>
	 */
	public $datatableTemplate;
	public $tableBodyCssClass;
	public $newAjaxUrl;
	public $selectionChanged;
	/**
	 * @var boolean if change in editable columns selects current row;
	 *				useful in relation tables, where only selected rows are saved;
	 *				when rows are selected for removal, this should be false;
	 */
	public $editableSelectsRow = true;
	/**
	 * @var boolean should the toolbar contain a checkbox for filtering data to only related (and selected)
	 */
	public $relatedOnlyOption = false;
	
	public $options = array();
	/** 
	 * Contains keys:
	 * 'all-selected' - empty string (defalut) or one of:
	 *		'select'	- select all records except 'deselected' and 'disconnect'
	 *		'deselect'	- deselect all records except 'selected'
	 * 'selected'	- comma separated string with selected ids (empty if 'all-selected' == 'select')
	 * 'deselected' - comma separated string with deselected ids (empty if 'all-selected' != 'select')
	 *				contains ids which WASN'T initially selected (before eDataTables instance initialized)
	 * 'disconnect' - comma separated string with deselected ids (empty if 'all-selected' != 'select')
	 *				contains ids which WAS initially selected (before eDataTables instance initialized)
	 * @var array Array of unsaved changes, must be filled when redrawing a form containing this control
	 *            after an unsuccessful save (failed validation).
	 */
	public $unsavedChanges = array('all-selected'=>'','selected'=>'','deselected'=>'','disconnect'=>'','values'=>'');
	/**
	 * @var array if null, disables all default buttons, if an array, will be merged with default buttons (refresh, configure)
	 */
	public $buttons = array();

	/**
	 * @var string the CSS class name for the container of all data item display.
	 * Defaults to 'table table-striped table-bordered table-condensed items' for bootstrap or 'display items' for jquery UI.
	 */
	public $itemsCssClass;

	/**
	 * @var boolean if true, bootstrap style will be used, jquery UI themeroller otherwise, which is the default
	 */
	public $bootstrap = false;

	/**
	 * @var array if not null, FixedHeader plugin will be initialized using this property as options
	 */
	public $fixedHeaders;

	public function init() {
		// check if a cookie exist holding some options and explode it into GET
		// must be done before parent::init(), because it calls initColumns and it calls dataProvider->getData()
		// not done if options are passed through GET/POST
		if (isset($this->options['bStateSave']) && $this->options['bStateSave']) {
			self::restoreState($this->getId(), isset($this->options['sCookiePrefix']) ? $this->options['sCookiePrefix'] : 'edt_');
		} else {
			// probably disabled forever, must be called before preparing dataProvider anyway
			//$this->restoreStateSessionInternal();
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
		if ($this->itemsCssClass === null) {
			if ($this->bootstrap) {
				$this->itemsCssClass='table table-striped table-bordered table-condensed items';
			} else {
				$this->itemsCssClass='display items';
			}
		}
	}

	/**
	 * If bStateSave was set when initializing widget it saves sorting and pagination into cookies.
	 * This method should be called before preparing a dataProvider,
	 * as it uses CSort which read $_GET.
	 */
	public static function restoreState($id, $prefix = 'edt_') {
		if (!isset($_COOKIE) || isset($_GET['iSortingCols'])) return;

		foreach($_COOKIE as $key=>$value) {
			if (strpos($key,$prefix)!==0 && substr($key, -strlen($id)) !== $id) continue;
			$options = json_decode($value, true);
			// for now, extract only sorting information
			if (isset($options['aaSorting']) && is_array($options['aaSorting'])) {
				$i=0;
				foreach($options['aaSorting'] as $sort) {
					if (!isset($sort[0]) || !isset($sort[1])) continue;
					$_GET['iSortCol_'.$i] = $sort[0];
					$_GET['sSortDir_'.$i++] = $sort[1];
				}
				$_GET['iSortingCols']=$i;
			}
		}
	}

	protected function restoreStateSessionInternal() {
		$sort = $this->dataProvider->getSort();
		$pagination = $this->dataProvider->getPagination();
		if (!($sort instanceof EDTSort) || !($pagination instanceof EDTPagination)) return;

		self::restoreStateSession($this->getId(), $sort, $pagination);
	}

	public static function restoreStateSession($id, $sort, $pagination, &$columns) {
		$session = Yii::app()->session['edatatables'];
		if ($sort===null) $sort = new EDTSort;
		if ($pagination===null) $pagination = new EDTPagination;
		if (isset($_GET[$sort->sortVar]) && ($iSortingCols = intval($_GET[$sort->sortVar])) > 0) {
			// save state
			$sortParams = array('count'=>$iSortingCols,'indexes'=>array(),'dirs'=>array());
			for ($i = 0; $i < $iSortingCols && isset($_GET[$sort->sortVarIdxPrefix.$i]); ++$i) {
				$sortParams['indexes'][$sort->sortVarIdxPrefix.$i] = $_GET[$sort->sortVarIdxPrefix.$i];
				if (isset($_GET[$sort->sortVarDirPrefix.$i])) {
					$sortParams['dirs'][$sort->sortVarDirPrefix.$i] = $_GET[$sort->sortVarDirPrefix.$i];
				}
			}
			$session[$id]['sort'] = $sortParams;
		} else if (isset($session[$id]) && isset($session[$id]['sort'])) {
			// restore state
			$sortParams = $session[$id]['sort'];
			$_GET[$sort->sortVar] = $sortParams['count'];
			for ($i = 0; $i < $sortParams['count']; ++$i) {
				$_GET[$sort->sortVarIdxPrefix.$i] = $sortParams['indexes'][$sort->sortVarIdxPrefix.$i];
				if (isset($sortParams['dirs'][$sort->sortVarDirPrefix.$i])) {
					 $_GET[$sort->sortVarDirPrefix.$i] = $sortParams['dirs'][$sort->sortVarDirPrefix.$i];
				}
			}
		}
		if ($pagination instanceof EDTPagination && isset($_GET[$pagination->pageVar]) && isset($_GET[$pagination->lengthVar])) {
			// save state
			$session[$id]['pagination'] = array(
				'length' => $_GET[$pagination->lengthVar],
				'page' => $_GET[$pagination->pageVar],
			);
		} else if (isset($session[$id]) && isset($session[$id]['pagination'])) {
			// restore state
			$_GET[$pagination->lengthVar] = $session[$id]['pagination']['length'];
			$_GET[$pagination->pageVar] = $session[$id]['pagination']['page'];
		}
		if (isset($_GET['sSearch'])) {
			$session[$id]['search'] = $_GET['sSearch'];
		} else if (isset($session[$id]) && isset($session[$id]['search'])) {
			$_GET['sSearch'] = $session[$id]['search'];
		}
		$orderedColumnNames = null;
		$visibleColumnIndexes = null;
		if (isset($_GET['sColumns']) && ($o=trim($_GET['sColumns'],', ')) !== '') {
			$orderedColumnNames = array_flip(explode(',',$o));
			if (isset($_GET['visibleColumns']) && ($v=trim($_GET['visibleColumns'],', ')) !== '') {
				$visibleColumnIndexes = array_flip(explode(',',$v));
			}
			$session[$id]['orderedColumnNames'] = $orderedColumnNames;
			$session[$id]['visibleColumnIndexes'] = $visibleColumnIndexes;
		} else if (isset($session[$id]) && isset($session[$id]['orderedColumnNames'])) {
			$orderedColumnNames = $session[$id]['orderedColumnNames'];
			$visibleColumnIndexes = $session[$id]['visibleColumnIndexes'];
		}
		if ($orderedColumnNames !== null) {
			$newOrder = array();
			foreach($columns as $key=>$column) {
				if (is_string($column)) {
					$column = self::createArrayColumn($column);
				}
				$name = isset($column['name']) ? $column['name'] : 'unnamed'.$key;
				if (!isset($orderedColumnNames[$name])) {
					// columns unspecified will remain in old position
					//! @todo from now on, all values from $orderedColumnNames should be incremented
					//        because next column will have same index as this one
					$newOrder[$key] = $key;
					continue;
				}
				// note new position
				$newOrder[$key] = $orderedColumnNames[$name];
				// update visibility, possibly changing the column spec from string to array
				if (!empty($visibleColumnIndexes)) {
					if (isset($visibleColumnIndexes[$orderedColumnNames[$name]])) {
						// if column specs had this key overwrite it
						// if not, it was visible anyway
						if (isset($column['visible']))
							$columns[$key]['visible'] = true;
					} else {
						// overwrite the visible key
						// and the column spec, changing it from string to array if necessary
						$column['visible'] = false;
						$columns[$key] = $column;
					}
				}
			}
			array_multisort($newOrder, $columns);
		}
		Yii::app()->session['edatatables'] = $session;
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
	 * Creates an array based on a shortcut column specification string.
	 * @param string $text the column specification string
	 * @return array the column specification array
	 */
	protected static function createArrayColumn($text)
	{
		if(!preg_match('/^([\w\.]+)(:(\w*))?(:(.*))?$/',$text,$matches))
			throw new CException(Yii::t('zii','The column must be specified in the format of "Name:Type:Label", where "Type" and "Label" are optional.'));
		$column=array();
		$column['name']=$matches[1];
		if(isset($matches[3]) && $matches[3]!=='')
			$column['type']=$matches[3];
		if(isset($matches[5]))
			$column['header']=$matches[5];
		return $column;
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
		if (isset($this->editableColumns) && !empty($this->editableColumns)) {
			$columnDefs[] = array(
				"fnRender"	=> "js:function(oObj) {return $('#{$this->getId()}').eDataTables('renderEditableCell', '{$this->getId()}', oObj);}",
				"aTargets"	=> $this->editableColumns,
			);
		}
		$sort = $this->dataProvider->getSort();
		$defaultOrder = array();
		if ($sort instanceof CSort) {
			$defaultOrder = $sort->getDirections();
		}
		$hiddenColumns = array();
		$nonsortableColumns = array();
		$sortedColumns = array('asc'=>array(),'desc'=>array(),'all'=>array());
		$cssClasses = array();
		$groupColumns = array();
		foreach($this->columns as $i=>$column) {
			if (property_exists($column, 'name') && trim($column->name)!=='') {
				$sName = $column->name;
			} else {
				$sName = 'unnamed'.$i;
			}
			$columnDefs[] = array( "sName" => $sName, "aTargets" => array($i) );
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
		} else if (!isset($this->options['aaSorting'])) {
			$this->options['aaSorting'] = array();
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
			'ajaxOpts'			=> array_merge(array($this->ajaxVar => $this->getId()), $this->serverData),
			'pagerClass'		=> $this->bootstrap ? 'paging_bootstrap pagination' : $this->pagerCssClass,
			'loadingClass'		=> $this->loadingCssClass,
			'filterClass'		=> $this->filterCssClass,
			//'tableClass'		=> $this->itemsCssClass,
			'selectableRows'	=> $this->selectableRows,
			'editableSelectsRow'=> $this->editableSelectsRow,
			// dataTables options
			'asStripClasses'	=> $this->rowCssClass,
			'iDeferLoading'		=> $this->dataProvider->getTotalItemCount(),
			'sAjaxSource'		=> CHtml::normalizeUrl($this->ajaxUrl),
			'aoColumnDefs'		=> $columnDefs,
			'sDom'				=> $this->bootstrap ? "<><'row'<'span3'l><'dataTables_toolbar'><'pull-right'f>r>t<'row'<'span3'i><'pull-right'p>>" : "<><'H'l<'dataTables_toolbar'>fr>t<'F'ip>",
			'bScrollCollapse'	=> false,
			'bStateSave'		=> false,
			'bPaginate'			=> true,
			'sCookiePrefix'		=> 'edt_',
			'bJQueryUI'			=> !$this->bootstrap,
			'relatedOnlyLabel'	=> Yii::t('EDataTables.edt', 'Only related'),
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
		if($this->enablePagination) {
			$pagination = $this->dataProvider->getPagination();
			if ($pagination instanceof EDTPagination) {
				$options[$pagination->pageVar]=$pagination->getCurrentPage()*$pagination->getPageSize();
				$options[$pagination->lengthVar]=$pagination->getPageSize();
			}
		}
		if($this->bootstrap)
			$options['sPaginationType'] = 'bootstrap';
		if ($this->datatableTemplate)
			$options['sDom'] = $this->datatableTemplate;
		if($this->beforeAjaxUpdate!==null)
			$options['beforeAjaxUpdate']=(strpos($this->beforeAjaxUpdate,'js:')!==0 ? 'js:' : '').$this->beforeAjaxUpdate;
		if($this->afterAjaxUpdate!==null)
			$options['afterAjaxUpdate']=(strpos($this->afterAjaxUpdate,'js:')!==0 ? 'js:' : '').$this->afterAjaxUpdate;
		if($this->ajaxUpdateError!==null)
			$options['ajaxUpdateError']=(strpos($this->ajaxUpdateError,'js:')!==0 ? 'js:' : '').$this->ajaxUpdateError;
		if($this->selectionChanged!==null)
			$options['selectionChanged']=(strpos($this->selectionChanged,'js:')!==0 ? 'js:' : '').$this->selectionChanged;
		if($this->relatedOnlyOption)
			$options['relatedOnlyOption']=$this->relatedOnlyOption;
		if($this->filterForm)
			$options['filterForm']=$this->filterForm;
		if(isset($_GET['sSearch']))
			$options['oSearch']=array('sSearch'=>$_GET['sSearch']);
		if ($this->buttons === null) {
			$options['buttons']=array();
		} else {
			$options['buttons']=array_merge(array(
				'refresh' => array(
					'label' => Yii::t('EDataTables.edt',"Refresh"),
					'text' => false,
					'htmlClass' => 'refreshButton',
					'icon' => $this->bootstrap ? 'icon-refresh' : 'ui-icon-refresh',
					'callback' => null //default will be used, if possible
				),
				/*'configure' => array(
					'label' => Yii::t('EDataTables.edt',"Configure"),
					'text' => false,
					'htmlClass' => 'configureButton',
					'icon' => $this->bootstrap ? 'icon-cog' : 'ui-icon-cog',
					'callback' => null //default will be used, if possible
				),*/
				/*
				'print' => array(
					'label' => Yii::t('EDataTables.edt',"Print"),
					'text' => false,
					'htmlClass' => 'printButton',
					'icon' => $this->bootstrap ? 'icon-print' : 'ui-icon-print',
					'callback' => null //default will be used, if possible
				),
				'export' => array(
					'label' => Yii::t('EDataTables.edt',"Save as CSV"),
					'text' => false,
					'htmlClass' => 'exportButton',
					'icon' => $this->bootstrap ? 'icon-download-alt' : 'ui-icon-disk',
					'callback' => null //default will be used, if possible
				),
				'new' => array(
					'label' => Yii::t('EDataTables.edt',"Add new"),
					'text' => true,
					'htmlClass' => 'newButton',
					'icon' => $this->bootstrap ? 'icon-plus' : 'ui-icon-document',
					'callback' => null //default will be used, if possible
				),
				*/
			),$this->buttons);
		}
		$configurable = isset($options['buttons']['configure']) && $options['buttons']['configure'] !== null;
		if ($configurable) {
			// block draggable column headers
			$options['oColReorder'] = array(
				'iFixedColumns' => count($this->columns)
			);
			$options['sDom'] .= 'R';
		}

		/**
		 * unserialize unsaved data into JS data structures, ready to be binded to DOM elements through .data()
		 */
		$values = self::parseQuery($this->unsavedChanges['values']);
		$us = trim($this->unsavedChanges['selected'],',');
		$udeselected = trim($this->unsavedChanges['deselected'],',');
		$ud = trim($this->unsavedChanges['disconnect'],',');
		$options['unsavedChanges'] = array(
			'all_selected' => $this->unsavedChanges['all-selected'],
			'selected' => !empty($us) ? array_fill_keys(explode(',',$us),true) : array(),
			'deselected' => !empty($udeselected) ? array_fill_keys(explode(',',$udeselected),true) : array(),
			'disconnect' => !empty($ud) ? array_fill_keys(explode(',',$ud),true) : array(),
			'values' => $values,
		);

		$options['fnServerParams'] = "js:function(aoData){return $('#{$this->getId()}').eDataTables('serverParams', aoData);}";
		$options['fnServerData'] = "js:function(sSource, aoData, fnCallback){return $('#{$this->getId()}').eDataTables('serverData', sSource, aoData, fnCallback);}";
		
		self::initClientScript($this->bootstrap, $this->fixedHeaders !== null, $configurable);
		$options=CJavaScript::encode($options);
		$cs=Yii::app()->getClientScript();
		$cs->registerScript(__CLASS__.'#'.$id,"jQuery('#$id').eDataTables($options);");
		if ($this->fixedHeaders !== null) {
			//$cs->registerScript(__CLASS__.'#'.$id.'_fixedheader',"new FixedHeader( $.fn.eDataTables.tables['$id'], ".CJavaScript::encode($this->fixedHeaders)." );");
			//$cs->registerScript(__CLASS__.'#'.$id.'_fixedheader',"new FixedColumns( $.fn.eDataTables.tables['$id'], ".CJavaScript::encode($this->fixedHeaders)." );");
		}
	}
	
	public static function initClientScript($bootstrap=false, $fixedHeaders=false, $configurable=false){
		$baseScriptUrl = Yii::app()->getAssetManager()->publish(Yii::getPathOfAlias('ext.EDataTables').'/assets');

		$cs=Yii::app()->getClientScript();
		$cs->registerCoreScript('jquery');
		if ($bootstrap) {
			//$cs->registerCssFile($baseScriptUrl.'/jquery.dataTables.css');
			$cs->registerCssFile($baseScriptUrl.'/bootstrap.dataTables.css');
			if ($configurable) {
				$cs->registerCoreScript('jquery.ui');
			}
		} else {
			$cs->registerCssFile($baseScriptUrl.'/demo_table_jui.css');
			$cs->registerCssFile($baseScriptUrl.'/jquery.dataTables_themeroller.css');
			$cs->registerCssFile($baseScriptUrl.'/smoothness/jquery-ui-1.8.17.custom.css');
			$cs->registerCoreScript('jquery.ui');
		}
		$cs->registerScriptFile($baseScriptUrl.'/jquery.dataTables'.(YII_DEBUG ? '' : '.min' ).'.js');
		if ($configurable) {
			$cs->registerScriptFile($baseScriptUrl.'/ColReorder'.(YII_DEBUG ? '' : '.min' ).'.js');
			$selectBaseUrl = Yii::app()->getAssetManager()->publish(Yii::getPathOfAlias('ext.select2').'/assets');
			$cs->registerCssFile($selectBaseUrl . '/select2.css');
			$cs->registerScriptFile($selectBaseUrl . '/select2.'.(YII_DEBUG ? 'min.' : '').'js');
		}
		$cs->registerScriptFile($baseScriptUrl.'/jquery.fnSetFilteringDelay.js');
		$cs->registerScriptFile($baseScriptUrl.'/jdatatable.js',CClientScript::POS_END);
		if ($fixedHeaders !== null) {
			//$cs->registerScriptFile($baseScriptUrl.'/FixedHeader'.(YII_DEBUG ? '' : '.min').'.js');
			//$cs->registerScriptFile($baseScriptUrl.'/FixedColumns'.(YII_DEBUG ? '' : '.min').'.js');
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

	/**
	 * This method should be used instead of getKeys from the dataProvider.
	 */
	protected function getKeys() {
		$keys = array();
		//! @todo we don't use getKeys() here which caches keys in _keys property -> check consequences
		$hasKeyAttribute=property_exists(get_class($this->dataProvider),'keyAttribute') || property_exists(get_class($this->dataProvider),'keyField');
		$keyAttribute = $hasKeyAttribute ? (property_exists(get_class($this->dataProvider),'keyAttribute') ? 'keyAttribute' : 'keyField') : null;
		foreach($this->dataProvider->getData() as $i=>$data) {
			// check dataProvider compatibility
			if (!$hasKeyAttribute) {
				continue;
			}
			if ($hasKeyAttribute) {
				if ($keyAttribute=='keyField') {
					if (isset($data[$keyAttribute]))
						$key = $data[$keyAttribute];
					else
						continue;
				} else if ($keyAttribute !== null && !empty($this->dataProvider->$keyAttribute)) {
					$key = $data->{$this->dataProvider->$keyAttribute};
				} else if (method_exists($data, 'getPrimaryKey')) {
					$key = $data->getPrimaryKey();
				} else {
					continue;
				}
			} else {
				if (method_exists($data, 'getPrimaryKey')) {
					$key = $data->getPrimaryKey();
				} else {
					continue;
				}
			}
			$key=is_array($key) ? implode('-',$key) : $key;
			$keys[] = $key;
		}
		return $keys;
	}
	
	public function renderKeys() {
		// base class code + choosing keys
		echo CHtml::openTag('div',array(
			'class'=>'keys',
			'style'=>'display:none',
			'title'=>Yii::app()->getRequest()->getUrl(),
		));
		//! @todo we don't use getKeys() here which caches keys in _keys property -> check consequences
		foreach($this->getKeys() as $key) {
			echo "<span>".CHtml::encode($key)."</span>";
		}
		echo "</div>\n";
		// extra code
		if ($this->selectableRows) {
			$specialFields = array('all-selected', 'selected', 'deselected', 'disconnect');
			foreach($specialFields as $field) {
				if (!isset($this->unsavedChanges[$field])) continue;
				echo CHtml::hiddenField(
					$this->getId().'-'.$field,
					$this->unsavedChanges[$field],
					array('id'=>$this->getId().'-'.$field)
				);
			}
		}
		if (isset($this->unsavedChanges['values']))
			echo CHtml::hiddenField(
				$this->getId().'-values',
				$this->unsavedChanges['values'],
				array('id'=>$this->getId().'-values')
			);
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
		return array(
			'sEcho'					=> $sEcho,
			'iTotalRecords'			=> $this->dataProvider->getTotalItemCount(),
			'iTotalDisplayRecords'	=> $this->dataProvider->getTotalItemCount(),
			'aaData'				=> $result,
			'keys'					=> $this->getKeys(),
		);
	}

	/**
	 * Taken from CakePHP, HttpSocket.php
	 * @param mixed $query A query string to parse into an array or an array to return directly "as is"
	 * @return array The $query parsed into a possibly multi-level array. If an empty $query is
	 * given, an empty array is returned.
	 */
	public static function parseQuery($query) {
		if (is_array($query)) {
			return $query;
		}

		if (is_array($query)) {
			return $query;
		}
		$parsedQuery = array();

		if (is_string($query) && !empty($query)) {
			$query = preg_replace('/^\?/', '', $query);
			$items = explode('&', $query);

			foreach ($items as $item) {
				if (strpos($item, '=') !== false) {
					list($key, $value) = explode('=', $item, 2);
				} else {
					$key = $item;
					$value = null;
				}

				$key = urldecode($key);
				$value = urldecode($value);

				if (preg_match_all('/\[([^\[\]]*)\]/iUs', $key, $matches)) {
					$subKeys = $matches[1];
					$rootKey = substr($key, 0, strpos($key, '['));
					if (!empty($rootKey)) {
						array_unshift($subKeys, $rootKey);
					}
					$queryNode =& $parsedQuery;

					foreach ($subKeys as $subKey) {
						if (!is_array($queryNode)) {
							$queryNode = array();
						}

						if ($subKey === '') {
							$queryNode[] = array();
							end($queryNode);
							$subKey = key($queryNode);
						}
						$queryNode =& $queryNode[$subKey];
					}
					$queryNode = $value;
					continue;
				}
				if (!isset($parsedQuery[$key])) {
					$parsedQuery[$key] = $value;
				} else {
					$parsedQuery[$key] = (array)$parsedQuery[$key];
					$parsedQuery[$key][] = $value;
				}
			}
		}
		return $parsedQuery;
	}
}
