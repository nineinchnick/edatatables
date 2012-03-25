/**
 * EDataTables plugin file.
 *
 * @author Jan Was <janek.jan@gmail.com>
 * @copyright Copyright &copy; 2011-2012 Jan Was
 * @license http://www.yiiframework.com/license/
 */

/* Default class modification */
$.extend( $.fn.dataTableExt.oStdClasses, {
	"sWrapper": "dataTables_wrapper form-inline"
} );

/* API method to get paging information */
$.fn.dataTableExt.oApi.fnPagingInfo = function ( oSettings )
{
	return {
		"iStart":         oSettings._iDisplayStart,
		"iEnd":           oSettings.fnDisplayEnd(),
		"iLength":        oSettings._iDisplayLength,
		"iTotal":         oSettings.fnRecordsTotal(),
		"iFilteredTotal": oSettings.fnRecordsDisplay(),
		"iPage":          Math.ceil( oSettings._iDisplayStart / oSettings._iDisplayLength ),
		"iTotalPages":    Math.ceil( oSettings.fnRecordsDisplay() / oSettings._iDisplayLength )
	};
}

/* Bootstrap style pagination control */
$.extend( $.fn.dataTableExt.oPagination, {
	"bootstrap": {
		"fnInit": function( oSettings, nPaging, fnDraw ) {
			var oLang = oSettings.oLanguage.oPaginate;
			var fnClickHandler = function ( e ) {
				e.preventDefault();
				if ( oSettings.oApi._fnPageChange(oSettings, e.data.action) ) {
					fnDraw( oSettings );
				}
			};

			$(nPaging).addClass('pagination').append(
				'<ul>'+
					'<li class="first disabled"><a href="#">'+oLang.sFirst+'</a></li>'+
					'<li class="prev disabled"><a href="#">'+oLang.sPrevious+'</a></li>'+
					'<li class="next disabled"><a href="#">'+oLang.sNext+'</a></li>'+
					'<li class="last disabled"><a href="#">'+oLang.sLast+'</a></li>'+
				'</ul>'
			);
			var els = $('a', nPaging);
			$(els[0]).bind( 'click.DT', { action: "first" }, fnClickHandler );
			$(els[1]).bind( 'click.DT', { action: "previous" }, fnClickHandler );
			$(els[2]).bind( 'click.DT', { action: "next" }, fnClickHandler );
			$(els[3]).bind( 'click.DT', { action: "last" }, fnClickHandler );
		},

		"fnUpdate": function ( oSettings, fnDraw ) {
			var iListLength = 5;
			var oPaging = oSettings.oInstance.fnPagingInfo();
			var an = oSettings.aanFeatures.p;
			var i, j, sClass, iStart, iEnd, iHalf=Math.floor(iListLength/2);

			if ( oPaging.iTotalPages < iListLength) {
				iStart = 1;
				iEnd = oPaging.iTotalPages;
			}
			else if ( oPaging.iPage <= iHalf ) {
				iStart = 1;
				iEnd = iListLength;
			} else if ( oPaging.iPage >= (oPaging.iTotalPages-iHalf) ) {
				iStart = oPaging.iTotalPages - iListLength + 1;
				iEnd = oPaging.iTotalPages;
			} else {
				iStart = oPaging.iPage - iHalf + 1;
				iEnd = iStart + iListLength - 1;
			}

			for ( i=0, iLen=an.length ; i<iLen ; i++ ) {
				// Remove the middle elements
				var size = $('li',an[i]).size();
				$('li:gt(1)', an[i]).filter(':lt('+(size-4)+')').remove();
				size = $('li',an[i]).size();
				var prelast = $('li:eq('+(size-2)+')', an[i]);

				// Add the new list items and their event handlers
				for ( j=iStart ; j<=iEnd ; j++ ) {
					sClass = (j==oPaging.iPage+1) ? 'class="active"' : '';
					$('<li '+sClass+'><a href="#">'+j+'</a></li>')
						.insertBefore( prelast[0] )
						.bind('click', function (e) {
							e.preventDefault();
							oSettings._iDisplayStart = (parseInt($('a', this).text(),10)-1) * oPaging.iLength;
							fnDraw( oSettings );
						} );
				}

				// Add / remove disabled classes from the static elements
				if ( oPaging.iPage === 0 ) {
					$('li:lt(2)', an[i]).addClass('disabled');
				} else {
					$('li:lt(2)', an[i]).removeClass('disabled');
				}

				size = $('li',an[i]).size();
				if ( oPaging.iPage === oPaging.iTotalPages-1 || oPaging.iTotalPages === 0 ) {
					$('li:gt('+(size-3)+')', an[i]).addClass('disabled');
				} else {
					$('li:gt('+(size-3)+')', an[i]).removeClass('disabled');
				}
			}
		}
	}
} );

(function($) {
	var methods = {
		init: function (options) {
			var settings = $.extend({}, $.fn.eDataTables.defaults, options || {});
			var $this = $(this);
			var id = $this.attr('id');
			$.fn.eDataTables.settings[id] = settings;

			$.fn.eDataTables.selectCheckedRows(id);

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
					$.fn.eDataTables.select(id, this);
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
			$('#'+id+' .'+settings.tableClass+' > tbody')
				.undelegate('input.editable','change')
				.delegate('input.editable','change',function(e){
					// if this is a not empty textbox and row is not selected select it
					if (!$(this).parent().parent().hasClass('selected') && (e.target.type != 'text' || $(e.target).val() != '')) {
						selectRowFromTr($(this).parent().parent()[0]);
					}

					var input_values = $('#'+id+'-values');
					var list_values = input_values.data('list');
					var row_id = $.fn.eDataTables.getKey(id, $(e.target).parent().parent().index());
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
					if(typeof settings.editableEvents == 'function') {
						settings.editableEvents(e);
					} else if (typeof settings.editableEvents == 'object' && typeof settings.editableEvents.change == 'function') {
						settings.editableEvents.change(e);
					}
					return false;
				}
			);
			if (typeof settings.editableEvents == 'object') {
				for (var i in settings.editableEvents) {
					if (i == 'change') {
						continue;
					}
					$('#'+id+' .'+settings.tableClass+' > tbody')
						.undelegate('input.editable',i)
						.delegate('input.editable',i,settings.editableEvents[i]);
				}
			}

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
				
				$.fn.eDataTables.select(id, this);
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
			settings.fnDrawCallback = $.fn.eDataTables.drawCallback;
			$.fn.eDataTables.tables[id] = jQuery('#'+id+' table').dataTable(settings).fnSetFilteringDelay();
			if (settings.bScrollCollapse)
				$('#'+id+' .dataTables_wrapper').css({'min-height':'0px'});

			var toolbar = $('#'+id+' .dataTables_toolbar');
			if (toolbar.length != 0){
				for (var i in settings.buttons) {
					if (settings.buttons[i] == null || (i=='new' && (typeof settings.newUrl == 'undefined' || settings.newUrl == ''))) {
						// skip if definition is missing (disabling defaults) or skip the new button if newUrl is not provided
						continue;
					}
					var button;
					if (settings.bootstrap) {
						button = $('<a class="btn '+settings.buttons[i].htmlClass+'" rel="tooltip" title="'+settings.buttons[i].label+'"><i class="'+settings.buttons[i].icon+'"></i>'+(settings.buttons[i].text ? settings.buttons[i].label : '')+'</a>').appendTo(toolbar);
					} else {
						button = $('<button class="'+settings.buttons[i].htmlClass+'">'+settings.buttons[i].label+'</button>').appendTo(toolbar)
						.button({icons: {primary:settings.buttons[i].icon}, text: settings.buttons[i].text});
					}
					if (settings.buttons[i].callback == null) {
						switch(i) {
							case 'refresh':	button.click(function(){$('#'+id+' table').dataTable().fnDraw(); return false;}); break;
							case 'print':	button.click(function(){return false;}); break;
							case 'export':	button.click(function(){return false;}); break;
							case 'new':
								button.click(function(){
									return $.fn.eDataTables.editDialog(
										{newUrl: settings.newUrl, saveUrl: '/'+id+'/save'},
										null,
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
										},
										$.extend(settings.ajaxOpts, {ajax:1})
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
			var settings = $.fn.eDataTables.settings[id];
			if(settings.afterAjaxUpdate !== undefined)
				settings.afterAjaxUpdate(id, data);
			$('#'+id+' > div.keys').html($.map(data.keys, function(value, index){return '<span>'+value+'</span>';}).join(''));
		}
	};

	$.fn.eDataTables = function(method) {
		if ( methods[method] ) {
			return methods[ method ].apply( this, Array.prototype.slice.call( arguments, 1 ));
		} else if ( typeof method === 'object' || ! method ) {
			return methods.init.apply( this, arguments );
		} else {
			$.error( 'Method ' +  method + ' does not exist on jQuery.eDataTables' );
		} 
	};

	$.fn.eDataTables.defaults = {
		ajaxUpdate: [],
		pagerClass: 'pager',
		loadingClass: 'loading',
		filterClass: 'filters',
		tableClass: 'items',
		selectableRows: 1,

		ajaxOpts: {},

		bProcessing: true,
		bServerSide: true,
		bStateSave: false,
		bAutoWidth: false,
		bJQueryUI: true,
		sPaginationType: "full_numbers",
		//aaSorting: [[0, "asc"]],
		iDisplayLength: 25,
		editable: {
			string: {},
			integer: {},
			boolean: {}
		},
		buttons: {}
	};

	$.fn.eDataTables.settings = {};

	$.fn.eDataTables.tables = {};

	$.fn.eDataTables.drawCallback = function(oSettings) {
		// iterate on all checkboxes, get the row id and check in lists of selected and deselected if the state should be changed
		var $this = $(this).parent().parent();
		if (typeof $this.attr('id') == 'undefined') {
			$this = $this.parent().parent();
		}
		var id = $this.attr('id');
		var settings = $.fn.eDataTables.settings[id];
		var list_selected = $('#'+id+'-selected').data('list');
		var list_deselected = $('#'+id+'-deselected').data('list');
		var list_values = $('#'+id+'-values').data('list');
		$('#'+id+' .'+settings.tableClass+' > tbody > tr > td >input.select-on-check').each(function(){
			var row = $(this).parent().parent().index();
			var key = $.fn.eDataTables.getKey(id, row);
			if (typeof list_selected != 'undefined' && typeof list_selected[key] != 'undefined') {
				$(this).attr('checked',true);
			} else if (typeof list_deselected != 'undefined' && typeof list_deselected[key] != 'undefined') {
				$(this).attr('checked',false);
			}
		});
		$('#'+id+' .'+settings.tableClass+' > tbody > tr input.editable').each(function(){
			var row = $(this).parent().parent().index();
			var key = $.fn.eDataTables.getKey(id, row);
			var attr = $(this).attr('id').substr(0,$(this).attr('id').length-(row+'').length-4);
			if (typeof list_values != 'undefined'
					&& typeof list_values[key] != 'undefined'
					&& typeof list_values[key][attr] != 'undefined') {
				$(this).val(list_values[key][attr]);
			}
		});
		// call selectChecked
		$.fn.eDataTables.selectCheckedRows(id);
		if (typeof settings.fnDrawCallbackCustom != 'undefined') {
			settings.fnDrawCallbackCustom(oSettings,id);
		}
	};

	$.fn.eDataTables.ajaxError = function(XHR, textStatus, errorThrown, settings) {
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

	$.fn.eDataTables.selectCheckedRows = function(id) {
		var settings = $.fn.eDataTables.settings[id];
		$('#'+id+' .'+settings.tableClass+' > tbody > tr > td >input.select-on-check:checked').each(function(){
			$(this).parent().parent().addClass('selected');
		});

		$('#'+id+' .'+settings.tableClass+' > thead > tr > th > div >input[type="checkbox"]').each(function(){
			var name=this.name.substring(0,this.name.length-4)+'[]';	//.. remove '_all' and add '[]''
			this.checked=$("input[name='"+name+"']").length==$("input[name='"+name+"']:checked").length;
		});
	};

	$.fn.eDataTables.getKey = function(id, row) {
		return $('#'+id+' > div.keys > span:eq('+row+')').text();
	};

	$.fn.eDataTables.select = function(id, checkbox) {
		var input_selected = $('#' + id + '-selected');
		var input_deselected = $('#' + id + '-deselected');
		var list_selected = input_selected.data('list');
		var list_deselected = input_deselected.data('list');
		var row_id = $.fn.eDataTables.getKey(id, $(checkbox).parent().parent().index());
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

	/**
	 * Validates the form specified by id (typically the one containing this dataTable)
	 * @todo this should be available in jquery.yiiactiveform.js
	 * @param int id
	 * @param function successCallback
	 * @param function errorCallback
	 */
	$.fn.eDataTables.processYiiForm = function($form, successCallback, errorCallback) {
		var settings = $form.data('settings');
		if(settings.timer!=undefined) {
			clearTimeout(settings.timer);
		}
		settings.submitting=true;
		if(settings.beforeValidate==undefined || settings.beforeValidate($form)) {
			$.fn.yiiactiveform.validate($form, function(data){
				var hasError = false;
				$.each(settings.attributes, function(i, attribute){
					hasError = $.fn.yiiactiveform.updateInput(attribute, data, $form) || hasError;
				});
				$.fn.yiiactiveform.updateSummary($form, data);
				if((settings.afterValidate==undefined || settings.afterValidate($form, data, hasError)) && !hasError) {
					if (typeof successCallback == 'function')
						successCallback($form);
					settings.submitting=false;
					return true;
				} else if (typeof errorCallback == 'function') {
					settings.submitting=false;
					errorCallback();
					return false;
				}
			}, errorCallback);
			return true;
		} else {
			settings.submitting=false;
			return false;
		}
	}

	$.fn.eDataTables.saveYiiForm = function($form, dialog, afterSave) {
		var params = $form.serialize() + '&ajax=1'
		var saveUrl = $form.attr('action');
		$.post(saveUrl, params, function(data, textStatus, jqXHR){
			if (jqXHR.getResponseHeader('Content-Type') == 'text/html') {
				// a form must have failed validation, redraw it
				dialog.html(data);
				dialog.scrollTop(0);
			} else {
				// we expect JSON
				if (data.errno == 0){
					if (typeof afterSave == 'function') {
						afterSave(data.message);
					}
					dialog.dialog('destroy').remove();
				}
			}
		});
		return false;
	}

	/**
	 * @param urls an object with properties: {controller: '', editAction: '', saveAction: ''} OR {newUrl: '', saveUrl: ''} 
	 */
	$.fn.eDataTables.editDialog = function(urls, id, afterSave, opts){
		if (typeof opts == 'undefined') {
			opts = {ajax:1};
		}
		/**
		 * @todo baseUrl, action?
		 */
		var url = '';
		if (typeof urls.controller != 'undefined') {
			url = '/'+urls.controller+'/' + (typeof urls.editAction != 'undefined' ? urls.editAction : 'edit') + (typeof id != 'undefined' && id != null ? '/'+id : '');
		} else {
			url = urls.newUrl;
		}
		var dialog = $('<div style="display:none">Ładowanie danych ...</div>').appendTo('body');
		// load remote content
		dialog.dialog({
			modal: true,
			draggable: true,
			resizable: true,
			width: 900,
			height: 600,
			position: ['center', 60],
			buttons: {
				"Zapisz": function() {
					var $form = $('form',this);
					$.fn.eDataTables.processYiiForm($form, function(f){return $.fn.eDataTables.saveYiiForm(f, dialog, afterSave)});
					/**
					 * @todo disable this button until validation error or other submit cancelling event occurs
					 */
					return false;
				},
				"Anuluj": function() { /** @todo we should clear form timers before destroying it */ dialog.dialog("destroy").remove(); return false; }
			},
			close:function() { $(this).dialog('destroy').remove(); return false; }
		});
		dialog.load(url, opts, function(responseText, textStatus){
			if (textStatus == 'error') {
				try {
					response = $.parseJSON(responseText);
					dialog.html('<h3 style="color:red">'+response.error+'</h3>');
				} catch(e) {
					dialog.html('<h3 style="color:red">Wystąpił nieznany błąd.</h3><br/><br/><h3>Tego typu zdarzenia nie są rejestrowane, prosimy o zawiadomienie administratorów strony.</h3>');
				}
				return;
			}
			var $form = $('form',this);
			var submitButton = $("button[type='submit']",$form);
			/**
			 * @todo before blocking form submit action check how it validates
			 */
			$form.submit(function(){return false;});
			submitButton.parent().remove();
			if (typeof urls.saveUrl != 'undefined') {
				$form.attr('action', typeof urls.saveUrl == 'function' ? urls.saveUrl() : urls.saveUrl);
			}
		});

		return false;
	}

})(jQuery);
