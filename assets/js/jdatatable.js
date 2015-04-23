/**
 * EDataTables plugin file.
 *
 * @author Jan Was <janek.jan@gmail.com>
 * @copyright Copyright &copy; 2011-2012 Jan Was
 * @license http://www.yiiframework.com/license/
 */

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
			$.fn.eDataTables.tables[id] = jQuery('#'+id+' table').DataTable(settings);

			// -----------------------
			// configuration
			//! @todo remove this, moved into initToolbar
			//$this.eDataTables('initConfigRow', settings);

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

			// if there is no toolbar there can't be the 'configure' button, so skip init
			var toolbar = $('#'+id+' .dataTables_toolbar');
			if (toolbar.length == 0)
				return false;

			// hide the first row and init a select2 control inside
			configureRow.hide();
			// if configure button was enabled, init a select2 control with columns list
			if (typeof settings.buttons.configure == 'undefined' || settings.buttons.configure == null) {
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
			var configList = $('<input id="'+id+'_configRow" type="hidden" value="'+value.join(',')+'">').appendTo(configureRow);
			// obtain a list of visible columns
			// add a hidden input
			// init select2
			configList.select2({
				data: data,
				multiple: true,
				//width: '10%',
				dropdownAutoWidth: true,
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
				return true;
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

			$this.eDataTables('initConfigControl', settings, toolbar);

			if (settings.relatedOnlyOption) {
				var checked = settings.relatedOnlyOption == '2' ? ' checked="checked"' : '';
				$('<input type="checkbox" id="'+id+'_relatedOnly" value="1"'+checked+'/><label for="'+id+'_relatedOnly">'+settings.relatedOnlyLabel+'</label>').appendTo(toolbar);
			}

			$this.eDataTables('initButtons', settings, toolbar);
		},

		initButtons: function(settings, toolbar) {
			var $this = $(this);
			var id = this.attr('id');
			for (var i in settings.buttons) {
				if (settings.buttons[i] == null) {
					// skip if definition is missing (disabling defaults)
					continue;
				}
				var button = $($.fn.eDataTables.buttonToHtml(settings.buttons[i])).appendTo(toolbar);
				if (!settings.buttons[i].init || typeof settings.buttons[i].init != 'function') {
					button.button({icons: {primary:settings.buttons[i].icon}, text: settings.buttons[i].text});
				} else {
					settings.buttons[i].init(button, settings.buttons[i]);
				}
				if (typeof settings.buttons[i].callback != 'undefined') {
					button.click({'id': id, 'that': $this, 'settings': settings}, settings.buttons[i].callback);
				}
			}
			return true;
		},

		initConfigControl: function(settings, toolbar) {
			var id = this.attr('id');
			var $this = $(this);

			if (!settings.configurable)
				return false;

			//! @todo insert a select with all column names as options, attach callbacks to show/hide columns
			var select = $('<select id="'+id+'_columns" style="width: 7em;"></select>').appendTo(toolbar);
			$('<option value="">'+settings.columnsListLabel+'</option>').appendTo(select);

			var oTable = $('#'+id+' table[id]').dataTable();
			var aoColumns = oTable.fnSettings().aoColumns;
			for (var i = 0; i < aoColumns.length; i++) {
				var c = aoColumns[i];
				$('<option value="'+i+'">'+(aoColumns[i].bVisible?'ukryj':'pokaż')+' '+c.sTitle+'</option>').appendTo(select); // '+(c.bVisible?' selected="selected"':'')+'
				/*if (aoColumns[i].bVisible) { }*/
			}
			select.on('change', function(e){
				var index = $(this).val();
				var visible = oTable.fnSettings().aoColumns[index].bVisible;
				// update label of selected option
				var option = $('option:selected', $(this));
				var pos = option.html().indexOf(' ');
				option.html((!visible ? 'ukryj' : 'pokaż')+option.html().substr(pos));
				oTable.fnSetColumnVis( index, !visible, false );
				$(this).val('');
				$this.eDataTables('refresh');
			});
		},

		toggleSelection: function(target) {
			var $this = $(this);
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
		},

		settings: function(){
			return $.fn.eDataTables.settings[this.attr('id')];
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
		var $this = $(this).parents('.dataTables_wrapper:first').parent();
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


	$.fn.eDataTables.buttonToHtml = function(button) {
		var htmlOptions = (typeof button.htmlOptions !== 'undefined' ? button.htmlOptions : {});
		htmlOptions['class'] = button.htmlClass + ' ' + (typeof htmlOptions['class'] === 'undefined' ? '' : htmlOptions['class']);
		htmlOptions['href'] = typeof button.url === 'undefined' ? '#' : button.url;

		var htmlAttrs = '';
		for (var i in htmlOptions) {
			htmlAttrs += ' ' + i + '="' + htmlOptions[i] + '"';
		}
		return '<' + button.tagName + ' ' + htmlAttrs + '>' + button.label + '</' + button.tagName + '>';
	}

})(jQuery);
