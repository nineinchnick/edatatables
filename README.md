Goal of this widget/wrapper is to provide a drop in replacement for base CGridView widget from the [Yii framework](http://yiiframework.com), using [DataTables](http://datatables.net) plugin.

It's usable, but feedback is needed. Please post issues on [project's page](https://github.com/nineinchnick/edatatables/issues).

##Features

* Redrawing of table contents (after paging/sorting/searching) using AJAX calls;
* Using CGridView columns definition format, supports all basic special columns like Buttons, Checkbox, etc;
* Custom buttons in table header;
* Smoothness theme from JUI by default;
* Twitter Bootstrap support through the [bootstrap extension](http://www.yiiframework.com/extension/bootstrap);
* Partial editable cells support.

##Requirements

* Yii 1.1.8 or above;
* PHP 5.3;
* (optional) [Bootstrap extension](http://www.yiiframework.com/extension/bootstrap).

##Usage

It's not 100% compatible with CGridView. I've decided not to alter the GET parameter names used by DataTables, so you have to use the provided EDTSort and EDTPagination classes as well as alter filter processing. See below.

###Installation

Extract into extensions dir, or use composer: `$ composer require nineinchnick/edatatables:dev-master`

Import in config/main.php

```php
'import' => array(
        ...
        'ext.edatatables.*', //if it's in your extensions folder
        'vendor.nineinchnick.edatatables.*', //if you're using composer (and have a 'vendor' alias!)
        ...
)
```

###Using

Use similar to CGridView. If displayed in a normal call just run the widget. To fetch AJAX response send json encoded result of $widget->getFormattedData().

The action in a controller:
```php
$widget = $this->createWidget('ext.edatatables.EDataTables', array(
 'id'            => 'products',
 'dataProvider'  => $dataProvider,
 'ajaxUrl'       => $this->createUrl('/products/index'),
 'columns'       => $columns,
));
if (!Yii::app()->getRequest()->getIsAjaxRequest()) {
  $this->render('index', array('widget' => $widget,));
  return;
} else {
  echo json_encode($widget->getFormattedData(intval($_REQUEST['sEcho'])));
  Yii::app()->end();
}
```

The index view (for non-ajax requests):
```php
<?php $widget->run(); ?>
```

###Preparing the dataprovider

To use features like sorting, pagination and filtering (by quick search field in the toolbar or a custom advanced search filter form) the dataprovider object passed to the widget must be prepared using provided EDTSort and EDTPagination class and CDbCriteria filled after parsing sent forms.

The simplest example:
```php
$criteria = new CDbCriteria;
// bro-tip: $_REQUEST is like $_GET and $_POST combined
if (isset($_REQUEST['sSearch']) && isset($_REQUEST['sSearch']{0})) {
    // use operator ILIKE if using PostgreSQL to get case insensitive search
    $criteria->addSearchCondition('textColumn', $_REQUEST['sSearch'], true, 'AND', 'ILIKE');
}

$sort = new EDTSort('ModelClass', $sortableColumnNamesArray);
$sort->defaultOrder = 'id';
$pagination = new EDTPagination();

$dataProvider = new CActiveDataProvider('ModelClass', array(
    'criteria'      => $criteria,
    'pagination'    => $pagination,
    'sort'          => $sort,
))
```

An advanced example would be based on a search form defined with a model and a view. Its attributes would be then put into a critieria and passed to a dataProvider. 

###Other options

Check out the [DataTables web page](http://datatables.net) for docs regarding:

* Table layout
* Styling
* Multi-column sorting etc.
* Some examples and funky plugins

###Using with Twitter Bootstrap

Since the _bootstrap_ attribute has been removed, please use the following configuration in the widget factory or as a default skin for your bootstrap theme:

```php
'EDataTables' => array(
    'htmlOptions' => array('class' => ''),
    'itemsCssClass' => 'table table-striped table-bordered table-condensed items',
    'pagerCssClass' => 'paging_bootstrap pagination',
    'buttons' => array(
        'refresh' => array(
            'tagName' => 'a',
            'label' => '<i class="icon-refresh"></i>',
            'htmlClass' => 'btn',
            'htmlOptions' => array('rel' => 'tooltip', 'title' => Yii::t('EDataTables.edt',"Refresh")),
            'init' => 'js:function(){}',
            'callback' => 'js:function(e){e.data.that.eDataTables("refresh"); return false;}',
        ),
    ),
    'datatableTemplate' => "<><'row'<'span3'l><'dataTables_toolbar'><'pull-right'f>r>t<'row'<'span3'i><'pull-right'p>>",
    'registerJUI' => false,
    'options' => array(
        'bJQueryUI' => false,
        'sPaginationType' => 'bootstrap',
        //'fnDrawCallbackCustom' => "js:function(){\$('a[rel=tooltip]').tooltip(); \$('a[rel=popover]').popover();}",
    ),
    'cssFiles' => array('bootstrap.dataTables.css'),
    'jsFiles' => array(
        'bootstrap.dataTables.js',
        'jdatatable.js' => CClientScript::POS_END,
    ),
),
```

##Resources

 * GitHub: [https://github.com/nineinchnick/edatatables](https://github.com/nineinchnick/edatatables)
 * Composer package named [nineinchnick/edatatables](https://packagist.org/packages/nineinchnick/edatatables)
