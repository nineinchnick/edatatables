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
	"use strict";
	var methods = {
		init: function (options) {
			var settings = $.extend({}, $.fn.eDataTables.defaults, options || {});
			var $this = $(this);
			var id = $this.attr('id');
			$.fn.eDataTables.settings[id] = settings;

			$('#'+id+'-all-selected').data('select', settings.unsavedChanges.all_selected);
			$('#'+id+'-selected').data('list', settings.unsavedChanges.selected.length == 0 ? {} : settings.unsavedChanges.selected);
			$('#'+id+'-deselected').data('list', settings.unsavedChanges.deselected.length == 0 ? {} : settings.unsavedChanges.deselected);
			$('#'+id+'-disconnect').data('list', settings.unsavedChanges.disconnect.length == 0 ? {} : settings.unsavedChanges.disconnect);
			$('#'+id+'-values').data('list', settings.unsavedChanges.values.length == 0 ? {} : settings.unsavedChanges.values);
			$('#'+id+'-values').data('base', {});

			// -----------------------
			// selectable rows part 1 - clicking on row
			if(settings.selectableRows > 0) {
				$('#'+id+' .'+settings.tableClass+' > tbody')
					.off('click', 'tr').on('click', 'tr', function(e){
						if (e.target.tagName == 'TD') { //clicked on td - not input, <a> or something else
							$this.eDataTables('toggleSelection', this);
						} else {
							return true;
						}
				});
			}

			// -----------------------
			// editable columns
			$('#'+id+' .'+settings.tableClass+' > tbody')
				.off('change', 'input.editable, select.editable')
				.on('change', 'input.editable, select.editable', function(e){
					// if this is a not empty textbox and row is not selected select it
					if (settings.selectableRows > 0 && settings.editableSelectsRow && (
							!$(this).parents('tr:first').hasClass('selected') && (
								e.target.type != 'text' || $(e.target).val() != ''
							)
						)) {
						$this.eDataTables('toggleSelection', $(this).parents('tr:first')[0]);
					}

					var input_values = $('#'+id+'-values');
					var list_values = input_values.data('list');
					var base_values = input_values.data('base');
					var row_id = $.fn.eDataTables.getKey(id, $(e.target).parents('tr:first').index());
					var index = $(e.target).attr('id').substr(0,$(e.target).attr('id').indexOf('_row'));
					
					if (typeof list_values == 'undefined') list_values = {};
					if (typeof list_values[row_id] == 'undefined') list_values[row_id] = {};
					var value;
					switch(e.target.type) {
						default:
						case 'select':
						case 'text': value = $(e.target).val(); break;
						case 'checkbox': value = $(e.target).attr('checked') ? 1 : 0; break;
					}
					if (typeof base_values[row_id] != 'undefined'
							&& typeof base_values[row_id][index] != 'undefined'
							&& typeof list_values[row_id][index] != 'undefined'
							&& base_values[row_id][index] == value) {
						delete list_values[row_id][index];
					} else {
						list_values[row_id][index] = value;
					}
					
					input_values.val($.param(list_values)).data('list',list_values);
					if(typeof settings.editableEvents == 'function') {
						settings.editableEvents(e);
					} else if (typeof settings.editableEvents == 'object' && typeof settings.editableEvents.change == 'function') {
						settings.editableEvents.change(e);
					}
					return true;
				}
			);
			if (typeof settings.editableEvents == 'object') {
				for (var i in settings.editableEvents) {
					if (i == 'change') {
						continue;
					}
					$('#'+id+' .'+settings.tableClass+' > tbody')
						.undelegate('input.editable, select.editable',i)
						.delegate('input.editable, select.editable',i,settings.editableEvents[i]);
				}
			}

			// -----------------------
			// selectable rows part 2 - checkboxes
			$('#'+id+' .'+settings.tableClass+' > tbody')
				.off('click','input.select-on-check')
				.on('click','input.select-on-check', function(e){return $this.eDataTables('toggleSelection', this)});

			if(settings.selectableRows > 1) {
				$('#'+id+' .'+settings.tableClass+' > thead')
					.off('click', '.dropdown-menu a')
					.on('click', '.dropdown-menu a', function(e){$this.eDataTables('toggleSelection', this); e.preventDefault();})
			}

			settings.fnDrawCallback = $.fn.eDataTables.drawCallback;
			$.fn.eDataTables.tables[id] = jQuery('#'+id+' table').dataTable(settings).fnSetFilteringDelay();
			if (settings.bScrollCollapse)
				$('#'+id+' .dataTables_wrapper').css({'min-height':'0px'});

			// -----------------------
			// configuration
			$this.eDataTables('initConfigRow', settings);

			// -----------------------
			// toolbar
			$this.eDataTables('initToolbar', settings);

			//deselect all rows on filtering
			$('div.dataTables_filter input[type=text]').on('keyup', function(e) {
				$('#' + id + '-all-selected').val('').data('select', '');
				$('#' + id + '-selected').val('').data('list', {});
				$('#' + id + '-deselected').val('').data('list', {});
				$('#' + id + '-disconnect').val('').data('list', {});
				$('#' + id + '-values').val('').data('list', {});
				
				$('input.select-on-check-all').removeAttr('checked');
				$('input.select-on-check-all').prop('indeterminate', false);
			});

			if (settings.filterForm !== null) {
				var a = settings.filterSerializeCallback($(settings.filterForm));
				var b = {};
				for(var i = 0; i < a.length; i++) {
					b[a[i].name] = a[i].value;
				};
				$.fn.eDataTables.settings[id].serializedForm = b;
			}
		},

		/**
		 * This method is intented as a param to fnServerParams option in dataTables plugin.
		 *
		 * Build a set of params describing current state send to the server.
		 * That includes:
		 * - any extra user provided data (consts and callbacks)
		 * - state of filtering form
		 * - columns visibility and order
		 * @param array aoData original dataTables params
		 */
		serverParams: function(aoData) {
			var $this = $(this);
			var id = $this.attr('id');
			var settings = $.fn.eDataTables.settings[id];

			for (var i in settings.ajaxOpts) {
				var v;
				if (typeof settings.ajaxOpts[i] == 'function') {
					v = settings.ajaxOpts[i]();
				} else {
					v = settings.ajaxOpts[i];
				}
				aoData.push({'name': i, 'value': v});
			}

			if (settings.filterForm !== null) {
				var filterForm = $(settings.filterForm);
				if (filterForm.length > 0) {
					$.merge(aoData, settings.filterSerializeCallback(filterForm));
					aoData.push({'name':'submit','value':true});
				}
			}

			//! @todo this looks ugly, refactor? maybe put in ajaxOpts as a callback function?
			if ($('#'+id+'_relatedOnly').length && $('#'+id+'_relatedOnly').attr('checked')) {
				aoData.push({'name': 'relatedOnly', 'value': true});
			}
			// remove empty/unused options to make request smaller
			//! @todo move this to the top of this method to avoid iterating over keys added above
			var notSuffixed = ['bRegex', 'iColumns'];
			var suffixed = ['bRegex', 'bSearchable', 'sSearch', 'bSortable', 'mDataProp'];
			var toBeRemoved = [];
			for(var i = 0; i < aoData.length; i++) {
				if ($.inArray(aoData[i].name, notSuffixed) !== -1 ||
					$.inArray(aoData[i].name.substr(0,aoData[i].name.lastIndexOf('_')), suffixed) !== -1) {
					toBeRemoved.push(i);
				}
			}
			for(var i = 0; i < toBeRemoved.length; i++) {
				aoData.splice(toBeRemoved[i]-i, 1);
			}

			// send visible columns indexes in order to the server for storage and proper column ordering
			var oTable = $('#'+id+' table[id]').dataTable();
			var aoColumns = oTable.fnSettings().aoColumns;
			var visibleColumns = [];
			for (var i = 0; i < aoColumns.length; i++) {
				if (!aoColumns[i].bVisible) continue;
				visibleColumns.push(i);
			}
			aoData.push({'name': 'visibleColumns', 'value': visibleColumns.join(',')});
		},

		/**
		 * This method is intented as a param to fnServerData option in dataTables plugin.
		 *
		 * Make an Ajax call to the server, including pre and post callbacks.
		 */
		serverData: function(sSource, aoData, fnCallback) {
			var $this = $(this);
			var id = $this.attr('id');
			var settings = $.fn.eDataTables.settings[id];

			if (settings.jqxhr !== null)
				settings.jqxhr.abort();
			if(settings.beforeAjaxUpdate !== undefined)
				settings.beforeAjaxUpdate(id);
			settings.jqxhr = $.ajax({
				'dataType': 'json',
				'type': 'GET',
				'url': sSource,
				'data': aoData,
				'success': [
					function(data){return $this.eDataTables('beforeAjaxSuccess', data);},
					fnCallback,
					function(data){return $this.eDataTables('ajaxSuccess', data);}
				],
				'error': function(XHR, textStatus, errorThrown){
					return $.fn.eDataTables.ajaxError(XHR, textStatus, errorThrown, settings);
				}
			});
		},

		/**
		 * Is called after fetching remote data but before drawing it in the table.
		 * @param array data
		 */
		beforeAjaxSuccess: function(data) {
			var $this = $(this);
			var id = $this.attr('id');
			var settings = $.fn.eDataTables.settings[id];

			// fill the keys div, the server puts row IDs in the keys property while preparing the response
			$('div.keys', $this).html($.map(data.keys, function(value, index){return '<span>'+value+'</span>';}).join(''));
		},

		/**
		 * Is called after fetching remote data and drawing it in the table.
		 * @param array data
		 */
		ajaxSuccess: function(data) {
			var $this = $(this);
			var id = $this.attr('id');
			var settings = $.fn.eDataTables.settings[id];

			if(settings.afterAjaxUpdate !== undefined)
				settings.afterAjaxUpdate(id, data);
		},

		refresh: function() {
			$('#'+this.attr('id')+' table[id]').dataTable().fnDraw();
		},

 		search: function() {
			var id = this.attr('id');
			var settings = $.fn.eDataTables.settings[id];
			var changed = false;

			/**
			 * If a filteractiveform jQuery plugin is registered try to use it
			 * to see if the form got any errors.
			 */
			if (settings.filterForm !== null && $.fn.filteractiveform !== undefined) {
				if ($.fn.filteractiveform.hasErrors($(settings.filterForm)))
					return;
			}

			/**
			 * When rows are not selectable don't track filter form changes.
			 */
			if ( settings.selectableRows <= 1 && $('input.editable, select.editable').length == 0 ) {
				this.eDataTables('refresh');
				return;
			}
			
			if (settings.filterForm === null) {
				// if we can't track it, assume it changes every time
				changed = true;
			} else {
				var a = settings.filterSerializeCallback($(settings.filterForm));
				var serializedForm = {};
				for(var i = 0; i < a.length; i++) {
					var key = a[i].name;
					var value = a[i].value;
					serializedForm[key] = value;

					if (typeof settings.serializedForm == 'undefined' ||
							typeof settings.serializedForm[key] == 'undefined' ||
							settings.serializedForm[key] != serializedForm[key])
					{
						changed = true;
					}
				};

				settings.serializedForm = serializedForm;
			}
			if (changed) {
				$('#' + id + '-all-selected').val('').data('select', '');
				$('#' + id + '-selected').val('').data('list', {});
				$('#' + id + '-deselected').val('').data('list', {});
				$('#' + id + '-disconnect').val('').data('list', {});
				$('#' + id + '-values').val('').data('list', {});

				$('input.select-on-check-all').removeAttr('checked');
				$('input.select-on-check-all').prop('indeterminate', false);

			}			
			this.eDataTables('refresh');
 		},

		initConfigRow: function(settings) {
			var $this = $(this);
			var id = this.attr('id');
			var configureRow = $('#'+id+' > div.dataTables_wrapper > div:first');
			if (configureRow.length == 0)
				return false;

			// hide the first row and init a select2 control inside
			configureRow.hide();
			// if configure button was enabled, init a select2 control with columns list
			if (typeof settings.buttons.configure == 'undefined') {
				return false;
			}
			var oTable = $('#'+id+' table[id]').dataTable();
			var aoColumns = oTable.fnSettings().aoColumns;
			//var configList = $('<select multiple="multiple">').appendTo(configureRow);
			var data = [];
			var value = [];
			var visible = [];
			for (var i = 0; i < aoColumns.length; i++) {
				//var c = aoColumns[i];
				//$('<option value="'+i+'"'+(c.bVisible?' selected="selected"':'')+'>'+c.sTitle+'</option>').appendTo(configList);
				data.push({id: aoColumns[i].sName, text: aoColumns[i].sTitle});
				if (aoColumns[i].bVisible) {
					value.push(aoColumns[i].sName);
					visible.push({id: aoColumns[i].sName, text: aoColumns[i].sTitle});
				}
			}
			var configList = $('<input type="hidden" value="'+value.join(',')+'">').appendTo(configureRow);
			// obtain a list of visible columns
			// add a hidden input
			// init select2
			configList.select2({
				data: data,
				multiple: true,
				initSelection: function(element, callback) { callback(visible); }
			}).on("change", function(e) {
				var aoColumns = oTable.fnSettings().aoColumns;
				// update column visibility
				if (typeof e.added != 'undefined') {
					for (var i = 0; i < aoColumns.length; i++) {
						if (aoColumns[i].sName == e.added.id) {
							oTable.fnSetColumnVis( i, true, false );
							oTable.fnColReorder( i, aoColumns.length-1 );
							break;
						}
					}
				} else if (typeof e.removed != 'undefined') {
					for (var i = 0; i < aoColumns.length; i++) {
						if (aoColumns[i].sName == e.removed.id) {
							oTable.fnSetColumnVis( i, false, false );
							break;
						}
					}
				} else {
					// possibly a change in order
					// since only moving of 1 column at a time is allowed
					// locate them and build a whole list including invisible columns
					var oldPos = null;
					var newPos = null;
					var visible = [];
					for (var i = 0; i < aoColumns.length; i++) {
						if (!aoColumns[i].bVisible) continue;
						visible.push({name: aoColumns[i].sName, index: i});
					}
					// iterate through both old and new lists comparing current values
					for (var j = 0; j < visible.length; j++) {
						if (e.val[j] == visible[j].name) {
							continue;
						}
						if (e.val[j] == visible[j+1].name) {
							// j-th element was moved from here
							oldPos = visible[j].index;
							// find it in new array and get index of it
							for (var k = 0; k < e.val.length; k++) {
								if (e.val[k] == visible[j].name) {
									newPos = visible[k].index;
									break;
								}
							}
						} else {
							// element was inserted here
							newPos = visible[j].index;
							// find it in old array and get index of it
							for (var k = 0; k < visible.length; k++) {
								if (visible[k].name == e.val[j]) {
									oldPos = visible[k].index;
									break;
								}
							}
						}
						oTable.fnColReorder( oldPos, newPos );
						break;
					}
				}
				// make a complete refresh so the backend can save all UI changes
				$this.eDataTables('refresh');
			});
			configList.select2("container").find("ul.select2-choices").sortable({
				containment: 'parent',
				start: function() { configList.select2("onSortStart"); },
				update: function() { configList.select2("onSortEnd"); }
			});
			return true;
		},

		initToolbar: function(settings) {
			var $this = $(this);
			var id = this.attr('id');
			var toolbar = $('#'+id+' .dataTables_toolbar');
			if (toolbar.length == 0)
				return false;
			
			if (settings.relatedOnlyOption) {
				var checked = settings.relatedOnlyOption == '2' ? ' checked="checked"' : '';
				$('<input type="checkbox" id="'+id+'_relatedOnly" value="1"'+checked+'/><label for="'+id+'_relatedOnly">'+settings.relatedOnlyLabel+'</label>').appendTo(toolbar);
			}
			for (var i in settings.buttons) {
				if (settings.buttons[i] == null || (i=='new' && (typeof settings.newUrl == 'undefined' || settings.newUrl == ''))) {
					// skip if definition is missing (disabling defaults) or skip the new button if newUrl is not provided
					continue;
				}
				var button = $($.fn.eDataTables.buttonToHtml(settings.buttons[i], settings.bootstrap)).appendTo(toolbar);
				if (!settings.bootstrap) {
					button.button({icons: {primary:settings.buttons[i].icon}, text: settings.buttons[i].text});
				}
				if (settings.buttons[i].callback == null) {
					switch(i) {
						case 'configure':	button.click(function(){$('#'+id+' > div.dataTables_wrapper > div:first').toggle(); return false;}); break;
						case 'refresh':	button.click(function(){$this.eDataTables('refresh'); return false;}); break;
						case 'print':	button.click(function(){return false;}); break;
						case 'export':	button.click(function(){return false;}); break;
						case 'new':
							button.click(function(){
								return $.fn.eDataTables.editDialog(
									{newUrl: settings.newUrl, saveUrl: settings.newUrl},
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
// 											$('#'+id+' table[id]').dataTable().fnDraw();
										$this.eDataTables('refresh');
									},
									$.extend(settings.ajaxOpts, {ajax:1}),
									settings.bootstrap
								);
							});
							break;
						default: break;
					}
				} else {
					button.click(settings.buttons[i].callback);
				}
			}
			if (settings.bootstrap) { //reinitialize tooltips and popovers
				$('a[rel=tooltip]').tooltip();
				$('a[rel=popover]').popover();
			}
		},

		toggleSelection: function(target) {
			var id = this.attr('id');
			var settings = $.fn.eDataTables.settings[id];

			if (settings.selectableRows === 0)
				return false;
			if ($(target).hasClass('dropdown-select-all') || $(target).hasClass('dropdown-deselect-all')) {
/*select/deselect all*/
				var check_all = $(target).hasClass('dropdown-select-all');
				var selected = check_all ? 'select' : 'deselect';
				$('#' + id + '-all-selected').val(selected).data('select', selected);
				$('#' + id + '-selected').val('').data('list', {});
				$('#' + id + '-deselected').val('').data('list', {});
				$('#' + id + '-disconnect').val('').data('list', {});

				var checkbox = $(target).closest('div.dropdown').find(':checkbox');
				var name = checkbox.attr('name').replace('_all', '[]');
				checkbox.attr('checked', check_all);
				checkbox.prop('indeterminate', false);

				$("input[name='"+name+"']").each(function() { //iterate through all rows (checkboxes)
					this.checked = check_all;
					$(this).parent().parent().toggleClass('selected',check_all);
				});

			} else if ($(target).hasClass('dropdown-select-page') || $(target).hasClass('dropdown-deselect-page')) {
/*select/deselect page*/
				var check_page = $(target).hasClass('dropdown-select-page');
				
				var checkbox2 = $(target).closest('div.dropdown').find(':checkbox');
				var name2 = checkbox2.attr('name').replace('_all', '[]');
				checkbox2.attr('checked', check_page);

				$("input[name='"+name2+"']").each(function() { //iterate through all rows (checkboxes)
					this.checked = check_page;
					$(this).parent().parent().toggleClass('selected',check_page);
					$.fn.eDataTables.select(id, this);
				});
			} else if ($(target).hasClass('select-on-check')){ 
/*select one row by checkbox*/
				var $row = $(target).parent().parent();
				if(settings.selectableRows == 1){
					$row.siblings().removeClass('selected');
					$("input:not(#"+target.id+")[name='"+target.name+"']").attr('checked',false);
//				} else {
//					var is_all_checked = $("input.select-on-check").length==$("input.select-on-check:checked").length;
//					$('#'+id+' .'+settings.tableClass+' > thead > tr > th >input.select-on-check-all')
//						.attr('checked', is_all_checked);
				}
				$row.toggleClass('selected', target.checked);
				$.fn.eDataTables.select(id, target);
			} else {
/*select by row*/
				if(settings.selectableRows == 1)
					$(target).siblings().removeClass('selected');

				var isRowSelected=$(target).toggleClass('selected').hasClass('selected');
				$('input.select-on-check',target).each(function(){
					if(settings.selectableRows == 1){
						$("input[name='"+target.name+"']").attr('checked',false);
					}

					this.checked = isRowSelected;
					var sboxallname = this.name.replace('[]', '_all');
//					var is_all_checked = $("input[name='"+this.name+"']").length == $("input[name='"+this.name+"']:checked").length;
//					$("input[name='"+sboxallname+"']").attr('checked', is_all_checked);
					$.fn.eDataTables.select(id, this);
				});
			}
			if(settings.selectionChanged !== undefined)
				settings.selectionChanged(id);
			return true;
		},
		/*
		 * options = {
		 *		urls - object with properties: {controller: '', editAction: '', saveAction: ''} OR {newUrl: '', saveUrl: ''}
		 *		opts - options to append to submit data,
		 *		afterSave - fn callback
		 *		saveForm - fn which will be invoked to save form - can be omitted, then default will be used
		 *		}
		 */
		editDialog: function(options){
			var id = this.attr('id');
			var settings = $.fn.eDataTables.settings[id];

			if (!this.eDataTables('isSomeSelected') && !this.eDataTables('isAllSelected')){
				alert('Zaznacz wiersze do edycji');
				return;
			}

			if (typeof options == 'undefined')
				options = {opts: {ajax: 1}}
			if (typeof options.opts == 'undefined') {
				options.opts = {ajax: 1};
			}
			/**
			 * @todo baseUrl, action?
			 */
			var url = '';
			if (typeof options.urls != 'undefined' && typeof options.urls.controller != 'undefined') {
				url = '/'+options.urls.controller+'/' + (typeof options.urls.editAction != 'undefined' ? options.urls.editAction : 'edit') + '/' + id;
			} else {
				url = options.urls.newUrl;
			}
			var dialog;

			var saveForm = typeof options.saveForm == 'function' ? options.saveForm : function() {
				var $form = $('form', dialog);
				$.fn.eDataTables.processYiiForm($form, function(f){return $.fn.eDataTables.saveYiiForm(f, dialog, settings.bootstrap, options.afterSave)});
				/**
				 * @todo disable this button until validation error or other submit cancelling event occurs
				 */
				return false;
			};
			if (!settings.bootstrap) {
				dialog = $('<div style="display:none">Ładowanie danych ...</div>').appendTo('body');
				// load remote content
				dialog.dialog({
					modal: true,
					draggable: true,
					resizable: true,
					width: 900,
					height: 600,
					position: ['center', 60],
					buttons: {
						"Zapisz": saveForm,
						"Anuluj": function() { /** @todo we should clear form timers before destroying it */ dialog.dialog("destroy").remove(); return false; }
					},
					close:function() { $(this).dialog('destroy').remove(); return false; }
				});
			} else {
				var dialogContent = '<div class="modal hide fade" id="edt_new">'+
	'	<div class="modal-header"><button type="button" class="close" aria-hidden="true">&times;</button><h3>Nowy</h3></div>'+
	'	<div class="modal-body"><p>Ładowanie...</p></div>'+
	'	<div class="modal-footer">'+
	'		<button class="btn" aria-hidden="true">Anuluj</button>'+
	'		<button class="btn btn-primary">Zapisz</button></div>'+
	'</div>';
				$(dialogContent).appendTo('body');
				dialog = $('.modal-body');
				$('#edt_new').modal('show');
				$('#edt_new .btn').not('.btn-primary').click(function(){
					$('#edt_new .btn-primary').off('click');
					$('#edt_new').modal('hide').remove();
				});
				$('#edt_new .modal-header button').click(function(){
					$('#edt_new .btn-primary').off('click');
					$('#edt_new').modal('hide').remove();
				});
				$('#edt_new .btn-primary').click(saveForm);
			}
			dialog.load(url, options.opts, function(responseText, textStatus){
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
				if (typeof options.urls.saveUrl != 'undefined') {
					$form.attr('action', typeof options.urls.saveUrl == 'function' ? options.urls.saveUrl() : options.urls.saveUrl);
				}
			});

			return false;
		},

		isAllSelected: function() {
			var id = this.attr('id');
			var all_selected = $('#'+id+'-all-selected').data('select') == 'select';
			var list_selected = $('#'+id+'-selected').data('list');
			var list_deselected = $('#'+id+'-deselected').data('list');
			var list_disconnect = $('#'+id+'-disconnect').data('list');
			var selected_size = 0;
			var deselected_size = 0;
			var disconnect_size = 0;
			var total_records = $.fn.eDataTables.tables[id].fnSettings().fnRecordsTotal();
			for (var d in list_selected) selected_size++;
			for (var e in list_deselected) deselected_size++;
			for (var f in list_disconnect) disconnect_size++;

			return (all_selected && deselected_size == 0 && disconnect_size == 0) ||
								selected_size == total_records;
		},

		isSomeSelected: function() {
			var id = this.attr('id');
			var all_selected = $('#'+id+'-all-selected').data('select') == 'select';
			var list_selected = $('#'+id+'-selected').data('list');
			var list_deselected = $('#'+id+'-deselected').data('list');
			var list_disconnect = $('#'+id+'-disconnect').data('list');
			var selected_size = 0;
			var deselected_size = 0;
			var disconnect_size = 0;
			var total_records = $.fn.eDataTables.tables[id].fnSettings().fnRecordsTotal();
			for (var d in list_selected) selected_size++;
			for (var e in list_deselected) deselected_size++;
			for (var f in list_disconnect) disconnect_size++;
			
			return (selected_size != 0 && selected_size != total_records)
					|| (all_selected && (deselected_size + disconnect_size != total_records) && (deselected_size + disconnect_size != 0));
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
		jqxhr: null,
		ajaxUpdate: [],
		pagerClass: 'pager',
		loadingClass: 'loading',
		filterClass: 'filters',
		tableClass: 'items',
		selectableRows: 1,
		editableSelectsRow: true,
		relatedOnlyOption: false,

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
		buttons: {},
		filterForm: null,
		filterSerializeCallback: function(f){return f.serializeArray();}
	};

	$.fn.eDataTables.settings = {};

	$.fn.eDataTables.tables = {};

	/**
	 * After drawing the table contents previously selected rows are checked
	 * and editable columns are filled with unsaved changes.
	 */
	$.fn.eDataTables.drawCallback = function(oSettings) {
		// iterate on all checkboxes, get the row id and check in lists of selected and disconnect if the state should be changed
		var $this = $(this).parent().parent();
		if (typeof $this.attr('id') == 'undefined') {
			$this = $this.parent().parent();
		}
		var id = $this.attr('id');
		var settings = $.fn.eDataTables.settings[id];
		var all_selected = $('#'+id+'-all-selected').data('select');
		var list_selected = $('#'+id+'-selected').data('list');
		var list_deselected = $('#'+id+'-deselected').data('list');
		var list_disconnect = $('#'+id+'-disconnect').data('list');
		var list_values = $('#'+id+'-values').data('list');
		var base_values = $('#'+id+'-values').data('base');
		
		$('#'+id+' .'+settings.tableClass+' > tbody > tr > td >input.select-on-check').each(function(){
			$(this).data('initValue', this.checked);
			var row = $(this).parent().parent().index();
			var key = $.fn.eDataTables.getKey(id, row);
			if (all_selected === 'select' || (typeof list_selected !== 'undefined' && typeof list_selected[key] !== 'undefined')) {
				if ((typeof list_deselected != 'undefined' && typeof list_deselected[key] != 'undefined')
					|| (typeof list_disconnect != 'undefined' && typeof list_disconnect[key] != 'undefined')) {
					this.checked = false;
				} else {
					this.checked = true;
				}
			} else if ((typeof list_disconnect !== 'undefined' && typeof list_disconnect[key] !== 'undefined')
					||(typeof list_deselected !== 'undefined' && typeof list_deselected[key] !== 'undefined')){
				this.checked = false;
//			} else {
//				this.checked = false;
			}
		});
		$('#'+id+' .'+settings.tableClass+' > tbody > tr .editable').each(function(){
			var row = $(this).parents('tr:first').index();
			var key = $.fn.eDataTables.getKey(id, row);
			var attr = $(this).attr('id').substr(0,$(this).attr('id').length-(row+'').length-4);
			// save original value in base_values
			if (typeof base_values[key] == 'undefined')
				base_values[key] = {};
			base_values[key][attr] = $(this).val();
			// restore unsaved value
			if (typeof list_values != 'undefined'
					&& typeof list_values[key] != 'undefined'
					&& typeof list_values[key][attr] != 'undefined') {
				$(this).val(list_values[key][attr]);
			}
		});
		$('#'+id+'-values').data('base', base_values); 
		// call selectChecked
		$.fn.eDataTables.selectCheckedRows(id);
		if (typeof settings.fnDrawCallbackCustom != 'undefined') {
			settings.fnDrawCallbackCustom(oSettings,id);
		}

		if (settings.bootstrap) { //reinitialize tooltips and popovers
			$('a[rel=tooltip]').tooltip();
			$('a[rel=popover]').popover();
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

		//if all rows is checked then check thead checkbox
		$('#'+id+' .'+settings.tableClass+' > thead > tr > th > div >input[type="checkbox"]').each(function(){
			var name = this.name.replace('_all', '[]');
			this.checked = $("input[name='"+name+"']").length == $("input[name='"+name+"']:checked").length;
		});
	};

	$.fn.eDataTables.getKey = function(id, row) {
		return $('#'+id+' > div.keys > span:eq('+row+')').text();
	};

	$.fn.eDataTables.select = function(id, checkbox) {
		var input_all_selected = $('#' + id + '-all-selected');
		var input_selected = $('#' + id + '-selected');
		var input_deselected = $('#' + id + '-deselected');
		var input_disconnect = $('#' + id + '-disconnect');
		var all_selected = input_all_selected.data('select') == 'select';
		var list_selected = input_selected.data('list');
		var list_deselected = input_deselected.data('list');
		var list_disconnect = input_disconnect.data('list');
		var row_id = $.fn.eDataTables.getKey(id, $(checkbox).parent().parent().index());
		if (typeof list_selected == 'undefined') list_selected = {};
		if (typeof list_deselected == 'undefined') list_deselected = {};
		if (typeof list_disconnect == 'undefined') list_disconnect = {};

		if (all_selected) {
			if ($(checkbox).is(':checked')) {
				delete list_deselected[row_id];
				delete list_disconnect[row_id];
			} else {
				if ($(checkbox).data('initValue')) {
					list_disconnect[row_id] = row_id;
				} else {
					list_deselected[row_id] = row_id;
				}
			}
		} else {
			if (typeof list_selected[row_id] != 'undefined') {
				// unsaved, previously added to selected
				if ($(checkbox).attr('checked')) {
					// not possible to select second time?
				} else {
					delete list_selected[row_id];
				}
			} else if (typeof list_disconnect[row_id] != 'undefined') {
				// unsaved, previously removed from selected
				if ($(checkbox).attr('checked')) {
					delete list_disconnect[row_id];
				} else {
					// not possible to deselect second time?
				}
			} else {
				// first change
				if ($(checkbox).attr('checked') ) {
					list_selected[row_id] = row_id;
				} else {
					list_disconnect[row_id] = row_id;
				}
			}
		}
		var list_selected_serialized = '';
		var list_deselected_serialized = '';
		var list_disconnect_serialized = '';
		for (var i in list_selected) {
			list_selected_serialized += i + ',';
		}
		for (var i in list_deselected) {
			list_deselected_serialized += i + ',';
		}
		for (var i in list_disconnect) {
			list_disconnect_serialized += i + ',';
		}
		input_selected.val(list_selected_serialized).data('list',list_selected);
		input_deselected.val(list_deselected_serialized).data('list',list_deselected);
		input_disconnect.val(list_disconnect_serialized).data('list',list_disconnect);


		

		$('#' + id + ' .select-on-check-all').prop('indeterminate', $('#' + id).eDataTables('isSomeSelected'));
		$('#' + id + ' .select-on-check-all').attr('checked', $('#' + id).eDataTables('isAllSelected'));
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
		if(settings!=undefined && settings.timer!=undefined) {
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

	$.fn.eDataTables.saveYiiForm = function($form, dialog, bootstrap, afterSave) {
		var params = $form.find('input').not('.dont-save').serialize() + '&ajax=1'
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
					if (bootstrap) {
						dialog.parents('.modal:first').modal('hide').remove();
					} else {
						dialog.dialog('destroy').remove();
					}
				}
			}
		});
		return false;
	}

	/**
	 * @param urls an object with properties: {controller: '', editAction: '', saveAction: ''} OR {newUrl: '', saveUrl: ''} 
	 */
	$.fn.eDataTables.editDialog = function(urls, id, afterSave, opts, bootstrap){
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
		var dialog;
		var saveForm = function() {
			var $form = $('form',dialog);
			$.fn.eDataTables.processYiiForm($form, function(f){return $.fn.eDataTables.saveYiiForm(f, dialog, bootstrap, afterSave)});
			/**
			 * @todo disable this button until validation error or other submit cancelling event occurs
			 */
			return false;
		};
		if (!bootstrap) {
			dialog = $('<div style="display:none">Ładowanie danych ...</div>').appendTo('body');
			// load remote content
			dialog.dialog({
				modal: true,
				draggable: true,
				resizable: true,
				width: 900,
				height: 600,
				position: ['center', 60],
				buttons: {
					"Zapisz": saveForm,
					"Anuluj": function() { /** @todo we should clear form timers before destroying it */ dialog.dialog("destroy").remove(); return false; }
				},
				close:function() { $(this).dialog('destroy').remove(); return false; }
			});
		} else {
			var dialogContent = '<div class="modal hide fade" id="edt_new">'+
'	<div class="modal-header"><button type="button" class="close" aria-hidden="true">&times;</button><h3>Nowy</h3></div>'+
'	<div class="modal-body"><p>Ładowanie...</p></div>'+
'	<div class="modal-footer">'+
'		<button class="btn" aria-hidden="true">Anuluj</button>'+
'		<button class="btn btn-primary">Zapisz</button></div>'+
'</div>';
			$(dialogContent).appendTo('body');
			dialog = $('.modal-body');
			$('#edt_new').modal('show');
			$('#edt_new .btn').not('.btn-primary').click(function(){
				$('#edt_new').modal('hide').remove();
			});
			$('#edt_new .modal-header button').click(function(){
				$('#edt_new').modal('hide').remove();
			});
			$('#edt_new .btn-primary').click(saveForm);
		}
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

	$.fn.eDataTables.buttonToHtml = function(button, bootstrap) {
		var htmlOptions = (typeof button.htmlOptions !== 'undefined' ? button.htmlOptions : {});
		htmlOptions['class'] = button.htmlClass + ' ' + (typeof htmlOptions['class'] === 'undefined' ? '' : htmlOptions['class']) + (bootstrap ? ' btn' : '');
		if (bootstrap) {
			htmlOptions.rel = 'tooltip';
			htmlOptions.title = button.label;
		}

		var htmlAttrs = '';
		for (var i in htmlOptions) {
			htmlAttrs += ' ' + i + '="' + htmlOptions[i] + '"';
		}
		if (bootstrap) {
			return '<a ' + htmlAttrs + '><i class="' + button.icon + '"></i>' + (button.text ? button.label : '') + '</a>';
		} else {
			return '<button ' + htmlAttrs + '>'+button.label+'</button>';
		}
	}
	
})(jQuery);
