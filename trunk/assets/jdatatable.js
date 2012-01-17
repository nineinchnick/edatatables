/**
 * jQuery Yii DataTables plugin file.
 *
 * @author Jan Was <jwas@nets.com.pl>
 * @copyright Copyright &copy; 2008-2010 NET Sp. z o. o.
 * @license http://www.yiiframework.com/license/
 */

(function($) {
	var methods = {
		init: function (options) {
			var settings = $.extend({}, $.fn.cDataTable.defaults, options || {});
			var $this = $(this);
			var id = $this.attr('id');
			$.fn.cDataTable.settings[id] = settings;

			$.fn.cDataTable.selectCheckedRows(id);

			$('#'+id+'-selected').data('list',settings.unsavedChanges.selected.length == 0 ? {} : settings.unsavedChanges.selected);
			$('#'+id+'-deselected').data('list',settings.unsavedChanges.deselected.length == 0 ? {} : settings.unsavedChanges.deselected);
			$('#'+id+'-values').data('list',settings.unsavedChanges.values.length == 0 ? {} : settings.unsavedChanges.values);

			var selectRowFromTr = function(row) {
				if(settings.selectableRows == 1)
					$(row).siblings().removeClass('selected');

				var isRowSelected=$(row).toggleClass('selected').hasClass('selected');
				$('input.select-on-check',row).each(function(){
					if(settings.selectableRows == 1){
						$("input[name='"+row.name+"']").attr('checked',false);
					}
					
					this.checked=isRowSelected;
					var sboxallname=this.name.substring(0,this.name.length-2)+'_all';	//.. remove '[]' and add '_all'
					$("input[name='"+sboxallname+"']").attr('checked', $("input[name='"+this.name+"']").length==$("input[name='"+this.name+"']:checked").length);
					$.fn.cDataTable.select(id, this);
				});
				if(settings.selectionChanged !== undefined)
					settings.selectionChanged(id);
			};
			if(settings.selectableRows > 0) {
				//$('#'+id+' .'+settings.tableClass+' > tbody > tr').die('click').live('click',function(e){
				$('#'+id+' .'+settings.tableClass+' > tbody').undelegate('tr','click').delegate('tr','click',function(e){
					if($(e.target).is('input') || $(e.target).is('a')){
						return;
					}
					selectRowFromTr(this);
				});
			}

			//$('#'+id+' .'+settings.tableClass+' > tbody > tr input.editable').die('change').live('change',function(e){
			$('#'+id+' .'+settings.tableClass+' > tbody').undelegate('input.editable','change').delegate('input.editable','change',function(e){
				// if this is a not empty textbox and row is not selected select it
				if (!$(this).parent().parent().hasClass('selected') && (e.target.type != 'text' || $(e.target).val() != '')) {
					selectRowFromTr($(this).parent().parent()[0]);
				}

				var input_values = $('#'+id+'-values');
				var list_values = input_values.data('list');
				var row_id = $.fn.cDataTable.getKey(id, $(e.target).parent().parent().index());
				var index = $(e.target).attr('id').substr(0,$(e.target).attr('id').indexOf('_row'));
				
				if (typeof list_values == 'undefined') list_values = {};
				if (typeof list_values[row_id] == 'undefined') list_values[row_id] = {};
				var value;
				switch(e.target.type) {
					default:
					case 'text': value = $(e.target).val(); break;
					case 'checkbox': value = $(e.target).attr('checked') ? true : false; break;
				}
				list_values[row_id][index] = value;
				
				input_values.val($.param(list_values)).data('list',list_values);
				return false;
			});

			//$('#'+id+' .'+settings.tableClass+' > tbody > tr > td > input.select-on-check').die('click').live('click',function(){
			$('#'+id+' .'+settings.tableClass+' > tbody').undelegate('input.select-on-check','click').delegate('input.select-on-check','click',function(){
				if(settings.selectableRows === 0)
					return false;

				var $row=$(this).parent().parent();
				if(settings.selectableRows == 1){
					$row.siblings().removeClass('selected');
					$("input:not(#"+this.id+")[name='"+this.name+"']").attr('checked',false);
				} else {
					$('#'+id+' .'+settings.tableClass+' > thead > tr > th >input.select-on-check-all').attr('checked', $("input.select-on-check").length==$("input.select-on-check:checked").length);
				}
				$row.toggleClass('selected', this.checked);
				
				$.fn.cDataTable.select(id, this);
				if(settings.selectionChanged !== undefined)
					settings.selectionChanged(id);
				return true;
			});

			if(settings.selectableRows > 1) {
				//$('#'+id+' .'+settings.tableClass+' > thead > tr > th > div > input.select-on-check-all').die('click').live('click',function(){
				$('#'+id+' .'+settings.tableClass+' > thead').undelegate('input.select-on-check-all','click').delegate('input.select-on-check-all','click',function(){
					var checkedall=this.checked;
					var name=this.name.substring(0,this.name.length-4)+'[]';	//.. remove '_all' and add '[]'
					$("input[name='"+name+"']").each(function() {
						if (this.checked!=checkedall){
							this.click();
						}
						$(this).parent().parent().toggleClass('selected',checkedall);
					});
					if(settings.selectionChanged !== undefined)
						settings.selectionChanged(id);
				});
			}
			settings.fnDrawCallback = $.fn.cDataTable.drawCallback;
			jQuery('#'+id+' table').dataTable(settings).fnSetFilteringDelay();
			if (settings.bScrollCollapse)
				$('#'+id+' .dataTables_wrapper').css({'min-height':'0px'});

			var toolbar = $('#'+id+' .dataTables_toolbar');
			if (toolbar.length != 0){
				for (var i in settings.buttons) {
					if (settings.buttons[i] == null || (i=='new' && (typeof settings.newUrl == 'undefined' || settings.newUrl == ''))) {
						// skip if definition is missing (disabling defaults) or skip the new button if newUrl is not provided
						continue;
					}
					var button = $('<button class="'+settings.buttons[i].htmlClass+'">'+settings.buttons[i].label+'</button>').appendTo(toolbar)
						.button({icons: {primary:settings.buttons[i].icon}, text: settings.buttons[i].text});
					if (settings.buttons[i].callback == null) {
						switch(i) {
							case 'refresh':	button.click(function(){$('#'+id+' table').dataTable().fnDraw(); return false;}); break;
							case 'print':	button.click(function(){return false;}); break;
							case 'export':	button.click(function(){return false;}); break;
							case 'new':
								button.click(function(){
									return editDialog(
										{newUrl: settings.newUrl, saveUrl: '/'+id+'/save'},
										null,
										settings.store_trx_id,
										settings.save_trx_id,
										function(data){
											// add key of new item to selected items list
											var input_selected = $('#' + id + '-selected');
											var list_selected = input_selected.data('list');
											if (typeof list_selected == 'undefined') list_selected = {};
											list_selected[data.id] = data.id;
											var list_selected_serialized = '';
											for (var i in list_selected) {
												list_selected_serialized += i + ',';
											}
											input_selected.val(list_selected_serialized).data('list',list_selected);
											// refresh, new record will be fetched and selected
											$('#'+id+' table').dataTable().fnDraw();
										}
									);
								});
								break;
							default: break;
						}
					} else {
						button.click(settings.buttons[i].callback);
					}
				}
			}
		},

		ajaxSuccess: function(data) {
			var $this = $(this);
			var id = $this.attr('id');
			var settings = $.fn.cDataTable.settings[id];
			if(settings.afterAjaxUpdate !== undefined)
				settings.afterAjaxUpdate(id, data);
			$('#'+id+' > div.keys').html($.map(data.keys, function(value, index){return '<span>'+value+'</span>';}).join(''));
		}
	};

	$.fn.cDataTable = function(method) {
		if ( methods[method] ) {
			return methods[ method ].apply( this, Array.prototype.slice.call( arguments, 1 ));
		} else if ( typeof method === 'object' || ! method ) {
			return methods.init.apply( this, arguments );
		} else {
			$.error( 'Method ' +  method + ' does not exist on jQuery.cDataTable' );
		} 
	};

	$.fn.cDataTable.lang = {
		pl: {
			/**
			 * @todo insert baseUrl here or just put in in CSS?
			 */
			sProcessing: '<img src="/images/loader.gif" alt="czekaj"/> Czekaj...',
			sLengthMenu: "Pokaż _MENU_",
			sZeroRecords: "Nie znaleziono pasujących rekordów",
			sInfo: "Widoczne od <strong>_START_</strong> do <strong>_END_</strong> z <strong>_TOTAL_</strong> rekordów",
			sInfoEmpty: "Wyświetlane 0 rekordów",
			sInfoFiltered: "(wyszukano z <strong>_MAX_</strong> wszystkich rekordów)",
			sInfoPostFix: "",
			sSearch: "Szukaj:",
			sUrl: "",
			oPaginate: {
					sFirst: "Pierwsza",
					sPrevious: "Poprzednia",
					sNext: "Następna",
					sLast: "Ostatnia"
			},
			error: "Wystąpił nieoczekiwany błąd, nie udało się pobrać danych.\\nZdarzenie to zostało zapisane do dziennika.\\nProsimy powiadomić administratora serwisu.",
			buttons: {
				'refresh': null,//'Odśwież',
				'print': null,//'Drukuj',
				'export': null,//'CSV',
				'new': 'Dodaj'
			}
		}
	};

	$.fn.cDataTable.defaults = {
		ajaxUpdate: [],
		ajaxVar: 'ajax',
		pagerClass: 'pager',
		loadingClass: 'loading',
		filterClass: 'filters',
		tableClass: 'items',
		selectableRows: 1,

		bProcessing: true,
		bServerSide: true,
		bStateSave: false,
		bAutoWidth: false,
		bJQueryUI: true,
		sPaginationType: "full_numbers",
		//aaSorting: [[0, "asc"]],
		iDisplayLength: 25,
		oLanguage: $.fn.cDataTable.lang.pl,
		editable: {
			string: {},
			integer: {},
			boolean: {}
		},
		buttons: {}
	};

	$.fn.cDataTable.settings = {};

	$.fn.cDataTable.drawCallback = function(oSettings) {
		// iterate on all checkboxes, get the row id and check in lists of selected and deselected if the state should be changed
		var $this = $(this).parent().parent();
		var id = $this.attr('id');
		var settings = $.fn.cDataTable.settings[id];
		var list_selected = $('#'+id+'-selected').data('list');
		var list_deselected = $('#'+id+'-deselected').data('list');
		var list_values = $('#'+id+'-values').data('list');
		$('#'+id+' .'+settings.tableClass+' > tbody > tr > td >input.select-on-check').each(function(){
			var row = $(this).parent().parent().index();
			var key = $.fn.cDataTable.getKey(id, row);
			if (typeof list_selected != 'undefined' && typeof list_selected[key] != 'undefined') {
				$(this).attr('checked',true);
			} else if (typeof list_deselected != 'undefined' && typeof list_deselected[key] != 'undefined') {
				$(this).attr('checked',false);
			}
		});
		$('#'+id+' .'+settings.tableClass+' > tbody > tr input.editable').each(function(){
			var row = $(this).parent().parent().index();
			var key = $.fn.cDataTable.getKey(id, row);
			var attr = $(this).attr('id').substr(0,$(this).attr('id').length-(row+'').length-4);
			if (typeof list_values != 'undefined'
					&& typeof list_values[key] != 'undefined'
					&& typeof list_values[key][attr] != 'undefined') {
				$(this).val(list_values[key][attr]);
			}
		});
		// call selectChecked
		$.fn.cDataTable.selectCheckedRows(id);
		if (typeof settings.fnDrawCallbackCustom != 'undefined') {
			settings.fnDrawCallbackCustom(oSettings);
		}
	};

	$.fn.cDataTable.ajaxError = function(XHR, testStatus, errorThrown) {
		if(XHR.readyState == 0 || XHR.status == 0)
			return;
		var err='';
		switch(textStatus) {
			case 'timeout':
				err='The request timed out!';
				break;
			case 'parsererror':
				err='Parser error!';
				break;
			case 'error':
				if(XHR.status && !/^\s*$/.test(XHR.status))
					err='Error ' + XHR.status;
				else
					err='Error';
				if(XHR.responseText && !/^\s*$/.test(XHR.responseText))
					err=err + ': ' + XHR.responseText;
				break;
		}

		if(settings.ajaxUpdateError !== undefined)
			settings.ajaxUpdateError(XHR, textStatus, errorThrown,err);
		else if(err)
			alert(err);
	};

	$.fn.cDataTable.selectCheckedRows = function(id) {
		var settings = $.fn.cDataTable.settings[id];
		$('#'+id+' .'+settings.tableClass+' > tbody > tr > td >input.select-on-check:checked').each(function(){
			$(this).parent().parent().addClass('selected');
		});

		$('#'+id+' .'+settings.tableClass+' > thead > tr > th > div >input[type="checkbox"]').each(function(){
			var name=this.name.substring(0,this.name.length-4)+'[]';	//.. remove '_all' and add '[]''
			this.checked=$("input[name='"+name+"']").length==$("input[name='"+name+"']:checked").length;
		});
	};

	$.fn.cDataTable.getKey = function(id, row) {
		return $('#'+id+' > div.keys > span:eq('+row+')').text();
	};

	$.fn.cDataTable.select = function(id, checkbox) {
		var input_selected = $('#' + id + '-selected');
		var input_deselected = $('#' + id + '-deselected');
		var list_selected = input_selected.data('list');
		var list_deselected = input_deselected.data('list');
		var row_id = $.fn.cDataTable.getKey(id, $(checkbox).parent().parent().index());
		if (typeof list_selected == 'undefined') list_selected = {};
		if (typeof list_deselected == 'undefined') list_deselected = {};
		if (typeof list_selected[row_id] != 'undefined') {
			// unsaved, previously added to selected
			if ($(checkbox).attr('checked')) {
				// not possible to select second time?
			} else {
				delete list_selected[row_id];
			}
		} else if (typeof list_deselected[row_id] != 'undefined') {
			// unsaved, previously removed from selected
			if ($(checkbox).attr('checked')) {
				delete list_deselected[row_id];
			} else {
				// not possible to deselect second time?
			}
		} else {
			// first change
			if ($(checkbox).attr('checked')) {
				list_selected[row_id] = row_id;
			} else {
				list_deselected[row_id] = row_id;
			}
		}
		var list_selected_serialized = '';
		var list_deselected_serialized = '';
		for (var i in list_selected) {
			list_selected_serialized += i + ',';
		}
		for (var i in list_deselected) {
			list_deselected_serialized += i + ',';
		}
		input_selected.val(list_selected_serialized).data('list',list_selected);
		input_deselected.val(list_deselected_serialized).data('list',list_deselected);
		return false;
	};

})(jQuery);
