<?php

/**
 * EDataTables class file.
 *
 * @author Jan Was <janek.jan@gmail.com>
 *
 * @link http://www.yiiframework.com/
 *
 * @copyright Copyright &copy; 2011-2012 Jan Was
 * @license http://www.yiiframework.com/license/
 */
Yii::import('zii.widgets.grid.CGridView');

/**
 * EDataTables does the same thing as CGridView, but using the datatables.net control.
 *
 * @author Jan Was <jwas@nets.com.pl>
 */
class EDataTables extends CGridView
{
    const SESSION_KEY = 'edatatables';

    // reset the template, pagination and other elements are drawn by dataTables JS plugin
    public $template = '{items}';
    /**
     * @var string Form selector. If set it tries to map columns to fields in that form,
     * and if found it uses their values for filtering.
     */
    public $filterForm = null;

    /**
     * @var array Additional key/value pairs to be sent to the server data backend.
     *            If the value string starts with 'js:' it will be included
     *            as a callback, without quotes.
     */
    public $serverData = array();
    /**
     * @var string The text to be displayed in a data cell when a data value is null.
     * This property will NOT be HTML-encoded when rendering. Defaults to an HTML blank.
     */
    public $nullDisplay = null;

    /**
     * @var string template used by dataTables
     */
    public $datatableTemplate = "<><'H'l<'dataTables_toolbar'>fr>t<'F'ip>";
    public $selectionChanged;
    /**
     * @var bool If change in editable columns selects current row.
     * Useful in relation tables, where only selected rows are saved.
     *           When rows are selected for removal, this should be false.
     */
    public $editableSelectsRow = true;
    /**
     * @var bool Should the toolbar contain a checkbox for filtering data to only related (and selected).
     */
    public $relatedOnlyOption = false;

    public $options = array();
    /**
     * Contains keys:
     * 'all-selected' - empty string (defalut) or one of:
     * 'select'       - select all records except 'deselected' and 'disconnect'
     * 'deselect'     - deselect all records except 'selected'
     * 'selected'     - comma separated string with selected ids (empty if 'all-selected' == 'select')
     * 'deselected'   - comma separated string with deselected ids (empty if 'all-selected' != 'select')
     *                  contains ids which WASN'T initially selected (before eDataTables instance initialized)
     * 'disconnect'   - comma separated string with deselected ids (empty if 'all-selected' != 'select')
     *                  contains ids which WAS initially selected (before eDataTables instance initialized).
     *
     * @var array Array of unsaved changes, must be filled when redrawing a form
     * containing this control after an unsuccessful save (failed validation).
     */
    public $unsavedChanges = array(
        'all-selected' => '',
        'selected'     => '',
        'deselected'   => '',
        'disconnect'   => '',
        'values'       => '',
    );
    /**
     * @var array If null, disables all default buttons.
     * If an array, will be merged with default refresh button.
     */
    public $buttons = array();

    /**
     * @var string The CSS class name for the container of all data item display.
     * Defaults to 'items'.
     */
    public $itemsCssClass = 'display items';

    /**
     * @var bool If true, toolbar will contain a multiselect to choose visible columns
     * and column headers will be sortable.
     */
    public $configurable = false;

    /**
     * @var array List of CSS files to register, if any contains a slash (/),
     * assets dir will not be prepended to it.
     */
    public $cssFiles = array(
        'demo_table_jui.css',
        'jquery.dataTables_themeroller.css',
        'jquery-ui-1.8.17.custom.css',
    );

    /**
     * @var array List of JS files to register, if any contains a slash (/),
     * assets dir will not be prepended to it. When filename is the key,
     * value must be one of CClientScript::POS_* constants.
     */
    public $jsFiles = array(
        'jdatatable.js' => CClientScript::POS_END,
    );

    /**
     * @var bool Should the jquery.ui core script be registered,
     * it could be required for toolbar buttons .
     */
    public $registerJUI = true;

    public function init()
    {
        // check if a cookie exist holding some options and explode it into GET
        // must be done before parent::init(), because it calls initColumns
        // and it calls dataProvider->getData(), not done if options are passed through GET/POST
        if (isset($this->options['bStateSave']) && $this->options['bStateSave']) {
            $cookiePrefix = isset($this->options['sCookiePrefix']) ? $this->options['sCookiePrefix'] : 'edt_';
            self::restoreState($this->getId(), $cookiePrefix);
        }
        parent::init();
        /*
         * @todo apparently CGridView wasn't meant to be inherited from
         */
        $assetsPath = dirname(__FILE__).DIRECTORY_SEPARATOR.'assets';
        $this->baseScriptUrl = Yii::app()->getAssetManager()->publish($assetsPath);
    }

    /**
     * If bStateSave was set when initializing widget it saves sorting
     * and pagination into cookies. This method should be called
     * before preparing a dataProvider, as it uses CSort which read $_GET.
     */
    public static function restoreState($id, $prefix = 'edt_')
    {
        if (!isset($_COOKIE) || isset($_GET['iSortingCols'])) {
            return;
        }

        foreach ($_COOKIE as $key => $value) {
            if (strpos($key, $prefix) !== 0 && substr($key, -strlen($id)) !== $id) {
                continue;
            }
            $options = json_decode($value, true);
            // for now, extract only sorting information
            if (isset($options['aaSorting']) && is_array($options['aaSorting'])) {
                $i = 0;
                foreach ($options['aaSorting'] as $sort) {
                    if (!isset($sort[0]) || !isset($sort[1])) {
                        continue;
                    }
                    $_GET['iSortCol_'.$i] = $sort[0];
                    $_GET['sSortDir_'.$i++] = $sort[1];
                }
                $_GET['iSortingCols'] = $i;
            }
        }
    }

    public static function restoreStateSession($id, $sort, $pagination, &$columns)
    {
        $session = Yii::app()->session[self::SESSION_KEY];
        if ($sort === null) {
            $sort = new EDTSort();
        }
        if ($pagination === null) {
            $pagination = new EDTPagination();
        }
        if (isset($_GET[$sort->sortVar]) && ($iSortingCols = intval($_GET[$sort->sortVar])) > 0) {
            // save state
            $sortParams = array(
                'count'   => $iSortingCols,
                'indexes' => array(),
                'dirs'    => array(),
            );
            for ($i = 0; $i < $iSortingCols && isset($_GET[$sort->sortVarIdxPrefix.$i]); ++$i) {
                $sortParams['indexes'][$sort->sortVarIdxPrefix.$i] = $_GET[$sort->sortVarIdxPrefix.$i];
                if (isset($_GET[$sort->sortVarDirPrefix.$i])) {
                    $sortParams['dirs'][$sort->sortVarDirPrefix.$i] = $_GET[$sort->sortVarDirPrefix.$i];
                }
            }
            $session[$id]['sort'] = $sortParams;
        } elseif (isset($session[$id]) && isset($session[$id]['sort'])) {
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
                'page'   => $_GET[$pagination->pageVar],
            );
        } elseif (isset($session[$id]) && isset($session[$id]['pagination'])) {
            // restore state
            $_GET[$pagination->lengthVar] = $session[$id]['pagination']['length'];
            $_GET[$pagination->pageVar] = $session[$id]['pagination']['page'];
        }
        if (isset($_GET['sSearch'])) {
            $session[$id]['search'] = $_GET['sSearch'];
        } elseif (isset($session[$id]) && isset($session[$id]['search'])) {
            $_GET['sSearch'] = $session[$id]['search'];
        }
        $orderedColumnNames = null;
        $visibleColumnIndexes = null;
        if (isset($_GET['sColumns']) && ($o = trim($_GET['sColumns'], ', ')) !== '') {
            $orderedColumnNames = array_flip(explode(',', $o));
            if (isset($_GET['visibleColumns']) && ($v = trim($_GET['visibleColumns'], ', ')) !== '') {
                $visibleColumnIndexes = array_flip(explode(',', $v));
            }
            $session[$id]['orderedColumnNames'] = $orderedColumnNames;
            $session[$id]['visibleColumnIndexes'] = $visibleColumnIndexes;
        } elseif (isset($session[$id]) && isset($session[$id]['orderedColumnNames'])) {
            $orderedColumnNames = $session[$id]['orderedColumnNames'];
            $visibleColumnIndexes = $session[$id]['visibleColumnIndexes'];
        }
        if ($orderedColumnNames !== null) {
            $newOrder = array();
            foreach ($columns as $key => $column) {
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
                        if (isset($column['visible'])) {
                            $columns[$key]['visible'] = true;
                        }
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
        Yii::app()->session[self::SESSION_KEY] = $session;
    }

    /**
     * Creates column objects and initializes them.
     */
    protected function initColumns()
    {
        if ($this->columns === array()) {
            if ($this->dataProvider instanceof CActiveDataProvider) {
                $this->columns = $this->dataProvider->model->attributeNames();
            } elseif ($this->dataProvider instanceof IDataProvider) {
                // use the keys of the first row of data as the default columns
                $data = $this->dataProvider->getData();
                if (isset($data[0]) && is_array($data[0])) {
                    $this->columns = array_keys($data[0]);
                }
            }
        }
        foreach ($this->columns as $i => $column) {
            if (is_string($column)) {
                $column = $this->createDataColumn($column);
            } else {
                if (!isset($column['class'])) {
                    $column['class'] = 'EDataColumn';
                }
                $column = Yii::createComponent($column, $this);
            }
            if ($column->id === null) {
                $column->id = $this->getId().'_c'.$i;
            }
            $numericTypes = array('dec2', 'dec3', 'dec5', 'integer', 'number');
            if (isset($column->type) && in_array($column->type, $numericTypes)) {
                // all numeric types gets aligned right by default
                //if (!isset($column->headerHtmlOptions['class']))
                // $column->headerHtmlOptions['class'] = 'text-right';
                if (!isset($column->htmlOptions['class'])) {
                    $column->htmlOptions['class'] = 'text-right';
                }
            }
            $this->columns[$i] = $column;
        }

        foreach ($this->columns as $column) {
            $column->init();
        }
    }

    /**
     * Creates an array based on a shortcut column specification string.
     *
     * @param string $text the column specification string
     *
     * @return array the column specification array
     */
    protected static function createArrayColumn($text)
    {
        if (!preg_match('/^([\w\.]+)(:(\w*))?(:(.*))?$/', $text, $matches)) {
            $message = Yii::t('zii', 'The column must be specified in the format of "Name:Type:Label", where "Type" and "Label" are optional.');
            throw new CException($message);
        }
        $column = array();
        $column['name'] = $matches[1];
        if (isset($matches[3]) && $matches[3] !== '') {
            $column['type'] = $matches[3];
        }
        if (isset($matches[5])) {
            $column['header'] = $matches[5];
        }

        return $column;
    }

    /**
     * Creates a {@link CDataColumn} based on a shortcut column specification string.
     *
     * @param string $text the column specification string
     *
     * @return CDataColumn the column instance
     */
    protected function createDataColumn($text)
    {
        if (!preg_match('/^([\w\.]+)(:(\w*))?(:(.*))?$/', $text, $matches)) {
            throw new CException(Yii::t('zii', 'The column must be specified in the format of "Name:Type:Label", where "Type" and "Label" are optional.'));
        }
        $column = new EDataColumn($this);
        $column->name = $matches[1];
        if (isset($matches[3]) && $matches[3] !== '') {
            $column->type = $matches[3];
        }
        if (isset($matches[5])) {
            $column->header = $matches[5];
        }

        return $column;
    }

    protected function initColumnsJS()
    {
        $columnDefs = array();
        if (isset($this->editableColumns) && !empty($this->editableColumns)) {
            $id = $this->getId();
            $script = <<<JavaScript
function(oObj) {
    return $('#{$id}').eDataTables('renderEditableCell', '{$id}', oObj);
}
JavaScript;
            $columnDefs[] = array(
                'fnRender' => "js:$script",
                'aTargets' => $this->editableColumns,
            );
        }
        $sort = $this->dataProvider->getSort();
        $defaultOrder = array();
        if ($sort instanceof CSort) {
            $defaultOrder = $sort->getDirections();
        }
        $hiddenColumns = array();
        $nonsortableColumns = array();
        $sortedColumns = array(
            'asc'  => array(),
            'desc' => array(),
            'all'  => array(),
        );
        $cssClasses = array();
        $groupColumns = array();
        foreach ($this->columns as $i => $column) {
            $hasName = property_exists($column, 'name') && trim($column->name) !== '';
            $sName = $hasName ? $column->name : 'unnamed'.$i;
            $columnDefs[] = array(
                'sName'    => $sName,
                'aTargets' => array($i),
            );
            if (!$column->visible) {
                $hiddenColumns[] = $i;
            }
            if (!$this->enableSorting || !$hasName || !property_exists($column, 'sortable')
                || !$column->sortable || $this->dataProvider->getSort()->resolveAttribute($column->name) === false
            ) {
                $nonsortableColumns[] = $i;
            } elseif (isset($defaultOrder[$column->name])) {
                $sortedColumns[$defaultOrder[$column->name] == CSort::SORT_ASC ? 'asc' : 'desc'][] = $i;
                $sortedColumns['all'][] = array(
                    $i,
                    $defaultOrder[$column->name] == CSort::SORT_ASC ? 'asc' : 'desc'
                );
            }
            if (isset($column->htmlOptions) && isset($column->htmlOptions['class'])) {
                if (!isset($cssClasses[$column->htmlOptions['class']])) {
                    $cssClasses[$column->htmlOptions['class']] = array();
                }
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
        } elseif (!isset($this->options['aaSorting'])) {
            $this->options['aaSorting'] = array();
        }
        if (!empty($hiddenColumns)) {
            $columnDefs[] = array(
                'bVisible' => false,
                'aTargets' => $hiddenColumns,
            );
        }
        if (!empty($nonsortableColumns)) {
            $columnDefs[] = array(
                'bSortable' => false,
                'aTargets'  => $nonsortableColumns,
            );
        }
        if (!empty($cssClasses)) {
            foreach ($cssClasses as $cssClass => $targets) {
                $columnDefs[] = array(
                    'sClass'   => $cssClass,
                    'aTargets' => $targets,
                );
            }
        }
        if (isset($this->editableColumns) && !empty($this->editableColumns)) {
            /*
             * @todo allow null values where permitted by column definition
             * (tri-state checkbox, two checkboxes or three radios)
             */
        }

        return $columnDefs;
    }

    public function initToolbarButtons()
    {
        if ($this->buttons === null) {
            return array();
        }

        return array_merge(array(
            'refresh' => array(
                'tagName'   => 'button',
                'label'     => Yii::t('EDataTables.edt', 'Refresh'),
                'htmlClass' => '',
                'text'      => false,
                'icon'      => 'ui-icon-refresh',
                'callback'  => 'js:function(e){e.data.that.eDataTables("refresh"); return false;}',
            ),
        ), $this->buttons);
    }

    /**
     * Registers necessary client scripts.
     */
    public function registerClientScript()
    {
        $id = $this->getId();

        //filter out non visible buttons
        $buttons = array_filter(
            $this->initToolbarButtons(),
            function ($btn) {
                return !isset($btn['visible']) || $btn['visible'] != false;
            }
        );

        $columnDefs = $this->initColumnsJS();
        if (isset($this->options['aoColumnDefs'])) {
            $this->options['aoColumnDefs'] = array_merge($columnDefs, $this->options['aoColumnDefs']);
        }
        $defaultOptions = array(
            'baseUrl'           => CJSON::encode(Yii::app()->baseUrl),
            // options inherited from CGridView JS scripts
            'ajaxUpdate'        => $this->ajaxUpdate === false ? false : array_unique(preg_split('/\s*,\s*/', $this->ajaxUpdate.','.$id, -1, PREG_SPLIT_NO_EMPTY)),
            'ajaxOpts'          => array_merge(array($this->ajaxVar => $this->getId()), $this->serverData),
            'pagerClass'        => $this->pagerCssClass,
            'loadingClass'      => $this->loadingCssClass,
            'filterClass'       => $this->filterCssClass,
            //'tableClass'      => $this->itemsCssClass,
            'selectableRows'    => $this->selectableRows,
            'editableSelectsRow'=> $this->editableSelectsRow,
            // dataTables options
            'asStripClasses'    => $this->rowCssClass,
            'iDeferLoading'     => ($totalCount = $this->dataProvider->getTotalItemCount()) ? $totalCount : false,
            'sAjaxSource'       => CHtml::normalizeUrl($this->ajaxUrl),
            'aoColumnDefs'      => $columnDefs,
            'bScrollCollapse'   => false,
            'bStateSave'        => false,
            'bPaginate'         => true,
            'sCookiePrefix'     => 'edt_',
            'relatedOnlyLabel'  => Yii::t('EDataTables.edt', 'Only related'),
            'columnsListLabel'  => Yii::t('EDataTables.edt', 'Columns'),
            'buttons'           => $buttons,
        );
        if (Yii::app()->getLanguage() !== 'en_us') {
            // those are the defaults in the DataTables plugin JS source,
            // we don't need to set them if app language is already en_us
            $oLanguage = array(
                'oAria' => array(
                    'sSortAscending'  => Yii::t('EDataTables.edt', ': activate to sort column ascending'),
                    'sSortDescending' => Yii::t('EDataTables.edt', ': activate to sort column descending'),
                ),
                'oPaginate' => array(
                    'sFirst'    => Yii::t('EDataTables.edt', 'First'),
                    'sLast'     => Yii::t('EDataTables.edt', 'Last'),
                    'sNext'     => Yii::t('EDataTables.edt', 'Next'),
                    'sPrevious' => Yii::t('EDataTables.edt', 'Previous'),
                ),
                'sEmptyTable'      => Yii::t('EDataTables.edt', 'No data available in table'),
                'sInfo'            => Yii::t('EDataTables.edt', 'Showing _START_ to _END_ of _TOTAL_ entries'),
                'sInfoEmpty'       => Yii::t('EDataTables.edt', 'Showing 0 to 0 of 0 entries'),
                'sInfoFiltered'    => Yii::t('EDataTables.edt', '(filtered from _MAX_ total entries)'),
                //"sInfoPostFix"   => "",
                //"sInfoThousands" => ",",
                'sLengthMenu'      => Yii::t('EDataTables.edt', 'Show _MENU_ entries'),
                'sLoadingRecords'  => Yii::t('EDataTables.edt', 'Loading...'),
                'sProcessing'      => Yii::t('EDataTables.edt', 'Processing...'),
                'sSearch'          => Yii::t('EDataTables.edt', 'Search:'),
                //"sUrl"           => "",
                'sZeroRecords'     => Yii::t('EDataTables.edt', 'No matching records found'),
            );
            $localeSettings = localeconv();
            if (!empty($localeSettings['decimal_point'])) {
                $oLanguage['sInfoThousands'] = $localeSettings['decimal_point'];
            }
            $this->options['oLanguage'] = array_merge(
                $oLanguage,
                !isset($this->options['oLanguage']) ? array() : $this->options['oLanguage']
            );
        }
        $options = array_merge($defaultOptions, $this->options);
        if ($this->ajaxUrl !== null) {
            $options['url'] = CHtml::normalizeUrl($this->ajaxUrl);
        }
        if ($this->updateSelector !== null) {
            $options['updateSelector'] = $this->updateSelector;
        }
        if ($this->enablePagination) {
            $pagination = $this->dataProvider->getPagination();
            if ($pagination instanceof EDTPagination) {
                $options[$pagination->pageVar] = $pagination->getCurrentPage() * $pagination->getPageSize();
                $options[$pagination->lengthVar] = $pagination->getPageSize();
            }
        }
        if ($this->datatableTemplate) {
            $options['sDom'] = $this->datatableTemplate;
        }
        if ($this->beforeAjaxUpdate !== null) {
            $options['beforeAjaxUpdate'] = (strpos($this->beforeAjaxUpdate, 'js:') !== 0 ? 'js:' : '').$this->beforeAjaxUpdate;
        }
        if ($this->afterAjaxUpdate !== null) {
            $options['afterAjaxUpdate'] = (strpos($this->afterAjaxUpdate, 'js:') !== 0 ? 'js:' : '').$this->afterAjaxUpdate;
        }
        if ($this->ajaxUpdateError !== null) {
            $options['ajaxUpdateError'] = (strpos($this->ajaxUpdateError, 'js:') !== 0 ? 'js:' : '').$this->ajaxUpdateError;
        }
        if ($this->selectionChanged !== null) {
            $options['selectionChanged'] = (strpos($this->selectionChanged, 'js:') !== 0 ? 'js:' : '').$this->selectionChanged;
        }
        if ($this->relatedOnlyOption) {
            $options['relatedOnlyOption'] = $this->relatedOnlyOption;
        }
        if ($this->filterForm) {
            $options['filterForm'] = $this->filterForm;
        }
        if (isset($_GET['sSearch'])) {
            $options['oSearch'] = array('sSearch' => $_GET['sSearch']);
        }
        if ($this->configurable) {
            $options['configurable'] = true;
            // block draggable column headers
            $options['oColReorder'] = array(
                //'iFixedColumns'   => count($this->columns)
                'fnReorderCallback' => "js:function(){\$('#{$id}').eDataTables('refresh');}",
            );
            $options['sDom'] .= 'R';
        }

        /*
         * unserialize unsaved data into JS data structures, ready to be binded to DOM elements through .data()
         */
        $values = self::parseQuery($this->unsavedChanges['values']);
        $us = trim($this->unsavedChanges['selected'], ',');
        $udeselected = trim($this->unsavedChanges['deselected'], ',');
        $ud = trim($this->unsavedChanges['disconnect'], ',');
        $options['unsavedChanges'] = array(
            'all_selected' => $this->unsavedChanges['all-selected'],
            'selected'     => !empty($us) ? array_fill_keys(explode(',', $us), true) : array(),
            'deselected'   => !empty($udeselected) ? array_fill_keys(explode(',', $udeselected), true) : array(),
            'disconnect'   => !empty($ud) ? array_fill_keys(explode(',', $ud), true) : array(),
            'values'       => $values,
        );

        if (isset($options['fnServerParams'])) {
            throw new CException(Yii::t('EDataTables.edt', 'fnServerParams option is reserved and cannot be set. Use the serverParams property instead.'));
        }
        $options['fnServerParams'] = "js:function(aoData){return $('#{$id}').eDataTables('serverParams', aoData);}";
        if (!isset($options['fnServerData'])) {
            $options['fnServerData'] = "js:function(sSource, aoData, fnCallback){return $('#{$id}').eDataTables('serverData', sSource, aoData, fnCallback);}";
        }

        self::initClientScript($this->cssFiles, $this->jsFiles, $this->configurable, $this->registerJUI);
        $options = CJavaScript::encode($options);
        Yii::app()->getClientScript()->registerScript(__CLASS__.'#'.$id, "jQuery('#$id').eDataTables($options);");
    }

    public static function initClientScript($cssFiles, $jsFiles, $configurable = false, $registerJUI = true)
    {
        $ds = DIRECTORY_SEPARATOR;
        $baseScriptUrl = Yii::app()->getAssetManager()->publish(dirname(__FILE__).$ds.'assets');
        $vendorScriptUrl = Yii::app()->getAssetManager()->publish(dirname(__FILE__)."{$ds}..{$ds}..{$ds}datatables{$ds}datatables{$ds}media");

        $cs = Yii::app()->getClientScript();
        $cs->registerCoreScript('jquery');
        foreach ($cssFiles as $cssFile) {
            $cs->registerCssFile((strpos($cssFile, '/') === false ? $baseScriptUrl.'/css/' : '').$cssFile);
        }
        $cs->registerScriptFile($vendorScriptUrl.'/js/jquery.dataTables'.(YII_DEBUG ? '' : '.min').'.js');
        if ($configurable || $registerJUI) {
            $cs->registerCoreScript('jquery.ui');
        }
        if ($configurable) {
            $cs->registerScriptFile($baseScriptUrl.'/js/dataTables.colReorder'.(YII_DEBUG ? '' : '.min').'.js');
        }
        foreach ($jsFiles as $key => $value) {
            if (is_numeric($key)) {
                $jsFile = $value;
                $position = $cs->defaultScriptFilePosition;
            } else {
                $jsFile = $key;
                $position = $value;
            }
            $cs->registerScriptFile((strpos($jsFile, '/') === false ? $baseScriptUrl.'/js/' : '').$jsFile, $position);
        }
    }

    /**
     * Renders the table body.
     */
    public function renderTableBody()
    {
        $data = $this->dataProvider->getData();
        $n = count($data);
        echo "<tbody>\n";

        // unlike in CGridView, here we don't render a special row when table is empty - it breaks the datatables
        for ($row = 0;$row < $n;++$row) {
            $this->renderTableRow($row);
        }
        echo "</tbody>\n";
    }

    /**
     * This method should be used instead of getKeys from the dataProvider.
     */
    protected function getKeys()
    {
        $keys = array();
        //! @todo we don't use getKeys() here which caches keys in _keys property -> check consequences
        $hasKeyAttribute = property_exists(get_class($this->dataProvider), 'keyAttribute')
            || property_exists(get_class($this->dataProvider), 'keyField');
        $keyAttribute = null;
        if ($hasKeyAttribute) {
            $keyAttribute = property_exists(get_class($this->dataProvider), 'keyAttribute') ? 'keyAttribute' : 'keyField';
        }
        foreach ($this->dataProvider->getData() as $i => $data) {
            // check dataProvider compatibility
            if (!$hasKeyAttribute) {
                continue;
            }
            if ($hasKeyAttribute) {
                if ($keyAttribute === 'keyField') {
                    $keyAttribute = $this->dataProvider->keyField;

                    if ($keyAttribute) {
                        if (is_array($data) && isset($data[$keyAttribute])) {
                            $key = $data[$keyAttribute];
                        } elseif (is_object($data) && isset($data->{$keyAttribute})) {
                            $key = $data->{$keyAttribute};
                        } else {
                            continue;
                        }
                    } else {
                        if (is_array($data) && !empty($data)) {
                            $key = 0;
                        }
                    }
                } elseif ($keyAttribute !== null && !empty($this->dataProvider->$keyAttribute)) {
                    $key = $data->{$this->dataProvider->$keyAttribute};
                } elseif (method_exists($data, 'getPrimaryKey')) {
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
            $key = is_array($key) ? implode('-', $key) : $key;
            $keys[] = $key;
        }

        return $keys;
    }

    public function renderKeys()
    {
        // base class code + choosing keys
        echo CHtml::openTag('div', array(
            'class' => 'keys',
            'style' => 'display:none',
            'title' => Yii::app()->getRequest()->getUrl(),
        ));
        //! @todo we don't use getKeys() here which caches keys in _keys property -> check consequences
        foreach ($this->getKeys() as $key) {
            echo '<span>'.CHtml::encode($key).'</span>';
        }
        echo "</div>\n";
        // extra code
        if ($this->selectableRows) {
            $specialFields = array('all-selected', 'selected', 'deselected', 'disconnect');
            foreach ($specialFields as $field) {
                if (!isset($this->unsavedChanges[$field])) {
                    continue;
                }
                echo CHtml::hiddenField(
                    $this->getId().'-'.$field,
                    $this->unsavedChanges[$field],
                    array('id' => $this->getId().'-'.$field)
                );
            }
        }
        if (isset($this->unsavedChanges['values'])) {
            echo CHtml::hiddenField(
                $this->getId().'-values',
                $this->unsavedChanges['values'],
                array('id' => $this->getId().'-values')
            );
        }
    }

    /**
     * Returns formatted dataset from dataProvider in an array
     * instead of rendering a HTML table. @see renderTableBody.
     *
     * @param int $sEcho
     *
     * @return array
     */
    public function getFormattedData($sEcho)
    {
        $result = array();

        $rowsCount = count($this->dataProvider->getData());
        for ($row = 0; $row < $rowsCount; ++$row) {
            $currentRow = array();
            foreach ($this->columns as $column) {
                $currentRow[] = $column->getDataCellContent($row);
            }
            $result[$row] = $currentRow;
        }

        return array(
            'sEcho'                => $sEcho,
            'iTotalRecords'        => $this->dataProvider->getTotalItemCount(),
            'iTotalDisplayRecords' => $this->dataProvider->getTotalItemCount(),
            'aaData'               => $result,
            'keys'                 => $this->getKeys(),
        );
    }

    /**
     * Taken from CakePHP, HttpSocket.php.
     *
     * @param mixed $query A query string to parse into an array or an array to return directly "as is"
     *
     * @return array The $query parsed into a possibly multi-level array. If an empty $query is
     *               given, an empty array is returned.
     */
    public static function parseQuery($query)
    {
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
                    $queryNode = &$parsedQuery;

                    foreach ($subKeys as $subKey) {
                        if (!is_array($queryNode)) {
                            $queryNode = array();
                        }

                        if ($subKey === '') {
                            $queryNode[] = array();
                            end($queryNode);
                            $subKey = key($queryNode);
                        }
                        $queryNode = &$queryNode[$subKey];
                    }
                    $queryNode = $value;
                    continue;
                }
                if (!isset($parsedQuery[$key])) {
                    $parsedQuery[$key] = $value;
                } else {
                    $parsedQuery[$key] = (array) $parsedQuery[$key];
                    $parsedQuery[$key][] = $value;
                }
            }
        }

        return $parsedQuery;
    }
}
