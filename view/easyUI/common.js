/*
 * Common javascript file loaded with all pages
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade Bizuno to newer
 * versions in the future. If you wish to customize Bizuno for your
 * needs please contact PhreeSoft for more information.
 *
 * @name       Bizuno ERP
 * @author     Dave Premo, PhreeSoft <support@phreesoft.com>
 * @copyright  2008-2025, PhreeSoft, Inc.
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2026-01-11
 * @filesource /view/easyUI/common.js
 */

var jqBiz = $.noConflict(); 

/* **************************** Variables loaded as needed ********************************* */
//initialize some variables
var portalAjax   = 'https://www.bizuno.com?';
var bizDefaults  = new Array();
var curIndex     = undefined;
var deleteRow    = false;
var rowAutoAdd   = true;
var no_recurse   = false;
var addressFields= ['address_id','primary_name','contact','address1','address2','city','state','postal_code','telephone1','telephone2','telephone3','telephone4','email','website'];
var contactFields= ['id','short_name','inactive','store_id','contact_first','contact_last','flex_field_1','account_number','gov_id_number'];
var countries    = new Array();
var inventory    = new Array();
var glAccounts   = new Array();
var arrPmtMethod = new Array();
var cogs_types   = ['si','sr','ms','mi','ma','sa'];
var discountType = 'amt';
var feeType      = 'amt';
var dashTimer;            // timer identifier
var dashTimerVal = 1000;  // time in ms, 2 second for example
var screenMode   = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : '';

/* **************************** Initialization Functions *********************************** */
// If an event listener is needed for something
//window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', event => { if (event.matches) { /* dark mode */ } else { /* light mode */ } });

jqBiz.ajaxSetup({ // Set defaults for ajax requests
//    contentType: "application/json; charset=utf-8", // this breaks easyUI, datagrid operations
//    dataType: (jqBiz.browser.msie) ? "text" : "json", // not needed for jquery 2.x
    dataType: "json",
    error: function(XMLHttpRequest, textStatus, errorThrown) {
            if (textStatus==="timeout") { jqBiz('body').removeClass('loading'); }
            if (errorThrown) {
                jqBiz('body').removeClass('loading');
                var errMessage = bizEscapeHtml(XMLHttpRequest.responseText.substring(0, 500)); // truncate the message
                if (!XMLHttpRequest.responseText.length || !errMessage.length) { // no length, don't show anything
//                    jqBiz.messager.alert('Info', "Bizuno Ajax Error: No data returned", 'info');
                } else if (XMLHttpRequest.responseText.substring(0, 1) == '<') {
                    jqBiz.messager.alert('Info', "Bizuno Ajax Error: Expecting JSON, got HTML (you can probably ignore unless debugging), received: <br /><br />"+errMessage, 'info');
                } else {
                    jqBiz.messager.alert('Info', "Bizuno Ajax Error: "+errorThrown+' - '+errMessage+"<br />Status: "+textStatus, 'info');
                }
            }
        // go to home page/login screen in 5 seconds
//        window.setTimeout(function() { window.location = bizunoHome; }, 3000);
    }
});

/**
 * This function will load into session storage for common Bizuno data that tends to be static once logged in
 */
function loadSessionStorage() {
    if (bizID=='0') { return; }
    jqBiz.ajax({
        url:     bizunoAjax+'&bizRt=bizuno/admin/loadBrowserSession',
        success: function(json) {
            processJson(json);
            if (typeof sessionStorage.bizuno != 'undefined') { sessionStorage.removeItem('bizuno'); }
            sessionStorage.setItem('bizuno', JSON.stringify(json));
            window.location = bizunoHome; // done, load the homepage
        }
    });
}

function reloadSessionStorage(callBackFunction) {
    if (bizID=='0') { return; }
    jqBiz.ajax({
        url:     bizunoAjax+'&bizRt=bizuno/admin/loadBrowserSession',
        success: function(json) {
            processJson(json);
            sessionStorage.removeItem('bizuno');
            sessionStorage.setItem('bizuno', JSON.stringify(json));
            bizDefaults=json;
            if (typeof callBackFunction == 'function') { callBackFunction(); }
        }
    });
}

function refreshSessionClock() {
    setInterval(function(){ jsonAction('bizuno/main/sessionRefresh'); }, 240000);
}

// LOAD BROWSER USER DEFAULTS
if (typeof sessionStorage.bizuno !== 'undefined') {
    bizDefaults = JSON.parse(sessionStorage.getItem('bizuno'));
} else if (bizID >= 0) { // this happens when first logging in OR opening a new tab in the browser manually
    reloadSessionStorage();
} else if (typeof bizDefaults.currency==='undefined') { // issue loading cache try again
    reloadSessionStorage();
} 

jqBiz.cachedScript = function( url, options ) {
    options = jqBiz.extend( options || {}, { dataType: "script", cache: true, url: url });
    return jqBiz.ajax( options );
};

jqBiz.fn.serializeObject = function() {
    var o = {};
    var a = this.serializeArray();
    jqBiz.each(a, function() {
        if (o[this.name] !== undefined) {
            if (!o[this.name].push) {
                o[this.name] = [o[this.name]];
            }
            o[this.name].push(this.value || '');
        } else {
            o[this.name] = this.value || '';
        }
    });
    return o;
};

jqBiz.extend(jqBiz.fn.textbox.methods, {
    show: function(jqy) { return jqy.each(function() { jqBiz(this).next().show(); }); },
    hide: function(jqy) { return jqy.each(function() { jqBiz(this).next().hide(); }); }
});

jqBiz.fn.textWidth = function(text, font) {
    if (!jqBiz.fn.textWidth.fakeEl) jqBiz.fn.textWidth.fakeEl = jqBiz('<span>').hide().appendTo(document.body);
    jqBiz.fn.textWidth.fakeEl.text(text || this.val() || this.text()).css('font', font || this.css('font'));
    return jqBiz.fn.textWidth.fakeEl.width();
};

// add clear button to datebox, need to add following to each datebox after init: jqBiz('#dd').datebox({ buttons:buttons });
var buttons = jqBiz.extend([], jqBiz.fn.datebox.defaults.buttons);
buttons.splice(1, 0, {
    text: 'Clear',
    handler: function(target){ jqBiz(target).datebox('clear'); }
});
jqBiz.fn.datebox.defaults.formatter  = function(date) { return formatDate(date); };
jqBiz.fn.datebox.defaults.parser     = function(sDate){
    if (!sDate) { return new Date(); }
    if (typeof sDate === 'integer' || typeof sDate === 'object') {
        sDate = formatDate(sDate);
    }
    var sep = '.'; // determine the separator, choices are /, -, and .
    var idx = bizDefaults.calendar.format.indexOf('.');
    if (idx === -1) {
        sep = '-';
        idx = bizDefaults.calendar.format.indexOf('-');
        if (idx === -1) sep = '/';
    }
    var pos = bizDefaults.calendar.format.split(sep);
    var ss  = sDate.split(sep);
    d = [];
    for (var i=0; i<3; i++) d[pos[i]] = parseInt(ss[i],10);
    if (!isNaN(d['Y']) && !isNaN(d['m']) && !isNaN(d['d'])){
        return new Date(d['Y'],d['m']-1,d['d']);
    } else {
        return new Date();
    }
};
//jqBiz.fn.datagrid.defaults.striped   = true; // causes row formatter to skip every other row. bad for using color for status
jqBiz.fn.datagrid.defaults.fitColumns  = true;
jqBiz.fn.datagrid.defaults.pagination  = true;
jqBiz.fn.datagrid.defaults.singleSelect= true;
jqBiz.fn.datagrid.defaults.scrollbarSize = 0; // since we use pagination, there are no scroll bars, let the browser provide them, just takes up space.
jqBiz.fn.window.defaults.minimizable   = false;
jqBiz.fn.window.defaults.collapsible   = false,
jqBiz.fn.window.defaults.maximizable   = false;
if (typeof bizDefaults.locale !== 'undefined') {
    jqBiz.fn.numberbox.defaults.precision       = bizDefaults.locale.precision;
    jqBiz.fn.numberbox.defaults.decimalSeparator= bizDefaults.locale.decimal;
    jqBiz.fn.numberbox.defaults.groupSeparator  = bizDefaults.locale.thousand;
    jqBiz.fn.numberbox.defaults.prefix          = bizDefaults.locale.prefix;
    jqBiz.fn.numberbox.defaults.suffix          = bizDefaults.locale.suffix;
}
//setCurrency(bizDefaults.currency.defaultCur); // makes all numbrerboxes in currency format

jqBiz.extend(jqBiz.fn.datagrid.defaults.editors, {
    color: {
        init: function(container, options){
            var input = jqBiz('<input type="text">').appendTo(container);
            return input.color(options);
        },
        destroy:  function(target){ jqBiz(target).color('destroy'); },
        getValue: function(target){ return jqBiz(target).color('getValue'); },
        setValue: function(target, value){ jqBiz(target).color('setValue',value); },
    },
    numberbox: {
        init: function(container, options){
            var input = jqBiz('<input type="text">').appendTo(container);
            return input.numberbox(options);
        },
        destroy:  function(target){ jqBiz(target).numberbox('destroy'); },
        getValue: function(target){ return jqBiz(target).numberbox('getValue'); },
        setValue: function(target, value){ jqBiz(target).numberbox('setValue',value); }
    },
    numberspinner: {
        init: function(container, options){
            var input = jqBiz('<input type="text">').appendTo(container);
            return input.numberspinner(options);
        },
        destroy:  function(target){ jqBiz(target).numberspinner('destroy'); },
        getValue: function(target){ return jqBiz(target).numberspinner('getValue'); },
        setValue: function(target, value){ jqBiz(target).numberspinner('setValue',value); },
        resize:   function(target, width){ jqBiz(target).numberspinner('resize',width); }
    },
    combogrid: {
        init: function(container, options){
            var input = jqBiz('<input type="text" class="datagrid-editable-input">').appendTo(container);
            input.combogrid(options);
            return input;
        },
        destroy:  function(target)       { jqBiz(target).combogrid('destroy'); },
        getValue: function(target)       { return jqBiz(target).combogrid('getValue'); },
        setValue: function(target, value){ jqBiz(target).combogrid('setValue', value); },
        resize:   function(target, width){ jqBiz(target).combogrid('resize',width); }
    },
    switchbutton:{
        init: function(container, options){
            var input = jqBiz('<input>').appendTo(container);
            input.switchbutton(options);
            return input;
        },
        getValue: function(target)       { return jqBiz(target).switchbutton('options').checked ? 'on' : 'off'; },
        setValue: function(target, value){ jqBiz(target).switchbutton(value=='on'?'check':'uncheck'); },
        resize:   function(target, width){ jqBiz(target).switchbutton('resize', {width: width,height:22}); }
    }
});

function bizPagerFilter(data){
    if (jqBiz.isArray(data)){    // is array
        data = { total: data.length, rows: data };
    }
    var target = this;
    var dg = jqBiz(target);
    var state = dg.data('datagrid');
    var opts = dg.datagrid('options');
    if (!state.allRows) { state.allRows = (data.rows); }
    if (!opts.remoteSort && opts.sortName){
        var names = opts.sortName.split(',');
        var orders = opts.sortOrder.split(',');
        state.allRows.sort(function(r1,r2){
            var r = 0;
            for(var i=0; i<names.length; i++){
                var sn = names[i];
                var so = orders[i];
                var col = jqBiz(target).datagrid('getColumnOption', sn);
                var sortFunc = col.sorter || function(a,b) { return a==b ? 0 : (a>b?1:-1); };
                r = sortFunc(r1[sn], r2[sn]) * (so=='asc'?1:-1);
                if (r != 0) { return r; }
            }
            return r;
        });
    }
    var start = (opts.pageNumber-1)*parseInt(opts.pageSize);
    var end = start + parseInt(opts.pageSize);
    data.rows = state.allRows.slice(start, end);
    return data;
}

var loadDataMethod = jqBiz.fn.datagrid.methods.loadData;
jqBiz.extend(jqBiz.fn.datagrid.methods, {
    disableDnd: function(jqy,index) {
        return jqy.each(function() {
            var trs;
            var target = this;
            var opts = jqBiz(this).datagrid('options');
            if (index != undefined) { trs = opts.finder.getTr(this, index); }
            else { trs = opts.finder.getTr(this, 0, 'allbody'); }
            trs.draggable('disable');
        });
    },
    clientPaging: function(jqy) {
        return jqy.each(function() {
            var dg = jqBiz(this);
            var state = dg.data('datagrid');
            var opts = state.options;
            opts.loadFilter = bizPagerFilter;
            var onBeforeLoad = opts.onBeforeLoad;
            opts.onBeforeLoad = function(param){
                state.allRows = null;
                return onBeforeLoad.call(this, param);
            };
            dg.datagrid('getPager').pagination({
                onSelectPage:function(pageNum, pageSize){
                    opts.pageNumber = pageNum;
                    opts.pageSize = pageSize;
                    jqBiz(this).pagination('refresh',{
                        pageNumber:pageNum,
                        pageSize:pageSize
                    });
                    dg.datagrid('loadData',state.allRows);
                }
            });
            jqBiz(this).datagrid('loadData', state.data);
            if (opts.url){
                jqBiz(this).datagrid('reload');
            }
        });
    },
    loadData: function(jqy, data) {
        jqy.each(function(){ jqBiz(this).data('datagrid').allRows = null; });
        return loadDataMethod.call(jqBiz.fn.datagrid.methods, jqy, data);
    },
    getAllRows: function(jqy) {
        return jqy.data('datagrid').allRows;
    }
});

jqBiz.extend(jqBiz.fn.combogrid.methods, {
    attachEvent: function(jqy, param){
        return jqy.each(function(){
            var grid = jqBiz(this).combogrid('grid');
            var opts = jqBiz(this).combogrid('options');
            opts.handlers = opts.handlers || {};
            var cbs = opts.handlers[param.event];
            if (cbs){
                cbs.push(param.handler);
            } else {
                cbs = [opts[param.event], param.handler];
            }
            opts.handlers[param.event] = cbs;
            opts[param.event] = grid.datagrid('options')[param.event] = function(){
                var target = this;
                var args = arguments;
                jqBiz.each(opts.handlers[param.event], function(i,h){
                    h.apply(target, args);
                });
            };
        });
    }
});

/**
 * datagrid detailview
 * From default extension
 * First - replace jq with jqy
 * Second - replace $ with jq
 */
jqBiz.extend(jqBiz.fn.datagrid.defaults, {
	autoUpdateDetail: true  // Define if update the row detail content when update a row
});

var detailview = jqBiz.extend({}, jqBiz.fn.datagrid.defaults.view, {
	type: 'detailview',
	render: function(target, container, frozen){
		var state = jqBiz.data(target, 'datagrid');
		var opts = state.options;
		if (frozen){
			if (!(opts.rownumbers || (opts.frozenColumns && opts.frozenColumns.length))){
				return;
			}
		}

		var rows = state.data.rows;
		var fields = jqBiz(target).datagrid('getColumnFields', frozen);
		var table = [];
		table.push('<table class="datagrid-btable" cellspacing="0" cellpadding="0" border="0"><tbody>');
		for(var i=0; i<rows.length; i++) {
			// get the class and style attributes for this row
			var css = opts.rowStyler ? opts.rowStyler.call(target, i, rows[i]) : '';
			var classValue = '';
			var styleValue = '';
			if (typeof css == 'string'){
				styleValue = css;
			} else if (css){
				classValue = css['class'] || '';
				styleValue = css['style'] || '';
			}

			var cls = 'class="datagrid-row ' + (i % 2 && opts.striped ? 'datagrid-row-alt ' : ' ') + classValue + '"';
			var style = styleValue ? 'style="' + styleValue + '"' : '';
			var rowId = state.rowIdPrefix + '-' + (frozen?1:2) + '-' + i;
			table.push('<tr id="' + rowId + '" datagrid-row-index="' + i + '" ' + cls + ' ' + style + '>');
			table.push(this.renderRow.call(this, target, fields, frozen, i, rows[i]));
			table.push('</tr>');

			table.push('<tr style="display:none;">');
			if (frozen){
				table.push('<td colspan=' + (fields.length+(opts.rownumbers?1:0)) + ' style="border-right:0">');
			} else {
				table.push('<td colspan=' + (fields.length) + '>');
			}

			table.push('<div class="datagrid-row-detail">');
			if (frozen){
				table.push('&nbsp;');
			} else {
				table.push(opts.detailFormatter.call(target, i, rows[i]));
			}
			table.push('</div>');

			table.push('</td>');
			table.push('</tr>');

		}
		table.push('</tbody></table>');

		jqBiz(container).html(table.join(''));
	},

	renderRow: function(target, fields, frozen, rowIndex, rowData){
		var opts = jqBiz.data(target, 'datagrid').options;

		var cc = [];
		if (frozen && opts.rownumbers){
			var rownumber = rowIndex + 1;
			if (opts.pagination){
				rownumber += (opts.pageNumber-1)*opts.pageSize;
			}
			cc.push('<td class="datagrid-td-rownumber"><div class="datagrid-cell-rownumber">'+rownumber+'</div></td>');
		}
		for(var i=0; i<fields.length; i++){
			var field = fields[i];
			var col = jqBiz(target).datagrid('getColumnOption', field);
			if (col){
				var value = rowData[field];	// the field value
				var css = col.styler ? (col.styler(value, rowData, rowIndex)||'') : '';
				var classValue = '';
				var styleValue = '';
				if (typeof css == 'string'){
					styleValue = css;
				} else if (cc){
					classValue = css['class'] || '';
					styleValue = css['style'] || '';
				}
				var cls = classValue ? 'class="' + classValue + '"' : '';
				var style = col.hidden ? 'style="display:none;' + styleValue + '"' : (styleValue ? 'style="' + styleValue + '"' : '');

				cc.push('<td field="' + field + '" ' + cls + ' ' + style + '>');

				if (col.checkbox){
					style = '';
				} else if (col.expander){
					style = "text-align:center;height:16px;";
				} else {
					style = styleValue;
					if (col.align){style += ';text-align:' + col.align + ';'}
					if (!opts.nowrap){
						style += ';white-space:normal;height:auto;';
					} else if (opts.autoRowHeight){
						style += ';height:auto;';
					}
				}

				cc.push('<div style="' + style + '" ');
				if (col.checkbox){
					cc.push('class="datagrid-cell-check ');
				} else {
					cc.push('class="datagrid-cell ' + col.cellClass);
				}
				cc.push('">');

				if (col.checkbox){
					cc.push('<input type="checkbox" name="' + field + '" value="' + (value!=undefined ? value : '') + '">');
				} else if (col.expander) {
					//cc.push('<div style="text-align:center;width:16px;height:16px;">');
					cc.push('<span class="datagrid-row-expander datagrid-row-expand" style="display:inline-block;width:16px;height:16px;margin:0;cursor:pointer;" />');
					//cc.push('</div>');
				} else if (col.formatter){
					cc.push(col.formatter(value, rowData, rowIndex));
				} else {
					cc.push(value);
				}

				cc.push('</div>');
				cc.push('</td>');
			}
		}
		return cc.join('');
	},

	insertRow: function(target, index, row){
		var opts = jqBiz.data(target, 'datagrid').options;
		var dc = jqBiz.data(target, 'datagrid').dc;
		var panel = jqBiz(target).datagrid('getPanel');
		var view1 = dc.view1;
		var view2 = dc.view2;

		var isAppend = false;
		var rowLength = jqBiz(target).datagrid('getRows').length;
		if (rowLength == 0){
			jqBiz(target).datagrid('loadData',{total:1,rows:[row]});
			return;
		}

		if (index == undefined || index == null || index >= rowLength) {
			index = rowLength;
			isAppend = true;
			this.canUpdateDetail = false;
		}

		jqBiz.fn.datagrid.defaults.view.insertRow.call(this, target, index, row);

		_insert(true);
		_insert(false);

		this.canUpdateDetail = true;

		function _insert(frozen){
			var tr = opts.finder.getTr(target, index, 'body', frozen?1:2);
			if (isAppend){
				var detail = tr.next();
				var newDetail = tr.next().clone();
				tr.insertAfter(detail);
			} else {
				var newDetail = tr.next().next().clone();
			}
			newDetail.insertAfter(tr);
			newDetail.hide();
			if (!frozen){
				newDetail.find('div.datagrid-row-detail').html(opts.detailFormatter.call(target, index, row));
			}
		}
	},

	deleteRow: function(target, index){
		var opts = jqBiz.data(target, 'datagrid').options;
		var dc = jqBiz.data(target, 'datagrid').dc;
		var tr = opts.finder.getTr(target, index);
		tr.next().remove();
		jqBiz.fn.datagrid.defaults.view.deleteRow.call(this, target, index);
		dc.body2.triggerHandler('scroll');
	},

	updateRow: function(target, rowIndex, row){
		var dc = jqBiz.data(target, 'datagrid').dc;
		var opts = jqBiz.data(target, 'datagrid').options;
		var cls = jqBiz(target).datagrid('getExpander', rowIndex).attr('class');
		jqBiz.fn.datagrid.defaults.view.updateRow.call(this, target, rowIndex, row);
		jqBiz(target).datagrid('getExpander', rowIndex).attr('class',cls);

		// update the detail content
		if (opts.autoUpdateDetail && this.canUpdateDetail){
			var row = jqBiz(target).datagrid('getRows')[rowIndex];
			var detail = jqBiz(target).datagrid('getRowDetail', rowIndex);
			detail.html(opts.detailFormatter.call(target, rowIndex, row));
		}
	},

	bindEvents: function(target){
		var state = jqBiz.data(target, 'datagrid');

		if (state.ss.bindDetailEvents){return;}
		state.ss.bindDetailEvents = true;

		var dc = state.dc;
		var opts = state.options;
		var body = dc.body1.add(dc.body2);
		var clickHandler = (jqBiz.data(body[0],'events')||jqBiz._data(body[0],'events')).click[0].handler;
		body.unbind('click.detailview').bind('click.detailview', function(e){
			var tt = jqBiz(e.target);
			var tr = tt.closest('tr.datagrid-row');
			if (!tr.length){return}
			if (tt.hasClass('datagrid-row-expander')){
				var rowIndex = parseInt(tr.attr('datagrid-row-index'));
				if (tt.hasClass('datagrid-row-expand')){
					jqBiz(target).datagrid('expandRow', rowIndex);
				} else {
					jqBiz(target).datagrid('collapseRow', rowIndex);
				}
				jqBiz(target).datagrid('fixRowHeight');
				e.stopPropagation();

			} else {
				// clickHandler(e);
			}
		});
	},

	onBeforeRender: function(target){
		var state = jqBiz.data(target, 'datagrid');
		var opts = state.options;
		var dc = state.dc;
		var t = jqBiz(target);
		var hasExpander = false;
		var fields = t.datagrid('getColumnFields',true).concat(t.datagrid('getColumnFields'));
		for(var i=0; i<fields.length; i++){
			var col = t.datagrid('getColumnOption', fields[i]);
			if (col.expander){
				hasExpander = true;
				break;
			}
		}
		if (!hasExpander){
			if (opts.frozenColumns && opts.frozenColumns.length){
				opts.frozenColumns[0].splice(0,0,{field:'_expander',expander:true,width:24,resizable:false,fixed:true});
			} else {
				opts.frozenColumns = [[{field:'_expander',expander:true,width:24,resizable:false,fixed:true}]];
			}

			var t = dc.view1.children('div.datagrid-header').find('table');
			var td = jqBiz('<td rowspan="'+opts.frozenColumns.length+'"><div class="datagrid-header-expander" style="width:24px;"></div></td>');
			if (jqBiz('tr',t).length == 0){
				td.wrap('<tr></tr>').parent().appendTo(jqBiz('tbody',t));
			} else if (opts.rownumbers){
				td.insertAfter(t.find('td:has(div.datagrid-header-rownumber)'));
			} else {
				td.prependTo(t.find('tr:first'));
			}
		}

		// if (!state.bindDetailEvents){
		// 	state.bindDetailEvents = true;
		// 	var that = this;
		// 	setTimeout(function(){
		// 		that.bindEvents(target);
		// 	},0);
		// }
	},

	onAfterRender: function(target){
		var that = this;
		var state = jqBiz.data(target, 'datagrid');
		var dc = state.dc;
		var opts = state.options;
		var panel = jqBiz(target).datagrid('getPanel');

		jqBiz.fn.datagrid.defaults.view.onAfterRender.call(this, target);

		if (!state.onResizeColumn){
			state.onResizeColumn = opts.onResizeColumn;
			opts.onResizeColumn = function(field, width){
				if (!opts.fitColumns){
					resizeDetails();
				}
				var rowCount = jqBiz(target).datagrid('getRows').length;
				for(var i=0; i<rowCount; i++){
					jqBiz(target).datagrid('fixDetailRowHeight', i);
				}

				// call the old event code
				state.onResizeColumn.call(target, field, width);
			};
		}
		if (!state.onResize){
			state.onResize = opts.onResize;
			opts.onResize = function(width, height){
				if (opts.fitColumns){
					resizeDetails();
				}
				state.onResize.call(panel, width, height);
			};
		}

		// function resizeDetails(){
		// 	var details = dc.body2.find('>table.datagrid-btable>tbody>tr>td>div.datagrid-row-detail:visible');
		// 	if (details.length){
		// 		var ww = 0;
		// 		dc.header2.find('.datagrid-header-check:visible,.datagrid-cell:visible').each(function(){
		// 			ww += jqBiz(this).outerWidth(true) + 1;
		// 		});
		// 		if (ww != details.outerWidth(true)){
		// 			details._outerWidth(ww);
		// 			details.find('.easyui-fluid').trigger('_resize');
		// 		}
		// 	}
		// }
		function resizeDetails(){
			var details = dc.body2.find('>table.datagrid-btable>tbody>tr>td>div.datagrid-row-detail:visible');
			if (details.length){
				var ww = 0;
				// dc.header2.find('.datagrid-header-check:visible,.datagrid-cell:visible').each(function(){
				// 	ww += jqBiz(this).outerWidth(true) + 1;
				// });
				dc.body2.find('>table.datagrid-btable>tbody>tr:visible:first').find('.datagrid-cell-check:visible,.datagrid-cell:visible').each(function(){
					ww += jqBiz(this).outerWidth(true) + 1;
				});
				if (ww != details.outerWidth(true)){
					details._outerWidth(ww);
					details.find('.easyui-fluid').trigger('_resize');
				}
			}
		}


		this.canUpdateDetail = true;	// define if to update the detail content when 'updateRow' method is called;

		var footer = dc.footer1.add(dc.footer2);
		footer.find('span.datagrid-row-expander').css('visibility', 'hidden');
		jqBiz(target).datagrid('resize');

		this.bindEvents(target);
		var detail = dc.body1.add(dc.body2).find('div.datagrid-row-detail');
		detail.unbind().bind('mouseover mouseout click dblclick contextmenu scroll', function(e){
			e.stopPropagation();
		});
	}
});

jqBiz.extend(jqBiz.fn.datagrid.methods, {
	fixDetailRowHeight: function(jqy, index){
		return jqy.each(function(){
			var opts = jqBiz.data(this, 'datagrid').options;
			if (!(opts.rownumbers || (opts.frozenColumns && opts.frozenColumns.length))){
				return;
			}
			var dc = jqBiz.data(this, 'datagrid').dc;
			var tr1 = opts.finder.getTr(this, index, 'body', 1).next();
			var tr2 = opts.finder.getTr(this, index, 'body', 2).next();
			// fix the detail row height
			if (tr2.is(':visible')){
				tr1.css('height', '');
				tr2.css('height', '');
				var height = Math.max(tr1.height(), tr2.height());
				tr1.css('height', height);
				tr2.css('height', height);
			}
			dc.body2.triggerHandler('scroll');
		});
	},
	getExpander: function(jqy, index){	// get row expander object
		var opts = jqBiz.data(jqy[0], 'datagrid').options;
		return opts.finder.getTr(jqy[0], index).find('span.datagrid-row-expander');
	},
	// get row detail container
	getRowDetail: function(jqy, index){
		var opts = jqBiz.data(jqy[0], 'datagrid').options;
		var tr = opts.finder.getTr(jqy[0], index, 'body', 2);
		// return tr.next().find('div.datagrid-row-detail');
		return tr.next().find('>td>div.datagrid-row-detail');
	},
	expandRow: function(jqy, index){
		return jqy.each(function(){
			var opts = jqBiz(this).datagrid('options');
			var dc = jqBiz.data(this, 'datagrid').dc;
			var expander = jqBiz(this).datagrid('getExpander', index);
			if (expander.hasClass('datagrid-row-expand')){
				expander.removeClass('datagrid-row-expand').addClass('datagrid-row-collapse');
				var tr1 = opts.finder.getTr(this, index, 'body', 1).next();
				var tr2 = opts.finder.getTr(this, index, 'body', 2).next();
				tr1.show();
				tr2.show();
				jqBiz(this).datagrid('fixDetailRowHeight', index);
				if (opts.onExpandRow){
					var row = jqBiz(this).datagrid('getRows')[index];
					opts.onExpandRow.call(this, index, row);
				}
			}
		});
	},
	collapseRow: function(jqy, index){
		return jqy.each(function(){
			var opts = jqBiz(this).datagrid('options');
			var dc = jqBiz.data(this, 'datagrid').dc;
			var expander = jqBiz(this).datagrid('getExpander', index);
			if (expander.hasClass('datagrid-row-collapse')){
				expander.removeClass('datagrid-row-collapse').addClass('datagrid-row-expand');
				var tr1 = opts.finder.getTr(this, index, 'body', 1).next();
				var tr2 = opts.finder.getTr(this, index, 'body', 2).next();
				tr1.hide();
				tr2.hide();
				dc.body2.triggerHandler('scroll');
				if (opts.onCollapseRow){
					var row = jqBiz(this).datagrid('getRows')[index];
					opts.onCollapseRow.call(this, index, row);
				}
			}
		});
	}
});

jqBiz.extend(jqBiz.fn.datagrid.methods, {
	subgrid: function(jqy, conf){
		return jqy.each(function(){
			createGrid(this, conf);

			function createGrid(target, conf, prow){
				var queryParams = jqBiz.extend({}, conf.options.queryParams||{});
				// queryParams[conf.options.foreignField] = prow ? prow[conf.options.foreignField] : undefined;
				if (prow){
					var fk = conf.options.foreignField;
					if (jqBiz.isFunction(fk)){
						jqBiz.extend(queryParams, fk.call(conf, prow));
					} else {
						queryParams[fk] = prow[fk];
					}
				}

				var plugin = conf.options.edatagrid ? 'edatagrid' : 'datagrid';

				jqBiz(target)[plugin](jqBiz.extend({}, conf.options, {
					subgrid: conf.subgrid,
					view: (conf.subgrid ? detailview : undefined),
					queryParams: queryParams,
					detailFormatter: function(index, row){
						return '<div><table class="datagrid-subgrid"></table></div>';
					},
					onExpandRow: function(index, row){
						var opts = jqBiz(this).datagrid('options');
						var rd = jqBiz(this).datagrid('getRowDetail', index);
						var dg = getSubGrid(rd);
						if (!dg.data('datagrid')){
							createGrid(dg[0], opts.subgrid, row);
						}
						rd.find('.easyui-fluid').trigger('_resize');
						setHeight(this, index);
						if (conf.options.onExpandRow){
							conf.options.onExpandRow.call(this, index, row);
						}
					},
					onCollapseRow: function(index, row){
						setHeight(this, index);
						if (conf.options.onCollapseRow){
							conf.options.onCollapseRow.call(this, index, row);
						}
					},
					onResize: function(){
						var dg = jqBiz(this).children('div.datagrid-view').children('table')
						setParentHeight(this);
					},
					onResizeColumn: function(field, width){
						setParentHeight(this);
						if (conf.options.onResizeColumn){
							conf.options.onResizeColumn.call(this, field, width);
						}
					},
					onLoadSuccess: function(data){
						setParentHeight(this);
						if (conf.options.onLoadSuccess){
							conf.options.onLoadSuccess.call(this, data);
						}
					}
				}));
			}
			function getSubGrid(rowDetail){
				var div = jqBiz(rowDetail).children('div');
				if (div.children('div.datagrid').length){
					return div.find('>div.datagrid>div.panel-body>div.datagrid-view>table.datagrid-subgrid');
				} else {
					return div.find('>table.datagrid-subgrid');
				}
			}
			function setParentHeight(target){
				var tr = jqBiz(target).closest('div.datagrid-row-detail').closest('tr').prev();
				if (tr.length){
					var index = parseInt(tr.attr('datagrid-row-index'));
					var dg = tr.closest('div.datagrid-view').children('table');
					setHeight(dg[0], index);
				}
			}
			function setHeight(target, index){
				jqBiz(target).datagrid('fixDetailRowHeight', index);
				jqBiz(target).datagrid('fixRowHeight', index);
				var tr = jqBiz(target).closest('div.datagrid-row-detail').closest('tr').prev();
				if (tr.length){
					var index = parseInt(tr.attr('datagrid-row-index'));
					var dg = tr.closest('div.datagrid-view').children('table');
					setHeight(dg[0], index);
				}
			}
		});
	},
	getSelfGrid: function(jqy){
		var grid = jqy.closest('.datagrid');
		if (grid.length){
			return grid.find('>.datagrid-wrap>.datagrid-view>.datagrid-f');
		} else {
			return null;
		}
	},
	getParentGrid: function(jqy){
		var detail = jqy.closest('div.datagrid-row-detail');
		if (detail.length){
			return detail.closest('.datagrid-view').children('.datagrid-f');
		} else {
			return null;
		}
	},
	getParentRowIndex: function(jqy){
		var detail = jqy.closest('div.datagrid-row-detail');
		if (detail.length){
			var tr = detail.closest('tr').prev();
			return parseInt(tr.attr('datagrid-row-index'));
		} else {
			return -1;
		}
	}
});

function bizLangJS(index) {
    if (typeof bizDefaults === 'undefined')            { return index; }
    if (typeof bizDefaults.dictionary === 'undefined') { return index; }
    return typeof bizDefaults.dictionary[index] !== 'undefined' ? bizDefaults.dictionary[index] : index;
}

/*
 * This function will search all columns in a combo in place of the standard search only by text field
 * @param array data - original data to search (need to go backwards)
 * @param string q - search string
 * @returns array - filtered data
 */
function glComboSearch(q) {
    var newRows = [];
    jqBiz.map(bizDefaults.glAccounts.rows, function(row) {
        for (var p in row) {
            var v = row[p];
            var regExp = new RegExp(q, 'i'); // i - makes the search case-insensitive.
            if (regExp.test(String(v))) {
                newRows.push(row);
                break;
            }
        }
    });
    var comboEd = jqBiz('#dgJournalItem').datagrid('getEditor', {index:curIndex,field:'gl_account'});
    var g = jqBiz(comboEd.target).combogrid('grid');
    g.datagrid('loadData', newRows);
    jqBiz(comboEd.target).combogrid('showPanel');
    jqBiz(comboEd.target).combogrid('setText', q);
}

/**
 * Detects if a mobile device
 * @returns {undefined}
 */
function isMobile() {
    const userAgent = navigator.userAgent || navigator.vendor || window.opera;
    if (/android|iphone|ipad|ipod|blackberry|iemobile|opera mini/i.test(userAgent)) { // Mobile devices
        // Further differentiate between phone and tablet based on screen size or specific keywords
        if (/ipad/i.test(userAgent) || (window.innerWidth > 768 && window.innerWidth <= 1024)) {
            return false; // tablet
        } else {
            return true; // mobile
        }
    }
    // Tablets (additional checks beyond user agent)
    // This can be less reliable due to varying screen sizes and user agents
    if (/tablet|ipad/i.test(userAgent) || (navigator.maxTouchPoints > 0 && window.innerWidth > 768 && window.innerWidth <= 1024)) {
        return false; // Tablet
    }
    return false; // Desktop
}

function hrefClick(path, rID, jData) {
    if  (typeof path == 'undefined') return alert('ERROR: The destination path is required, no value was provided.');
    var pathClean = path.replace(/&amp;/g,"&");
    var remoteURL = pathClean==='' ? bizunoHome : bizunoHome+'?bizRt='+pathClean;
    if (typeof rID   != 'undefined') remoteURL += '&rID='+rID;
    if (typeof jData != 'undefined') remoteURL += '&data='+encodeURIComponent(jData);
    window.location = remoteURL;
}

function jsonAction(path, rID, jData) {
    if  (typeof path == 'undefined') return alert('ERROR: The destination route is required, no value was provided.');
    var pathClean = path.replace(/&amp;/g,"&");
    var remoteURL = bizunoAjax+'&bizRt='+pathClean;
    if (typeof rID   != 'undefined') remoteURL += '&rID='+rID;
    if (typeof jData != 'undefined') remoteURL += '&data='+encodeURIComponent(jData);
    jqBiz.ajax({ type:'GET', url:remoteURL, success:function (data) { processJson(data); } });
    return false;
}

function menuAction(item) {
    if (typeof item.action === 'undefined') { item.action = 'page'; }
    switch (item.action) {
        default:
        case 'page':   hrefClick(item.route);                                   break;
        case 'iframe': windowEdit(item.route, 'myFrame', item.text, 1000, 500); break;
        case 'window': alert('Redirecting to a new window');                    break;
        case 'popup':  alert('GFenerating a popup');                            break;
    }
}

function jsonPortal(path, rID, jData) {
    if  (typeof path == 'undefined') return alert('ERROR: The destination route is required, no value was provided.');
    var pathClean = path.replace(/&amp;/g,"&");
    var remoteURL = portalAjax+'&bizRt='+pathClean;
    if (typeof rID   != 'undefined') remoteURL += '&rID='+rID;
    if (typeof jData != 'undefined') remoteURL += '&data='+encodeURIComponent(jData);
    jqBiz.ajax({ type:'GET', url:remoteURL, success:function (data) { processJson(data); } });
    return false;
}

function winOpen(id, path, w, h) {
    if (!w) w = 800;
    if (!h) h = 650;
    var popupWin = window.open(bizunoHome+"?bizRt="+path, id, 'width='+w+',height='+h+',resizable=1,scrollbars=1,top=150,left=200');
    if (popupWin==null) {
        jqBiz.messager.alert({ title:'Popup Blocked!',
            msg: 'Your browser has blocked the popup! Please make sure you allow popups from this site or some browsers require an user click action to process the popup. Press OK to try again.',
            fn: function() { var popupWin = window.open(bizunoHome+"?bizRt="+path, id, 'width='+w+',height='+h+',resizable=1,scrollbars=1,top=150,left=200');
                if (popupWin==null) { alert('The popup is still blocked. You will need to perform this function another way or use a different browser.'); return; }
            }
        });
    }
    popupWin.focus();
}

function winHref(path, id) {
    if (isMobile()) { // tabs not possible so just reload new url
        window.location = path;
    } else {
        var popupWin = window.open(path, id);
        if (popupWin==null) { alert('Popup blocked!'); return; }
        popupWin.focus();
    }
}

function accordionEdit(accID, dgID, divID, title, path, rID, action) {
//alert('accID = '+accID+' and dgID = '+dgID+' and divID = '+divID+' and title = '+title+' and path = '+path+' and rID = '+rID+' and action = '+action);
    var xVars = path+'&rID='+rID;
    if (typeof action != 'undefined') { xVars += '&bizAction='+action; } // do not know if this is used?
    jqBiz('#'+dgID).datagrid('loaded');
    jqBiz('#'+divID).panel({href:bizunoAjax+'&bizRt='+xVars});
    jqBiz('#'+accID).accordion('select', title);
}

/**
 * This function opens a window and then loads the contents remotely
 * @param {type} href
 * @param {type} id
 * @param {type} winTitle
 * @param {type} width
 * @param {type} height
 * @returns {undefined}
 */
function windowEdit(href, id, winTitle, width, height) {
    processJson( { action:'window', id:id, title:winTitle, href:bizunoAjax+'&bizRt='+href, height:height, width:width } );
}

/**
 * This function prepares a form to be submited via ajax
 * @param string formID - form ID to be submitted
 * @param boolean skipPre - set to true to skip the preCheck before submit
 * @returns false - but submits the form data via AJAX if all test pass
 */
function ajaxForm(formID, skipPre) {
    jqBiz('#'+formID).submit(function (e) {
        e.preventDefault();
        e.stopImmediatePropagation(); // may have to uncomment this to prevent double submits
        if ('function' == typeof preSubmit && ('undefined' == typeof skipPre || false == skipPre)) {
            var passed = preSubmit();
            if (!passed) return false; // pre-submit js checking
        }
        var frmData = new FormData(this);
        // Patch for Safari 11+ browsers hanging on forms submits with EMPTY file fields.
//      if (navigator.userAgent.indexOf('Safari') !== -1) { jqBiz('#'+formID).find("input[type=file]").each(function(index, field) { if (jqBiz('#'+field.id).val() == '') { frmData.delete(field.id); } }); }
        jqBiz.ajax({
            url:        jqBiz('#'+formID).attr('action'),
            type:       'post', // breaks with GET
            data:       frmData,
            mimeType:   'multipart/form-data',
            contentType:false,
            processData:false,
            cache:      false,
            success:    function (data) { processJson(data); }
        });
        return false;
    });
}

/**
 * This function uses the jquery plugin filedownload to perform a controlled file download with error messages if a failure occurs
 */
function ajaxDownload(formID) {
    jqBiz('#'+formID).submit(function (e) {
        jqBiz.fileDownload(jqBiz(this).attr('action'), {
            failCallback: function (response, url) { processJson(JSON.parse(response)); },
            httpMethod: 'POST',
            data: jqBiz(this).serialize()
        });
        e.preventDefault();
    });
}

/**
 * This function submits the input fields within a given div element
 */
function divSubmit(path, id) {
    divData = jqBiz("#"+id).find("select, textarea, input").serialize();
//  divData = jqBiz('#'+id+' :input').serializeObject(); // misses textarea?
    jqBiz.ajax({
        url:     bizunoAjax+'&bizRt='+path,
        type:    'post',
        data:    divData,
        mimeType:'multipart/form-data',
        cache:   false,
        success: function (data) { processJson(data); }
    });
}

/**
 * This function processes the returned json data array
 */
function processJson(json) {
    jqBiz('body').removeClass('loading');
    if (!json) return false;
    if ( json.message) displayMessage(json.message);
    if ( json.extras)  eval(json.extras);
    switch (json.action) {
        case 'chart':   drawBizunoChart(json.actionData); break;
        case 'divHTML': jqBiz('#'+json.divID).html(json.html).text();  break;
        case 'eval':    if (json.actionData) eval(json.actionData); break;
        case 'href':    if (json.link) window.location = json.link.replace(/&amp;/g,"&");      break;
        case 'newDiv':
            var newdiv1 = jqBiz(json.html);
            jqBiz('#navPopup').html(newdiv1);
            break;
        case 'window':
            var title = typeof json.title!== 'undefined' ? json.title: ' ';
            if (isMobile()) {
                var iconBack = '<span data-options="menuAlign:\'left\'" title="Back" class="easyui-linkbutton iconL-back" style="border:0;display:inline-block;vertical-align:middle;height:32px;min-width:32px;" onclick="jqBiz.mobile.back();">&nbsp;</span></div>';
                var html     = '<header><div class="m-toolbar"><div class="m-title">'+title+'</div><div class="m-left">'+iconBack+'</div></div></header>';
                html += '<div id="navPopupBody">'+(typeof json.html !== 'undefined' ? json.html : '')+'</div>';
                jqBiz('#navPopup').html(html);
                jqBiz.mobile.go('#navPopup');
                if (typeof json.href !== 'undefined') { jqBiz('#navPopupBody').load(json.href, function() { jqBiz.parser.parse('#navPopup'); } ); }
            } else {
                var id       = typeof json.id   != 'undefined' ? json.id : 'win'+Math.floor((Math.random() * 1000000) + 1);
                if (jqBiz("#"+id).hasClass( "easyui-window" )) { jqBiz('#'+id).window('destroy', true); }
                var winT     = typeof json.top  != 'undefined' ? json.top  :  50;
                var winL     = typeof json.left != 'undefined' ? json.left : 200;
                var winW     = Math.min(typeof json.width != 'undefined' ? json.width : 600, jqBiz(document).width());
                var winH     = Math.min(typeof json.height!= 'undefined' ? json.height: 400, jqBiz(document).height());
                var wClosable= typeof json.wClosable != 'undefined' ? json.wClosable : true;
                jqBiz('body').append('<div id="'+id+'"></div>');
                jqBiz('#'+id).window({ title:title, top:winT, left:winL, width:winW, height:winH, modal:true, onClose:function(){ jqBiz('#'+id).window('destroy', true); } }); //.window('center');
                if (typeof json.html == 'undefined') { json.html = ''; }
                jqBiz('#'+id).html(json.html);
                jqBiz.parser.parse('#'+id);
                if (typeof json.href != 'undefined') { jqBiz('#'+id).window( {href:json.href } ); }
                if (wClosable) { jqBiz( "div.panel-tool" ).css("display", "inline-block"); } // mobile.css hides this, so show it
            }
            break;
        default: // if (!json.action) alert('response had no action! Bailing...');
    }
}

/**
 * This function extracts the returned messageStack messages and displays then according to the severity
 */
function displayMessage(message) {
    var msgText = '';
    var imgIcon = '';
    // Process errors and warnings
    if (message.error) for (var i=0; i<message.error.length; i++) {
        msgText += '<span>'+message.error[i].text+'</span><br />';
        imgIcon = 'error';
    }
    if (message.warning) for (var i=0; i<message.warning.length; i++) {
        msgText += '<span>'+message.warning[i].text+'</span><br />';
        if (!imgIcon) imgIcon = 'warning';
    }
    if (message.caution) for (var i=0; i<message.caution.length; i++) {
        msgText += '<span>'+message.caution[i].text+'</span><br />';
        if (!imgIcon) imgIcon = 'warning';
    }
    if (msgText) jqBiz.messager.alert({title:'',msg:msgText,icon:imgIcon,width:600});
    // Now process Info and Success
    if (message.info) {
        msgText = '';
        msgTitle= bizLangJS('INFORMATION');
        msgID   = Math.floor((Math.random() * 1000000) + 1); // random ID to keep boxes from stacking and crashing easyui
        for (var i=0; i<message.info.length; i++) {
            if (typeof message.info[i].title !== 'undefined') { msgTitle = message.info[i].title; }
            msgText += '<span>'+message.info[i].text+'</span><br />';
        }
        processJson( { action:'window', id:msgID, title:msgTitle, html:msgText } );
    }
    if (message.success) {
        msgText = '';
        for (var i=0; i<message.success.length; i++) {
            msgText += '<span>'+message.success[i].text+'</span><br />';
        }
        jqBiz.messager.show({title:bizLangJS('MESSAGE'), msg:msgText, timeout:5000, width:400, height:200, style:{ right:'', top:'', bottom:-document.body.scrollTop-document.documentElement.scrollTop }});
    }
}

/**
 * sets a timer for spiners an such for real time fomr submission, i.e. spinners
 * @param {type} path
 * @param {type} id
 * @param {type} el
 * @returns {undefined}
 */
function dashDelay(dashPath, dashID, el) {
    jqBiz('#'+el).on('keyup', {dashPath: dashPath, dashID: dashID}, function(event) {
        clearTimeout(dashTimer);
        dashTimer = setTimeout(function () { dashSubmit(event.data.dashPath, event.data.dashID); }, dashTimerVal);
    });
    jqBiz('#'+el).on('keydown', function () { clearTimeout(dashTimer); });
}

/**
 *
 * @param {type} dashID
 * @param {type} rowID
 * @returns {Boolean}
 */
function dashSubmit(dashID, rowID) {
    var gData = '&menuID='+menuID+'&dashID='+dashID;
    if (rowID) gData += '&rID='+rowID; // then there was a row action
    var form = jqBiz('#'+dashID+'Form');
    divSubmit('bizuno/dashboard/attr'+gData, dashID);
}

/**
 * This function deletes a selected dashboard from the displayed menu
 */
function dashboardDelete(obj) {
    var p = jqBiz(obj).panel('options');
    jqBiz.ajax({
        type: 'GET',
        url:  bizunoAjax+'&bizRt=bizuno/dashboard/delete&menuID='+menuID+'&moduleID='+p.module_id+'&dashboardID='+p.id,
        success: function (json) { processJson(json); }
    });
    return true;
}
// ****************** Multi-submit Operations ***************************************/
function cronInit(baseID, urlID) {
    winHTML = '<p>&nbsp;</p><p style="text-align:center"><progress id="prog'+baseID+'"></progress></p><p style="text-align:center"><span id="msg'+baseID+'">&nbsp;</span></p>';
    processJson({action:'window', id:'win'+baseID, title:bizLangJS('PLEASE_WAIT'), html:winHTML, width:450, height:300});
    jqBiz.ajax({ url:bizunoAjax+'&bizRt='+urlID, async:false, success:cronResponse });
}

function cronRequest(baseID, urlID) {
    jqBiz.ajax({ url:bizunoAjax+'&bizRt='+urlID, async:false, success:cronResponse });
}

function cronResponse(json) {
    jqBiz('#msg' +json.baseID).html(json.msg+' ('+json.percent+'%)');
    jqBiz('#prog'+json.baseID).attr({ value:json.percent,max:100});
    processJson(json);
    if (json.percent < 100) {
        window.setTimeout("cronRequest('"+json.baseID+"','"+json.urlID+"')", 250);
    } else { // finished
        jqBiz('#btn'+json.baseID).show();
        jqBiz('#win'+json.baseID).window({title:bizLangJS('FINISHED')});
        jqBiz( "div.panel-tool" ).css("display", "inline-block");
    }
}

//*********************************** General Functions *****************************************/
/**
 * Rounds a number to the proper number of decimal places for currency values
 * @returns float
 */
function bizRoundCurrency(value)
{
    var curISO  = jqBiz('#currency').val() ? jqBiz('#currency').val() : bizDefaults.currency.defaultCur;
    var decLen  = parseInt(bizDefaults.currency.currencies[curISO].dec_len);
    var adj     = Math.pow(10, (decLen+2));
    var newValue= parseFloat(value) + (1/adj);
    return parseFloat(newValue.toFixed(decLen));
}

/**
 * Rounds a number to the proper number of decimal places for number values
 * @returns float
 */
function bizRoundNumber(value, extend)
{
    if (typeof extend == 'undefined') { extend=true; }
    var decLen  = (typeof bizDefaults.locale.precision !== 'undefined') ? parseInt(bizDefaults.locale.precision) : 2;
    var precsion= extend? decLen+2 : decLen;
    var step1   = parseFloat(value);
    var step2   = (step1).toFixed(precsion);
    var step3   = parseFloat(step2);
//alert('value = '+value+' and decLen = '+decLen+' and step1 = '+step1+' and step2 = '+step2+' and step3 = '+step3);
    return step3;
//    var adj     = Math.pow(10, (decLen+2));
//    var newValue= parseFloat(value) + (1/adj);
//    return parseFloat(newValue.toFixed(decLen));
}

function bizWindowClose(id) {
    isMobile() ? jqBiz.mobile.back() : jqBiz('#'+id).window('close');
}

function bizWindowDestroy(id) {
    jqBiz('#'+id).window('destroy', true);
}
function imgManagerInit(imgID, src, srcPath, opts)
{
    imgStyle = 'max-height:100%;max-width:100%;';
    if (typeof opts != 'undefined') {
        if (typeof opts.style != 'undefined') { imgStyle = opts.style; }
    }
    var divInvImg= '';
    var divTB    = '';
    path = src==='' ? '' : ('images/'+src);
    var viewAction  = "jqBiz('#imdtl_"+imgID+"').window({ width:700,height:560,modal:true,title:'Image Viewer (Click Image to Dismiss)' }).window('center');";
    viewAction     += "var q = jqBiz('#img_"+imgID+"').attr('src'); jqBiz('#imdtl_"+imgID+"').html(jqBiz('<img>',{id:'viewImg',src:q}));";
    viewAction     += "jqBiz('#viewImg').click(function() { jqBiz('#imdtl_"+imgID+"').window('close'); }).css({'max-height':'100%','max-width':'100%'});";
    var editAction  = "jsonAction('bizuno/image/manager&imgMgrPath="+srcPath+"&imgTarget="+imgID+"');";
    var trashAction = "jqBiz('#img_"+imgID+"').attr('src','"+bizunoAjaxFS+"'); jqBiz('#"+imgID+"').val('');";
    divInvImg      += '<div><a id="im_'+imgID+'" href="javascript:void(0)">';
    divInvImg      += '  <img type="img" style="'+imgStyle+'" src="'+bizunoAjaxFS+bizID+'/'+path+'" name="img_'+imgID+'" id="img_'+imgID+'" /></a></div><div id="imdtl_'+imgID+'"></div>';
    divTB  = '<a onClick="'+viewAction +'" class="easyui-linkbutton" title="'+bizLangJS('VIEW') +'" data-options="iconCls:\'icon-search\',plain:true"></a>';
    divTB += '<a onClick="'+editAction +'" class="easyui-linkbutton" title="'+bizLangJS('EDIT') +'" data-options="iconCls:\'icon-edit\',  plain:true"></a>';
    divTB += '<a onClick="'+trashAction+'" class="easyui-linkbutton" title="'+bizLangJS('TRASH')+'" data-options="iconCls:\'icon-trash\', plain:true"></a>';

    jqBiz('#'+imgID).after(divInvImg);
    jqBiz('#im_'+imgID).tooltip({ hideEvent:'none', showEvent:'click', position:'bottom', content:jqBiz('<div></div>'),
        onUpdate: function(content) { content.panel({ width: 100, border: false, content: divTB }); },
        onShow:   function() {
            var t = jqBiz(this);
            t.tooltip('tip').unbind().bind('mouseenter', function() { t.tooltip('show'); }).bind('mouseleave', function() { t.tooltip('hide'); });
        }
    });
}

function initGLAcct(obj) {
    if (obj.id === "") obj.id = 'tempGL';
    jqBiz('#'+obj.id).combogrid({ data: bizDefaults.glAccounts, width: 300, panelWidth: 450, idField: 'id', textField: 'title',
        columns: [[{field:'id',title:bizLangJS('ACCOUNT'),width:60},{field:'title',title:bizLangJS('TITLE'),width:200},{field:'type',title:bizLangJS('TYPE'),width:180}]]
    });
    // jqBiz('#'+obj.id).combogrid('showPanel'); // displays in upper left corner if instantiated inside hidden div
    jqBiz('#'+obj.id).combogrid('resize',120);
    if (obj.id === "tempGL") obj.id = "";
}

/**
 * Bizuno equivalent to PHP's in_array
 * @param {type} needle
 * @param {type} haystack
 * @returns {Boolean}
 */
function bizInArray(needle, haystack) {
    var length = haystack.length;
    for (var i = 0; i < length; i++) {
        if (haystack[i] == needle) { return true; }
    }
    return false;
}
/* ****************************** Currency Functions ****************************************/
/**
 * Sets the default numberbox currency properties, decimal point, thousands separator, prefix, suffix and decimal length
 * @param {type} iso
 * @returns {undefined}
 */
function setCurrency(iso) {
    if (typeof bizDefaults.currency.defaultCur == 'undefined') { return; } // browser cache not loaded
    jqBiz('#currency').val(iso);
//  currency = jqBiz('#currency').val() ? jqBiz('#currency').val() : bizDefaults.currency.defaultCur;
    if (!bizDefaults.currency.currencies[iso]) {
        alert('Error - cannot find currency: '+iso+' to set! Bailing.');
        return;
    }
    jqBiz.fn.numberbox.defaults.precision       = bizDefaults.currency.currencies[iso].dec_len;
    jqBiz.fn.numberbox.defaults.decimalSeparator= bizDefaults.currency.currencies[iso].dec_pt;
    jqBiz.fn.numberbox.defaults.groupSeparator  = bizDefaults.currency.currencies[iso].sep;
    jqBiz.fn.numberbox.defaults.prefix          = bizDefaults.currency.currencies[iso].prefix;
    jqBiz.fn.numberbox.defaults.suffix          = bizDefaults.currency.currencies[iso].suffix;
    if (jqBiz.fn.numberbox.defaults.prefix) { jqBiz.fn.numberbox.defaults.prefix = jqBiz.fn.numberbox.defaults.prefix + ' '; }
    if (jqBiz.fn.numberbox.defaults.suffix) { jqBiz.fn.numberbox.defaults.suffix = ' ' + jqBiz.fn.numberbox.defaults.suffix; }
}

function bizDgEdCurSet(id, column, newISO) {
    var opts = jqBiz('#'+id).datagrid('getColumnOption', column);
    if (opts == null || typeof opts.editor == 'undefined') { return; }
    if (!opts.editor) { return; }
    opts.editor.options.decimalSeparator = bizDefaults.currency.currencies[newISO].dec_pt;
    opts.editor.options.groupSeparator = bizDefaults.currency.currencies[newISO].sep;
    opts.editor.options.prefix = bizDefaults.currency.currencies[newISO].prefix ? bizDefaults.currency.currencies[newISO].prefix+' ' : '';
    opts.editor.options.suffix = bizDefaults.currency.currencies[newISO].suffix ? ' '+bizDefaults.currency.currencies[newISO].suffix : '';
}


/**
 * Takes a locale formatted currency string and formats it into a float value
 * @param string amount - Locale formatted currency value
 * @param string currency - ISO currency code to convert from
 * @return float Converted currency value
 */
function cleanCurrency(amount, currency) {
    if (typeof amount  =='undefined') { return 0; }
    if (typeof currency=='undefined') { currency = jqBiz('#currency').val() ? jqBiz('#currency').val() : bizDefaults.currency.defaultCur; }
    if (!bizDefaults.currency.currencies[currency]) {
        alert('Error - cannot find currency: '+currency+' to clean! Returning unformattted value!');
        return amount;
    }
    if (bizDefaults.currency.currencies[currency].prefix) amount = amount.toString().replace(bizDefaults.currency.currencies[currency].prefix, '');
    if (bizDefaults.currency.currencies[currency].suffix) amount = amount.toString().replace(bizDefaults.currency.currencies[currency].suffix, '');
    var sep   = bizDefaults.currency.currencies[currency].sep;
    var dec_pt= bizDefaults.currency.currencies[currency].dec_pt;
    amount    = amount.toString().replace(new RegExp("["+sep+"]", "g"), '');
    amount    = amount.replace(new RegExp("["+dec_pt+"]", "g"), '.');
    amount    = parseFloat(amount.replace(/[^0-9\.\-]/g, ''));
    return amount;
}

/**
 * Rounds a number to the proper number of decimal places for currency values
 * @returns float
 */
function roundCurrency(value)
{
    var curISO  = jqBiz('#currency').val() ? jqBiz('#currency').val() : bizDefaults.currency.defaultCur;
    var decLen  = parseInt(bizDefaults.currency.currencies[curISO].dec_len);
    var adj     = Math.pow(10, (decLen+2));
    var newValue= parseFloat(value) + (1/adj);
    var newTmp  = parseFloat(newValue.toFixed(decLen));
    return newTmp;
}

/**
 * This function formats a decimal value into the currency format specified in the form
 * @param decimal amount - decimal amount to format
 * @param boolean pfx_sfx - [default: true] determines whether or not to include the prefix and suffix, setting to false will just return number
 * @returns formatted string to ISO currency format
 */
function formatCurrency(amount, pfx_sfx, isoCur, excRate) { // convert to expected currency format
    if (typeof pfx_sfx == 'undefined') { pfx_sfx= true; }
    if (typeof isoCur  == 'undefined') { isoCur = bizDefaults.currency.defaultCur; }
    if (typeof excRate == 'undefined') { excRate = 1; }
    var curISO  = jqBiz('#currency').val() ? jqBiz('#currency').val() : isoCur;
    if (!bizDefaults.currency.currencies[curISO]) {
        alert('Error - cannot find currency: '+curISO+' to format! Returning unformattted value!');
        return amount;
    }
    var dec_len = parseInt(bizDefaults.currency.currencies[curISO].dec_len);
    var sep     = bizDefaults.currency.currencies[curISO].sep;
    var dec_pt  = bizDefaults.currency.currencies[curISO].dec_pt;
    var pfx     = bizDefaults.currency.currencies[curISO].prefix;
    var pfxneg  = bizDefaults.currency.currencies[curISO].pfxneg;
    var sfxneg  = bizDefaults.currency.currencies[curISO].sfxneg;
    var sfx     = bizDefaults.currency.currencies[curISO].suffix;
    if (pfx) { pfx = pfx + ' '; }
    if (sfx) { sfx = ' ' + sfx; }
    if (isNaN(pfxneg)) { pfxneg = '-'; }
    if (isNaN(sfxneg)) { sfxneg = ''; }
//alert('found currency = '+currency+' and decimal point = '+dec_pt+' and separator = '+sep);
    if (typeof dec_len === 'undefined') dec_len = 2;
    // amount needs to be a string type with thousands separator ',' and decimal point dot '.'
    var factor  = Math.pow(10, dec_len);
    var adj     = Math.pow(10, (dec_len+3)); // to fix rounding (i.e. .1499999999 rounding to 0.14 s/b 0.15)
    var wholeNum= parseFloat(amount * excRate);
    if (isNaN(wholeNum)) return amount;
    var numExpr = Math.round((wholeNum * factor) + (1/adj));
    var calcAmt = (wholeNum * factor) + (1/adj);
//if (amount) alert('original amount = '+amount+' and parsed float to '+wholeNum+' multiplied by '+factor+' and adjusted by 1/'+adj+' calculated to '+calcAmt+' which rounded to: '+numExpr);
    var negative= (numExpr < 0) ? true : false;
    numExpr     = Math.abs(numExpr);
    var decimal = (numExpr % factor).toString();
    while (decimal.length < dec_len) decimal = '0' + decimal;
    var whole   = Math.floor(numExpr / factor).toString();
    for (var i = 0; i < Math.floor((whole.length-(1+i))/3); i++) { whole = whole.substring(0,whole.length-(4*i+3)) + sep + whole.substring(whole.length-(4*i+3)); }
    var output = dec_len > 0 ? whole+dec_pt+decimal : whole;
    if (negative) { output = pfxneg+output+sfxneg; }
    if (pfx_sfx)  { output = pfx+output+sfx; }
    return output;
}

function formatPrecise(amount) { // convert to expected currency format with the additional precision
    currency = jqBiz('#currency').val() ? jqBiz('#currency').val() : bizDefaults.currency.defaultCur;
    if (!bizDefaults.currency.currencies[currency]) {
        alert('Error - cannot find currency: '+currency+' to format precise! Returning unformattted value!');
        return amount;
    }
    var sep   = bizDefaults.currency.currencies[currency].sep;
    var dec_pt= bizDefaults.currency.currencies[currency].dec_pt;
    var decimal_precise = bizDefaults.locale.precision;
    if (typeof decimal_precise === 'undefined') decimal_precise = 4;
    // amount needs to be a string type with thousands separator ',' and decimal point dot '.'
    var factor  = Math.pow(10, decimal_precise);
    var adj     = Math.pow(10, (decimal_precise+2)); // to fix rounding (i.e. .1499999999 rounding to 0.14 s/b 0.15)
    var numExpr = parseFloat(amount);
    if (isNaN(numExpr)) return amount;
    numExpr     = Math.round((numExpr * factor) + (1/adj));
    var minus   = (numExpr < 0) ? '-' : '';
    numExpr     = Math.abs(numExpr);
    var decimal = (numExpr % factor).toString();
    while (decimal.length < decimal_precise) decimal = '0' + decimal;
    var whole   = Math.floor(numExpr / factor).toString();
    for (var i = 0; i < Math.floor((whole.length-(1+i))/3); i++)
        whole = whole.substring(0,whole.length-(4*i+3)) + sep + whole.substring(whole.length-(4*i+3));
    if (decimal_precise > 0) return minus + whole + dec_pt + decimal;
    return minus + whole;
}

/**
 *
 * This function takes a value and converts it from one ISO to another
 * @param float value - Value to convert
 * @param string destISO - destination ISO to convert to
 * @param string sourceISO - [default bizDefaults.currency.defaultCur] ISO code to use to convert from
 * @returns float - converted to destISO code
 */
function convertCurrency(value, destISO, sourceISO) {
    var defaultISO = bizDefaults.currency.defaultCur;
    if (typeof sourceISO == 'undefined') sourceISO = bizDefaults.currency.defaultCur;
    if (!bizDefaults.currency.currencies[sourceISO]) {
        alert('Error - cannot find source currency to format! Returning unformattted value!');
        return value;
    }
    if (!bizDefaults.currency.currencies[destISO]) {
        alert('Error - cannot find destination currency to format! Returning unformattted value!');
        return value;
    }
    var srcVal = parseFloat(value);
    if (isNaN(srcVal)) {
        alert('Error - the value submitted is not a number! Returning unformattted value!');
        return value;
    }
    if (sourceISO != defaultISO) srcVal = srcVal * parseFloat(bizDefaults.currency.currencies[sourceISO].value); // convert to defaultISO
    if (parseFloat(bizDefaults.currency.currencies[destISO].value) == 0) {
        alert('currenct exchange rate is zero! This should not happen.');
        return value;
    }
    newValue = srcVal != 0 ? srcVal / parseFloat(bizDefaults.currency.currencies[destISO].value) : 0; // convert to destISO
    return newValue;
}

/******************************* Number Functions ****************************************
 * Takes a locale formatted number string and formats it into a float value
 * @param string amount - Locale formatted value
 * @return float Converted value
 */
function cleanNumber(amount) {
    if (typeof amount == 'undefined') return 0;
    var sep = bizDefaults.locale.thousand;
    amount = amount.toString().replace(new RegExp("["+sep+"]", "g"), '');
    var dec = bizDefaults.locale.decimal;
    amount = parseFloat(amount.replace(new RegExp("["+dec+"]", "g"), '.'));
    return amount;
}

function formatNumber(amount) {
    var dec_len= (typeof bizDefaults.locale.precision !== 'undefined') ? bizDefaults.locale.precision : 2;
    var sep    = (typeof bizDefaults.locale.thousand  !== 'undefined') ? bizDefaults.locale.thousand  : '.';
    var dec_pt = (typeof bizDefaults.locale.decimal   !== 'undefined') ? bizDefaults.locale.decimal   : ',';
    var pfx    = (typeof bizDefaults.locale.prefix    !== 'undefined') ? bizDefaults.locale.prefix    : '';
    var sfx    = (typeof bizDefaults.locale.suffix    !== 'undefined') ? bizDefaults.locale.suffix    : '';
    var negpfx = (typeof bizDefaults.locale.neg_pfx   !== 'undefined') ? bizDefaults.locale.neg_pfx   : '-';
    var negsfx = (typeof bizDefaults.locale.neg_sfx   !== 'undefined') ? bizDefaults.locale.neg_sfx   : '';
    var is_negative = false;
//alert('found decimal point = '+dec_pt+' and seprator = '+sep);
    if (typeof dec_len === 'undefined') dec_len = 2;
    // amount needs to be a string type with thousands separator ',' and decimal point dot '.'
    var factor  = Math.pow(10, dec_len);
    var adj     = Math.pow(10, (dec_len+2)); // to fix rounding (i.e. .1499999999 rounding to 0.14 s/b 0.15)
    var numExpr = parseFloat(amount);
    if (isNaN(numExpr)) return amount;
    if (numExpr < 0) is_negative = true;
    numExpr     = Math.round((numExpr * factor) + (1/adj));
    numExpr     = Math.abs(numExpr);
    var decimal = (numExpr % factor).toString();
    while (decimal.length < dec_len) decimal = '0' + decimal;
    var whole   = Math.floor(numExpr / factor).toString();
    for (var i = 0; i < Math.floor((whole.length-(1+i))/3); i++)
        whole = whole.substring(0,whole.length-(4*i+3)) + sep + whole.substring(whole.length-(4*i+3));
    if (is_negative) {
        if (dec_len > 0) return negpfx + whole + dec_pt + decimal + negsfx;
        return negpfx + whole + negsfx;
    }
    if (dec_len > 0) return pfx + whole + dec_pt + decimal + sfx;
    return pfx + whole + sfx;
}

/******************************* Date Functions ****************************************
 * Formats a database date (YYYY-MM-DD) date to local format, the datebox calls this to format the date
 * @todo broken needs to take into account UTC, returns a day earlier
 * @param str - db date in string format YYYY-MM-DD
 * @returns formatted date by users locale definition
 */
function formatDate(str) {
    var output = bizDefaults.calendar.format;
    if (typeof str !== 'string' || typeof sDate === 'object') { // easyui date formatter, or full ISO date
        var objDate = new Date(str);
        var Y = objDate.getFullYear();
        var m = ("0" + (objDate.getMonth() + 1)).slice(-2);
        var d = ("0" + objDate.getDate()).slice(-2);
    } else {
        var Y = str.substr(0,4);
        var m = str.substr(5,2);
        var d = str.substr(8,2);
    }
    output = output.replace("Y", Y);
    output = output.replace("m", m);
    output = output.replace("d", d);
//    alert('started with date = '+str+' and ended with = '+output);
    return output;
}

/**
 * Convert the users locale date to db format to use with Date() object
 * @returns integer
 */
function dbDate(str) {
    var fmt  = bizDefaults.calendar.format;
    var delim= bizDefaults.calendar.delimiter;
    var parts= fmt.split(delim);
    var src  = str.split(delim);
    for (var i=0; i < parts.length; i++) {
        if (parts[i] == 'Y') { var Y = src[i]; }
        if (parts[i] == 'm') { var m = src[i]; }
        if (parts[i] == 'd') { var d = src[i]; }
    }
    return Y+'-'+m+'-'+d;
}

/**
 *
 * @param {type} ref
 * @returns integer, -1 if less, 0 if equal, 1 if greater
 */
function compareDate(ref) {
    var d1 = new Date(ref);
    var d2 = new Date();
    if (d1 < d2) return -1;
    if (d1 > d2)  return 1;
    return 0;
}

function bizButtonOpt(id, opt, value) {
    jqBiz('#'+id).linkbutton({ opt: value });
}

function bizCheckBox(id) {
    jqBiz('#'+id).switchbutton('check');
}

function bizUncheckBox(id) {
    jqBiz('#'+id).switchbutton('uncheck');
}

function bizCheckBoxGet(id) {
    if (jqBiz("#"+id).hasClass( "easyui-switchbutton" )) {
        return jqBiz('#'+id).switchbutton('options').checked == true ? 1 : 0;
    } else if (jqBiz('#'+id).is(':checkbox')) {
        return jqBiz('#'+id).is(':checked');
    }
    return parseFloat(jqBiz('#'+id).val());
}

function bizCheckboxSet(id, value) {
    if (value==='0' || value===0) { bizUncheckBox(id); }
    else { bizCheckBox(id); }
}

function bizCheckboxChange(id, callback) {
    jqBiz('#'+id).switchbutton({ onChange:function (newVal, oldVal) { callback(newVal, oldVal); } });
}

function bizDateSet(id, val) {
     jqBiz('#'+id).datebox('setValue', val);
}

function bizDateGet(id) {
    return jqBiz('#'+id).datebox('getValue');
}

// Retrieves the curent index of the selected row (edited) of a datagrid
function bizDGgetIndex(id) {
    var idx = null;
    var row = jqBiz('#'+id).datagrid('getSelected');
    if (row) { idx = jqBiz('#'+id).datagrid('getRowIndex', row); }
    return idx;
}

function bizDGgetRow(dgID) {
    var idx = bizDGgetIndex(dgID);
    var row = jqBiz('#'+dgID).datagrid('getRows')[idx];
    return row;
}

function bizDivToggle(id) {
    jqBiz('#'+id).toggle('slow');
}

function bizEscapeHtml(text) {
  var map = {'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;'};
  return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

function bizFocus(id, dgID) {
    if (jqBiz('#'+id).hasClass("easyui-textbox") || jqBiz('#'+id).hasClass("easyui-passwordbox")) {
        jqBiz('#'+id).textbox('textbox').focus();
    } else if (jqBiz('#'+id).hasClass("easyui-combobox")) {
        jqBiz('#'+id).combobox('textbox').focus();
    } else if (jqBiz("#"+id).hasClass("easyui-combogrid")) {
        jqBiz('#'+id).combogrid('textbox').focus();
    } else {
        jqBiz('#'+id).focus();
    }
    if (typeof dgID == 'string') {
        jqBiz('#'+dgID+'Toolbar').keypress(function (e) { if (e.keyCode == 13) { window[dgID+'Reload'](); } });
    }
}

/**
 * Determines if a datagrid exists in the DOM
 * @param string id - DOM field id
 * @returns true is grid exists, foalse otherwise
 */
function bizGridExists(id) {
    var dg = jqBiz('#'+id);
    return dg.data('datagrid') ? true : false;
}

/**
 * Reloads a datagrid from either URL or local data depending on how the grid is defined
 * @param string id - DOM id
 * @returns null
 */
function bizGridReload(id) { jqBiz('#'+id).datagrid('reload'); }

/**
 * Add a row to the current grid
 * @param string id - DOM element ID
 */
function bizGridAddRow(id) { jqBiz('#'+id).edatagrid('addRow'); }

/**
 * Returns the row data from the selected row of a datagrid from the action bar
 * @param {string} id - DOM id of the element to get data from
 * @returns row data object
 */
function bizGridGetRow(id) {
    var rowIndex= jqBiz('#'+id).datagrid('getRowIndex', jqBiz('#'+id).datagrid('getSelected'));
    var rowData = jqBiz('#dgJournalItem').datagrid('getData');
    if (typeof rowData.rows[rowIndex] == 'undefined') { return; }
    return rowData.rows[rowIndex];
}

function bizGridEdSet(id, idx, fld, val) {
    var ed = jqBiz('#'+id).datagrid('getEditor', {index:idx, field:fld});
    if (ed) jqBiz(ed.target).combogrid('setValue', val);
}

function bizGridFormatter(value) {
    if (typeof(value)=='undefined') { return ''; }
    return value.replace(/\r\n/g, '<br>');
}

/**
 * Stops editing datagrid and gathers the data into a specified field
 * @param DOM dGrid - datagrid to fetch data
 * @param string fld - DOM field id to place the serialized data
 * @returns null
 */
function bizGridSerializer(dGrid, fld) {
    if (jqBiz('#'+dGrid).length) {
        jqBiz('#'+dGrid).edatagrid('saveRow');
        var items = jqBiz('#'+dGrid).datagrid('getData');
        var serializedItems = JSON.stringify(items);
        if (fld !== '') { jqBiz('#'+fld).val(serializedItems); }
        else            { return serializedItems; }
    }
    return '';
}

/**
 * Tests the window width and if it is small, removes the labels fromthe buttons (i.e. short format))
 */
function bizMenuResize() {
    // sometimes the browser memory gets erased. This is just a simple test to see if the data is still there. If not, reload.
    if (typeof(bizDefaults.glAccounts) == 'undefined') { reloadSessionStorage(); }
//  alert('window width = '+jqBiz(window).width());
    if (jqBiz(window).width() < 1400) {
        jqBiz('div[id=rootMenu]').children('.easyui-splitbutton').each(function() {
            jqBiz('#'+this.id).splitbutton({text:''});
        });
    }
}

function bizMenuToggle(id) {
    var opts = jqBiz('#'+id).sidemenu('options');
    var icnDir = opts.collapsed ? 'left' : 'right';
    jqBiz('#'+id+'Toggle').linkbutton({ iconCls: 'fa-sharp-duotone fa-solid fa-chevrons-'+icnDir });
    jqBiz('#'+id).sidemenu(opts.collapsed ? 'expand' : 'collapse');
    opts = jqBiz('#'+id).sidemenu('options');
    jqBiz('#'+id).sidemenu('resize', { width: opts.collapsed ? 60 : 200 });
    jqBiz('body').layout('panel','west').panel('resize',{ width: opts.collapsed ? 60 : 200 });
    var c = jqBiz('body'); // resize the center layout panel
    var p = c.layout('panel', 'center');
    var oldWidth = p.panel('panel').outerWidth();
    p.panel('resize', {width:'auto'});
    var newWidth = p.panel('panel').outerWidth();
    c.layout('resize',{ width: (c.width() + newWidth - oldWidth) } );
    // send ajax upxate to users profile back at the server
    jsonAction('bizuno/profile/update&menuSize='+(opts.collapsed?'min':'full'));
}

function bizNumChange(id, callback) {
    jqBiz('#'+id).numberbox({ onChange:function (newVal, oldVal) { callback(newVal, oldVal); } });
}
/**
 * Pulls numeric value from a numberbox
 * CAUTION: DO NOT USE FOR TEXTBOXES DISPLAYING CURRENCIES AS IT WILL ERASE THE VALUE!
 */
function bizNumGet(id) {
    if (!jqBiz('#'+id).numberbox({})) { }
    return parseFloat(jqBiz('#'+id).numberbox('getValue'));
}


function bizNumSet(id, val) {
    if (!jqBiz('#'+id).hasClass("easyui-numberbox")) {
        if (jqBiz('#'+id)) { jqBiz('#'+id).val(val); } // hidden or not a easyUI widget
        return;
    }
    if (!jqBiz('#'+id).numberbox({})) { }
    jqBiz('#'+id).numberbox('setValue', val);
}

function bizNumEdGet(id, idx, fld) {
    var ed = jqBiz('#'+id).edatagrid('getEditor', {index:idx,field:fld});
    if (ed) { var val = parseFloat(ed.target.val()); }
    else    { var val = parseFloat(jqBiz('#'+id).edatagrid('getRows')[idx][fld]); } // no editor try just to get value
    return isNaN(val) ? 0 : val;
}

function bizNumEdSet(id, idx, fld, amount) {
    if (isNaN(amount)) { return; }
    if (typeof id=='undefined') { return; }
    var ed = jqBiz('#'+id).edatagrid('getEditor', {index:idx,field:fld});
    jqBiz('#'+id).edatagrid('getRows')[idx][fld] = amount; // needs to set irregardless of editor (i.e. when editors are hidden)
    if (ed) { jqBiz(ed.target).numberbox('setValue', amount); }
}

function bizPanelContent(id, data) {
    jqBiz('#'+id).panel('content', data);
}

function bizPanelReload(id, route) {
    jqBiz('#'+id).panel({href:bizunoAjax+'&bizRt='+route});
}

function bizPanelRefresh(id) {
    jqBiz('#'+id).panel('refresh');
}

function bizParse(id) {
    if (typeof id=='undefined') { jqBiz.parser.parse(); }
    else                        { jqBiz.parser.parse('#'+id); }
}

function bizRegionInit(suffix) {
    var iso3 = bizSelGet('country' + suffix);  // e.g. "USA", "CAN", "MEX"...
    jqBiz('#country'+suffix).combogrid({ onSelect: function(index,row) { jqBiz('#country'+suffix).combogrid('hidePanel'); bizRegionChange(row.iso3, suffix); } });
    bizRegionChange(iso3, suffix);
}
function bizRegionChange(iso3, suffix) {
    if (iso3 && bizDefaults?.regions?.[iso3] && Array.isArray(bizDefaults.regions[iso3]) || typeof bizDefaults.regions[iso3] === 'object') {
        jqBiz('#state'+suffix).combobox({data:bizDefaults.regions[iso3], editable:false}).combobox('loadData', bizDefaults.regions[iso3]);
    } else { // No regions available for this country → free text input
        jqBiz('#state'+suffix).combobox({data:[], value:'', editable:true}).combogrid('clear').combobox('loadData', []);
    }
}
function bizSelGet(id) {
    if (jqBiz("#"+id).hasClass( "easyui-combobox" )) {
        return jqBiz('#'+id).combobox('getValue');
    } else if (jqBiz("#"+id).hasClass( "easyui-combogrid" )) {
        return jqBiz('#'+id).combogrid('getValue');
    }
    if (!jqBiz('#'+id).combo({})) { }
    return jqBiz('#'+id).combo('getValue');
}

function bizSelReload(id, values) {
    jqBiz('#'+id).combobox('loadData', values);
}

function bizSelSet(id, val, fmt) {
    if (!jqBiz('#'+id).combo({})) { return; }
    switch (fmt) {
        case 'number':   val = formatNumber(val);   break;
        case 'currency': val = formatCurrency(val); break;
        default:
        case 'raw': // nothing just display as is
    }
    if (jqBiz('#'+id).hasClass("easyui-combobox")) {
        jqBiz('#'+id).combobox('setValue', val);
    } else if (jqBiz("#"+id).hasClass("easyui-combogrid")) {
        jqBiz('#'+id).combogrid('setValue', val);
    } else {
        jqBiz('#'+id).combo('setValue', val);
    }
}

/*
 * This function will search all gl accounts in a combo in place of the standard search only by text field
 * @param string id - DOM element ID
 * @param string q - search string
 * @returns array - filtered data
 */
function bizSelSearch(id, q) {
    var newRows = [];
    jqBiz.map(bizDefaults.glAccounts.rows, function(row) {
        for (var p in row) {
            var v = row[p];
            var regExp = new RegExp(q, 'i'); // i - makes the search case-insensitive.
            if (regExp.test(String(v))) {
                newRows.push(row);
                break;
            }
        }
    });
    var gridData = jqBiz('#'+id).combogrid('grid');
    gridData.datagrid('loadData', newRows);
    jqBiz('#'+id).combogrid('showPanel');
    jqBiz('#'+id).combogrid('setText', q);
}

/**
 * Replaces the values in a combo, combobox, and combogrid
 * @param {type} id
 * @param {type} values
 * @returns {undefined}
 */
function bizSelVals(id, values) {
//        alert('bizSelVals with id = '+id);
//    if (!jqBiz('#'+id).combobox({})) { return; } // Instantiates combo using defaults
    jqBiz('#'+id).combobox('loadData', values);
}

/**
 *
 * @param {type} id
 * @param {type} idx
 * @param {type} fld
 * @param {type} val
 * @returns {undefined}
 */
function bizSelEdSet(id, idx, fld, val) {
    if (typeof idx == 'undefined') { return; }
    var ed = jqBiz('#'+id).edatagrid('getEditor', {index:idx,field:fld});
    if (ed) { jqBiz(ed.target).combogrid('setValue', val); }
    jqBiz('#'+id).edatagrid('getRows')[idx][fld] = val; // needs to set iregardless of editor (i.e. when editors are hidden)
}

function bizStartDnD(id) {
    jqBiz('#'+id).datagrid('enableDnd');
}

function bizStopDnD(id) {
    jqBiz('#'+id).datagrid('disableDnd');
}

function bizTextChange(id, callback) {
    jqBiz('#'+id).textbox({ onChange:function (newVal, oldVal) { callback(newVal, oldVal); } });
}
function bizTextGet(id) {
    if (!jqBiz('#'+id).textbox({})) { alert('not ready'); return; }
    return jqBiz('#'+id).textbox('getValue');
}

function bizTextSet(id, txt, fmt) {
    if (!jqBiz('#'+id).hasClass("easyui-textbox")) {
        if (jqBiz('#'+id)) { jqBiz('#'+id).val(txt); } // hidden or not a easyUI widget
        return;
    }
    if (!jqBiz('#'+id).textbox({})) { alert('not ready'); return; }
    switch (fmt) {
        case 'number':   txt = formatNumber(txt);   break;
        case 'currency': txt = formatCurrency(txt); break;
        default:
        case 'raw': // nothing just display as is
    }
    jqBiz('#'+id).textbox('setValue', txt);
}

function bizTextEdSet(id, idx, fld, txt) {
//  alert('setting fields for id = '+id+' and index = '+idx+' field = '+fld+' and text = '+txt);
    var ed = jqBiz('#'+id).edatagrid('getEditor', {index:idx,field:fld});
    if (ed) { ed.target.val(txt); }
    jqBiz('#'+id).edatagrid('getRows')[idx][fld] = txt; // needs to set iregardless of editor (i.e. when editors are hidden)
}

/**
 * Reloads a tree from either URL or local data depending on how the grid is defined
 * @param string id - DOM id
 * @returns null
 */
function bizTreeReload(id) { jqBiz('#'+id).tree('reload'); }

// pulls the text value from a select element given the id value
function getTextValue(arrList, index) {
    if (index === 'undefined') return '';
    if (!arrList.length) return index;
    for (var i in arrList) { if (arrList[i].id == index) return arrList[i].text; } // must use == NOT === or doesn't work
    return index;
}


function makeRequest(url) { jqBiz.ajax({ url: url, success: server_response }); }

function server_response(json) {
    processJson(json);
  jqBiz('progress').attr({value:json.percent,max:100});
  jqBiz('#pl').html(json.pl);
  jqBiz('#pq').html(json.pq);
  jqBiz('#ps').html(json.ps);
  if (json.percent == '100') {
      jqBiz("#divRestoreCancel").toggle('slow');
      jqBiz("#divRestoreFinish").toggle('slow');
  } else {
    if (restoreCancel == 'cancel') {
      jqBiz("#divRestoreCancel").toggle('slow');
    } else {
      url_request = bizunoAjax+"&bizRt=bizuno/main/restoreAjax&start="+json.linenumber+"&fn="+json.fn+"&foffset="+json.foffset+"&totalqueries="+json.totalqueries;
      window.setTimeout("makeRequest(url_request)",500);
    }
  }
}

// *********************************** Contact Functions *****************************************/
function crmDetail(rID, suffix) {
    jqBiz.ajax({
        url:bizunoAjax+'&bizRt=contacts/main/details&rID='+rID,
        success: function(json) {
            processJson(json);
            jqBiz('#id'+suffix).val(json.contact.id); // hidden, no class formatting
            jqBiz('#terms'+suffix).val(json.contact.terms);
            bizTextSet('short_name'    +suffix, json.contact.short_name);
            bizTextSet('contact_first' +suffix, json.contact.contact_first);
            bizTextSet('contact_last'  +suffix, json.contact.contact_last);
            bizTextSet('flex_field_1'  +suffix, json.contact.flex_field_1);
            bizTextSet('account_number'+suffix, json.contact.account_number);
            bizTextSet('gov_id_number' +suffix, json.contact.gov_id_number);
            bizTextSet('terms'+suffix+'_text', json.contact.terms_text);
            for (var i = 0; i < json.address.length; i++) if (json.address[i].type === 'm') addressFill(json.address[i], suffix);
        }
    });
}

function addressFill(address, suffix) {
    for (key in address) {
        bizTextSet(key+suffix, address[key]);
        if (key == 'country') { jqBiz('#country'+suffix).combogrid('setValue', address[key]); }
    }
    jqBiz('#contact_id'+suffix).val(address['ref_id']);
}

function clearAddress(suffix) {
    jqBiz('#address'+suffix).find('input, select').each(function(){
        jqBiz(this).val('').attr('checked',false).css({color:'#000000'}).blur();
    });
    jqBiz('#country'+suffix).combogrid('setValue',bizDefaults.country.iso).combogrid('setText', bizDefaults.country.title);
}

function addressClear(suffix) {
    jqBiz.each(addressFields, function (index, value) { bizTextSet(value+suffix, ''); });
    jqBiz.each(contactFields, function (index, value) { bizTextSet(value+suffix, ''); });
    if (suffix != '_s') { jqBiz('#addressDiv'+suffix).hide(); }
    jqBiz('#country'+suffix).combogrid('setValue', bizDefaults.country.iso).combogrid('setText', bizDefaults.country.title);
}

function addressCopy(fromSuffix, toSuffix) {
    jqBiz.each(addressFields, function (index, value) { if (jqBiz('#'+value+fromSuffix).length) bizTextSet(value+toSuffix, bizTextGet(value+fromSuffix)); });
    jqBiz('#country'+toSuffix).combogrid('setValue', jqBiz('#country'+fromSuffix).combogrid('getValue'));
    // Clear the ID's so Add/Updates don't erase the source record
    bizTextSet('id'+toSuffix, '0');
    bizTextSet('address_id'+toSuffix, '0');
}

function shippingValidate(suffix) {
    let temp = {};
    temp['primary_name']= jqBiz('input[name="primary_name'+suffix+'"]').val();
    temp['contact']     = jqBiz('input[name="contact'+suffix+'"]').val();
    temp['address1']    = jqBiz('input[name="address1'+suffix+'"]').val();
    temp['address2']    = jqBiz('input[name="address2'+suffix+'"]').val();
    temp['city']        = jqBiz('input[name="city'+suffix+'"]').val();
    temp['state']       = jqBiz('input[name="state'+suffix+'"]').val();
    temp['postal_code'] = jqBiz('input[name="postal_code'+suffix+'"]').val();
    var country= bizSelGet('country'+suffix);
    var code   = bizSelGet('method_code');
    var suffix = suffix;
    var ship   = encodeURIComponent(JSON.stringify(temp));
    // For ISP hosted run everything through the Client server
    jsonAction('shipping/address/validateAddress&suffix='+suffix+'&methodCode='+code+'&country='+country, 0, ship);
    // For PhreeSoft hosted, run through the portal???
//  jsonPortal('myPortal/shipping/validateAddress&suffix='+suffix+'&methodCode='+code+'&country='+country, 0, ship);
}

//*********************************** Chart functions *****************************************/
jqBiz.cachedScript('https://www.gstatic.com/charts/loader.js');
function drawBizunoChart(json) {
    var divWidth = parseInt(jqBiz('#'+json.divID).width());
    var divHeight= parseInt(divWidth * 3 / 4);
    var data     = google.visualization.arrayToDataTable(json.data);
    const options = {
        width: divWidth,
        height:divHeight,
//        title: 'Company Performance',
        titleTextStyle: { color: getComputedStyle(document.documentElement).getPropertyValue('--chart-text').trim() },
        backgroundColor: getComputedStyle(document.documentElement).getPropertyValue('--chart-bg').trim(),
        hAxis: { textStyle: { color: getComputedStyle(document.documentElement).getPropertyValue('--chart-text').trim() } },
        vAxis: { textStyle: { color: getComputedStyle(document.documentElement).getPropertyValue('--chart-text').trim() },
                 gridlines: { color: getComputedStyle(document.documentElement).getPropertyValue('--chart-grid').trim() } },
        legend:{ textStyle: { color: getComputedStyle(document.documentElement).getPropertyValue('--chart-text').trim() } },
        series:{ 0: { color: getComputedStyle(document.documentElement).getPropertyValue('--chart-line').trim() } }
    };
    for (var idx in json.attr) { options[idx] = json.attr[idx]; }
    switch (json.type) {
        default:
        case 'pie':
            options['pieHole']  = 0.4;
            options['legend']   = 'none';
            options['chartArea']= {left:'auto',top:'auto',width:'75%',height:'75%'};
            options['colors']   = [{color: '#B7D2FF'}, {color: '#AAC2EA'}, {color: '#9DB2D5'}, {color: '#91A3C1'}, {color: '#8493AC'},
                                   {color: '#778397'}, {color: '#6A7382'}, {color: '#5E646E'}, {color: '#515459'}, {color: '#444444'}];
            var chart = new google.visualization.PieChart(document.getElementById(json.divID));
            break;
        case 'bar':   var chart = new google.visualization.BarChart(document.getElementById(json.divID));   break;
        case 'column':var chart = new google.visualization.ColumnChart(document.getElementById(json.divID));break;
        case 'guage': var chart = new google.visualization.Guage(document.getElementById(json.divID));      break;
        case 'line':
            options['legend']   = {position:'bottom'};
            options['chartArea']= {left:'auto',top:'auto',width:'70%',height:'75%'};
            var chart = new google.visualization.LineChart(document.getElementById(json.divID));
            break;
    }
    chart.draw(data, options);
    if (typeof json.event != 'undefined') { google.visualization.events.addListener(chart, 'select', function () { var fnstring = json.event; var fn = window[fnstring]; if (typeof fn === "function") fn(chart, data); }); }
}

function drawBizunoTable(json) {
    const data = google.visualization.arrayToDataTable(json.data);
    const options = {
      showRowNumber: false,
      width:         '100%',
      height:        '100%',
      datalessTable: true
    };
    const chart = new google.visualization.LineChart(document.getElementById(json.divID));
    chart.draw(data, options);
    // Build your own perfect dark-mode table
    const table = new google.visualization.Table(document.getElementById(json.tableID));
    table.draw(data, {
        showRowNumber: true,
        width: '100%',
        height: '100%',
        allowHtml: true,
        cssClassNames: {
            headerRow: 'google-table-header',
            tableRow: 'google-table-row',
            hoverTableRow: 'google-table-hover',
            selectedTableRow: 'google-table-selected'
        }
    });
}

/******************* PHREEBOOKS MODULE *********************/
// Javascipt functions to handle operations specific to the PhreeBooks module

/**
 * Sets the referrer to apply a credit from the manager
 * @param {integer} jID - Journal ID of the row
 * @param {integer} cID - Contact ID of the row
 * @param {integer} iID - Record ID of the row
 * @returns NULL
 */
function setCrJournal(jID, cID, iID) {
//    alert('received jID = '+jID+' and cID = '+cID+' and iID = '+iID);
    switch (jID) {
        case  6: jDest = 7;  break;
        default:
        case 12: jDest = 13; break;
    }
    journalEdit(jDest, 0, cID, 'inv', 'journal:'+jID, iID);
}

/**
 * Sets the referrer to apply a payment from the manager
 * @param {integer} jID - Journal ID of the row
 * @param {integer} cID - Contact ID of the row
 * @param {integer} iID - Record ID of the row
 * @returns NULL
 */

function setPmtJournal(jID, cID, iID) {
//    alert('received jID = '+jID+' and cID = '+cID+' and iID = '+iID);
    switch (jID) {
        case  6: jDest = 20; break;
        case  7: jDest = 17; break;
        default:
        case 12: jDest = 18; break;
        case 13: jDest = 22; break;
    }
    journalEdit(jDest, 0, cID, 'inv', 'journal:'+jID, iID);
}

function journalEditSel(jID, rID) {
    switch (jID) {
        case  3:
        case  4:
        case  6:
        case  7:
        case  9:
        case 10:
        case 12:
        case 13: jsonAction('phreebooks/main/getJournalFill&jID='+jID, rID); break;
        default: journalEdit(jID, rID); break;
    }
}

function journalEdit(jID, rID, cID, action, xAction, iID) {
    if (typeof cID    == 'undefined') cID    = 0;
    if (typeof action == 'undefined') action = '';
    if (typeof xAction== 'undefined') xAction= '';
    if (typeof iID    == 'undefined') iID    = 0;
//alert('jID = '+jID+' and rID = '+rID+'and cID = '+cID+' and action = '+action+' and xAction = '+xAction);
    var xVars = '&jID='+jID;
    if (cID) xVars += '&cID='+cID;
    if (iID) xVars += '&iID='+iID;
//    if (action) xVars  += '&bizAction='+action;
    if (xAction) xVars += '&xAction='+xAction;
    var title = bizLangJS('TITLE_J'+jID); //jqBiz('#j'+jID+'_mgr').text();
    document.title = title;
    var p = jqBiz('#accJournal').accordion('getPanel', 1);
    if (p) {
        p.panel('setTitle',title);
        accordionEdit('accJournal', 'dgPhreeBooks', 'divJournalDetail', title, 'phreebooks/main/edit'+xVars, rID, action);
//        jqBiz('#dgPhreeBooks').datagrid('loaded');
//        jqBiz('#divJournalDetail').panel({href:bizunoAjax+'&bizRt=phreebooks/main/edit'+xVars});
//        jqBiz('#accJournal').accordion('select', title);
    }
}

function phreebooksSelectAll() {
    var rowData= jqBiz('#dgJournalItem').datagrid('getData');
    for (var rowIndex=0; rowIndex<rowData.total; rowIndex++) {
        var val  = parseFloat(rowData.rows[rowIndex].bal);
        var price= parseFloat(rowData.rows[rowIndex].price);
        if (isNaN(val)) {
            rowData.rows[rowIndex].qty = '';
        } else {
            rowData.rows[rowIndex].qty = val;
            rowData.rows[rowIndex].total = val * price;
        }
    }
    jqBiz('#dgJournalItem').datagrid('loadData', rowData);
    totalUpdate('fill_all');
}

/**
 * This function either makes a copy of an existing SO/Invoice to the quote journal OR
 * saves to a journal other than the one the current form is set to.
 */
function saveAction(action, newJID) {
    var jID = jqBiz('#journal_id').val();
    var partialInProgress = false;
    if (jqBiz('#id').val()) { // if partially filled, deny save
        var rowData = jqBiz('#dgJournalItem').edatagrid('getData');
        for (var rowIndex=0; rowIndex<rowData.total; rowIndex++) {
            var bal = parseFloat(rowData.rows[rowIndex].bal);
            if (bal) partialInProgress = true;
            if (action == 'saveAs') {
                jqBiz('#dgJournalItem').edatagrid('getRows')[rowIndex]['id'] = 0;
                jqBiz('#dgJournalItem').edatagrid('getRows')[rowIndex]['reconciled'] = 0;
            } // need to create new record
        }
    }
    if (partialInProgress) return alert(bizLangJS('PB_SAVE_AS_LINKED'));
    if (parseFloat(jqBiz('#so_po_ref_id').val()) || parseFloat(jqBiz('#recur_id').val()) || parseFloat(jqBiz('#recur_frequency').val())) {
        return alert(bizLangJS('PB_SAVE_AS_LINKED'));
    }
    if ( jID!='2' && bizCheckBoxGet('closed')) return alert(bizLangJS('PB_SAVE_AS_CLOSED'));
    if ((jID=='3' || jID=='4' || jID=='6') && (newJID=='3' || newJID=='4' || newJID=='6')) {
        jqBiz('#journal_id').val(newJID);
        bizTextSet('invoice_num', ''); // force the next ref ID for the journal saved/moved to
    } else if ((jID=='9' || jID=='10' || jID=='12') && (newJID=='9' || newJID=='10' || newJID=='12')) {
        if (newJID=='12') { jqBiz('#waiting').val('1'); } // force the unshipped flag to be set
        jqBiz('#journal_id').val(newJID);
        bizTextSet('invoice_num', ''); // force the next ref ID for the journal saved/moved to
    } else if (newJID=='2') {
        jqBiz('#journal_id').val(newJID);
    } else alert('Invalid call to Save As...!');
    if (action == 'saveAs') {
        jqBiz('#id').val('0'); // make sure this is posted as a new record
        for (var i=0; i < totalsMethods.length; i++) { // clear the id field for each total method
            var tName = totalsMethods[i];
            jqBiz('#totals_'+tName+'_id').val('0');
        }
    }
    // clear the waiting flag for the following:
    if (newJID=='2' || newJID=='3' || newJID=='4' || newJID=='9' || newJID=='10') { jqBiz('#waiting').val('0'); }
    jqBiz('#frmJournal').submit();
}

/************************** general ledger ********************************************************/
function pbSetPrompt(id, idMin, idMax) {
    var val = bizSelGet(id);
    switch (val) {
        case 'all':  jqBiz('#'+idMin).textbox('hide');  jqBiz('#'+idMax).textbox('hide'); break;
        case 'band': jqBiz('#'+idMin).textbox({prompt:bizLangJS('FROM')}).textbox('show');  jqBiz('#'+idMax).textbox({prompt:bizLangJS('TO')}).textbox('show'); break;
        case 'eq':   jqBiz('#'+idMin).textbox({prompt:bizLangJS('VALUE')}).textbox('show'); jqBiz('#'+idMax).textbox('hide'); break;
        case 'not':  jqBiz('#'+idMin).textbox({prompt:bizLangJS('VALUE')}).textbox('show'); jqBiz('#'+idMax).textbox('hide'); break;
        case 'inc':  jqBiz('#'+idMin).textbox({prompt:bizLangJS('VALUE')}).textbox('show'); jqBiz('#'+idMax).textbox('hide'); break;
    }
}

function setPointer(glAcct, debit, credit) {
    var found = false;
    var arrow = '';
    for (var i=0; i < bizDefaults.glAccounts.rows.length; i++) {
        if (bizDefaults.glAccounts.rows[i]['id'] == glAcct) {
            found = true;
            if (debit  &&  bizDefaults.glAccounts.rows[i]['asset']) arrow = 'inc';
            if (debit  && !bizDefaults.glAccounts.rows[i]['asset']) arrow = 'dec';
            if (credit &&  bizDefaults.glAccounts.rows[i]['asset']) arrow = 'dec';
            if (credit && !bizDefaults.glAccounts.rows[i]['asset']) arrow = 'inc';
            break;
        }
    }
    incdec = '';
    if (found && arrow=='inc')      { incdec = String.fromCharCode(8679)+' '+bizLangJS('PB_GL_ASSET_INC'); }
    else if (found && arrow=='dec') { incdec = String.fromCharCode(8681)+' '+bizLangJS('PB_GL_ASSET_DEC'); }
    var notesEditor = jqBiz('#dgJournalItem').datagrid('getEditor', {index:curIndex,field:'notes'});
    jqBiz(notesEditor.target).val(incdec);
}

function glEditing(rowIndex) {
    curIndex = rowIndex;
    jqBiz('#dgJournalItem').edatagrid('getRows')[rowIndex]['qty'] = 1;
    var glEditor = jqBiz('#dgJournalItem').datagrid('getEditor', {index:rowIndex,field:'gl_account'});
    jqBiz(glEditor.target).combogrid('attachEvent', { event: 'onSelect', handler: function(idx,row){ glCalc('gl', row.id); } });
}

function glCalc(action, glAcct) {
    var glEditor    = jqBiz('#dgJournalItem').datagrid('getEditor', {index:curIndex,field:'gl_account'});
    var descEditor  = jqBiz('#dgJournalItem').datagrid('getEditor', {index:curIndex,field:'description'});
    var debitEditor = jqBiz('#dgJournalItem').datagrid('getEditor', {index:curIndex,field:'debit_amount'});
    var creditEditor= jqBiz('#dgJournalItem').datagrid('getEditor', {index:curIndex,field:'credit_amount'});
    if (!glEditor || !debitEditor || !creditEditor) return; // all editors are not active
    if (typeof glAcct != 'undefined') {
        if (glAcct != jqBiz('#dgJournalItem').edatagrid('getRows')[curIndex]['gl_account']) {
            jqBiz('#dgJournalItem').edatagrid('getRows')[curIndex]['gl_account'] = glAcct;
            jqBiz(glEditor.target).combogrid('setValue', glAcct);
        }
    } else {
        glAcct  = jqBiz(glEditor.target).combogrid('getValue');
    }
    var newDesc = jqBiz(descEditor.target).val();
    var newDebit= debitEditor.target.val();
    if (isNaN(newDebit))  newDebit = 0;
    var newCredit= creditEditor.target.val();
    if (isNaN(newCredit)) newCredit = 0;
//  alert('glCalc action = '+action+' and glAcct = '+glAcct+' and newDebit = '+newDebit+' and newCredit = '+newCredit);
    if (!glAcct && !newDebit && !newCredit) return; // empty row
    switch (action) {
    default:
        case 'gl': return setPointer(glAcct, newDebit, newCredit);
        case 'debit':
            bizNumEdSet('dgJournalItem', curIndex, 'debit_amount',  newDebit);
            if (newDebit != 0) {
                newCredit = 0;
                bizNumEdSet('dgJournalItem', curIndex, 'credit_amount', 0);
            }
            break;
        case 'credit':
            bizNumEdSet('dgJournalItem', curIndex, 'credit_amount', newCredit);
            if (newCredit != 0) {
                newDebit = 0;
                bizNumEdSet('dgJournalItem', curIndex, 'debit_amount', 0);
            }
            break;
    }
    setPointer(glAcct, newDebit, newCredit);
    totalUpdate('glCalc');
    if (rowAutoAdd && jqBiz('#dgJournalItem').edatagrid('getRows').length == (curIndex+1)) { // auto add new row
        rowAutoAdd = false; // disable auto add to prevent infinite loop
        jqBiz('#dgJournalItem').edatagrid('addRow');
        bizNumEdSet('dgJournalItem', curIndex, 'debit_amount',  newCredit);
        bizNumEdSet('dgJournalItem', curIndex, 'credit_amount', newDebit);
        var descEditor  = jqBiz('#dgJournalItem').datagrid('getEditor', {index:curIndex,field:'description'});
        jqBiz(descEditor.target).val(newDesc);
    }
}

function totalsCurrency(newISO, oldISO) {
    bizNumSet('currency_rate', bizDefaults.currency.currencies[newISO].value);
    var len = parseInt(bizDefaults.currency.currencies[newISO].dec_len);
    var sep = bizDefaults.currency.currencies[newISO].sep;
    var dec = bizDefaults.currency.currencies[newISO].dec_pt;
    var rate= bizDefaults.currency.currencies[newISO].value / bizDefaults.currency.currencies[oldISO].value;
    var pfx = bizDefaults.currency.currencies[newISO].prefix ? bizDefaults.currency.currencies[newISO].prefix+' ' : '';
    var sfx = bizDefaults.currency.currencies[newISO].suffix ? ' '+bizDefaults.currency.currencies[newISO].suffix : '';
    // convert the totals fields
    var fldsTotals = ['totals_subtotal','totals_debit','totals_credit','total_balance','totals_balanceBeg','totals_balanceEnd',
        'totals_discount','totals_tax_other','totals_tax_order','totals_tax_item','totals_fee_order','freight','total_amount'];
    for (var i=0; i<fldsTotals.length; i++) {
        if (jqBiz('#'+fldsTotals[i])) {
            jqBiz('#'+fldsTotals[i]).numberbox({decimalSeparator:dec,groupSeparator:sep,precision:len,prefix:pfx,suffix:sfx});
            bizNumSet(fldsTotals[i], jqBiz('#'+fldsTotals[i]).val() * rate);
        }
    }
    // Fix the item table
    dgFields = ['amount','price','discount','total','debit_amount','credit_amount'];
    for (var i=0; i<dgFields.length; i++) { bizDgEdCurSet('dgJournalItem', dgFields[i], newISO); }
    var rowData = jqBiz('#dgJournalItem').edatagrid('getData');
    for (var rowIndex=0; rowIndex<rowData.total; rowIndex++) {
        for (var i=0; i < dgFields.length; i++) {
            newVal = rowData.rows[rowIndex][dgFields[i]] * rate;
            if (isNaN(newVal)) newVal = 0;
            jqBiz('#dgJournalItem').edatagrid('getRows')[rowIndex][dgFields[i]] = newVal;
            var ed = jqBiz('#dgJournalItem').datagrid('getEditor', {index:rowIndex,field:dgFields[i]});
            if (ed) {
                jqBiz(ed.target).numberbox( {decimalSeparator:dec,groupSeparator:sep,precision:len,prefix:pfx,suffix:sfx});
                bizNumEdSet('dgJournalItem', curIndex, dgFields[i], newVal);
            }
        }
        jqBiz('#dgJournalItem').datagrid('refreshRow', rowIndex);
    }
}

/**************************** datagrid support **************************************/
function setFields(rowIndex) {
    bizNumEdSet('dgJournalItem', rowIndex, 'qty', 1);
    bizNumEdSet('dgJournalItem', rowIndex, 'price', 0); // added for orders dg
    bizNumEdSet('dgJournalItem', rowIndex, 'total', 0); // added for orders dg
    bizSelEdSet('dgJournalItem', rowIndex, 'gl_account',  def_contact_gl_acct);
    bizSelEdSet('dgJournalItem', rowIndex, 'tax_rate_id', def_contact_tax_id);
}

/**************************** orders ******************************************************/
function contactsDetail(rID, suffix, fill) {
    jqBiz.ajax({
        url:     bizunoAjax+'&bizRt=contacts/main/details&rID='+rID+'&suffix='+suffix+'&fill='+fill,
        success: function(json) {
            processJson(json);
            if (suffix=='_b') {
                jqBiz('#terms').val(json.contact.terms);
                bizTextSet('terms_text', json.contact.terms_text);
                if (bizDefaults.phreebooks.journalID == 6) { bizDateSet('terminal_date', formatDate(json.contact.terminal_date)); }
                jqBiz('#spanContactProps'+suffix).show();
                if (json.contact.rep_id != 0) { bizSelSet('rep_id', json.contact.rep_id); }
                def_contact_gl_acct = json.contact.gl_account;
                def_contact_tax_id  = json.contact.tax_rate_id < 0 ? 0 : json.contact.tax_rate_id;
                bizSelEdSet('dgJournalItem', curIndex, 'gl_account',  def_contact_gl_acct);
                bizSelEdSet('dgJournalItem', curIndex, 'tax_rate_id', def_contact_tax_id);
                bizSelSet('tax_rate_id', def_contact_tax_id); // set the order level default tax rate
            }
            for (var i = 0; i < json.address.length; i++) { // pull the main address record
                if (json.address[i].type == 'm') {
                    json.address[i]['ref_id'] = rID;
                    addressFill(json.address[i], json.suffix);
                }
            }
            var tmp = new Array();
            jqBiz.each(json.address, function () { if (this.type=='m' || this.type=='b') tmp.push(this); });
            jqBiz('#addressSel'+suffix).combogrid({ data: tmp });
            jqBiz('#addressDiv'+suffix).show();
            bizUncheckBox('AddUpdate'+suffix);
            if (fill == 'both' || suffix=='_s') {
                var tmp = new Array();
                jqBiz.each(json.address, function () {
                    if (this.type=='m') this.address_id = 0; // prevents overriding billing address if selected and add/update checked
                    if (this.type=='m' || this.type=='s') tmp.push(this);
                });
                jqBiz('#addressSel_s').combogrid({ data: tmp });
                jqBiz('#addressDiv_s').show();
            }
            if (suffix=='_b' && json.showStatus=='1') jsonAction('phreebooks/main/detailStatus', json.contact.id);
            if (typeof json.contact.tax_exempt !== 'undefined') { bizCheckboxSet('tax_exempt', json.contact.tax_exempt); }
        }
    });
}

function orderFill(data, type) {
    var gl_account= '';
    var qtyEditor = jqBiz('#dgJournalItem').datagrid('getEditor', {index:curIndex,field:'qty'});
    var skuEditor = jqBiz('#dgJournalItem').datagrid('getEditor', {index:curIndex,field:'sku'});
    var descEditor= jqBiz('#dgJournalItem').datagrid('getEditor', {index:curIndex,field:'description'});
    var glEditor  = jqBiz('#dgJournalItem').datagrid('getEditor', {index:curIndex,field:'gl_account'});
    var taxEditor = jqBiz('#dgJournalItem').datagrid('getEditor', {index:curIndex,field:'tax_rate_id'});
    var qty       = jqBiz(qtyEditor.target).numberbox('getValue'); //handles formatted values
    if (!qty) qty = 1;
    switch (bizDefaults.phreebooks.journalID) {
        case  3:
        case  4:
        case  6:
        case  7: gl_account = data.gl_inv;  break;
        default: gl_account = data.gl_sales;break;
    }
    var def_tax_id = type=='v' ? data.tax_rate_id_v : data.tax_rate_id_c;
    if (def_tax_id == '-1') def_tax_id = def_contact_tax_id;
    var adjDesc  = type=='v' ? data.description_purchase : data.description_sales;
    // adjust for invVendors extension
    if (typeof(data.invVendors) != 'undefined' && data.invVendors && type=='v') {
        var cID = jqBiz('#contact_id_b').val();
        if (cID) {
            invVendors = JSON.parse(data.invVendors);
            if (invVendors.rows) for (var i=0; i<invVendors.rows.length; i++) {
                if (invVendors.rows[i].id == cID) {
                    pkq_qty = parseFloat(invVendors.rows[i].qty_pkg);
                    if (qty < pkq_qty) { qty = pkq_qty; }
                    adjDesc  = invVendors.rows[i].desc;
                    def_tax_id = def_contact_tax_id;
                }
            }
        }
    }
    // set the datagrid source data
    jqBiz('#dgJournalItem').edatagrid('getRows')[curIndex]['qty']           = qty;
    jqBiz('#dgJournalItem').edatagrid('getRows')[curIndex]['sku']           = data.sku;
    jqBiz('#dgJournalItem').edatagrid('getRows')[curIndex]['description']   = adjDesc;
    jqBiz('#dgJournalItem').edatagrid('getRows')[curIndex]['gl_account']    = gl_account;
    jqBiz('#dgJournalItem').edatagrid('getRows')[curIndex]['tax_rate_id']   = def_tax_id;
    jqBiz('#dgJournalItem').edatagrid('getRows')[curIndex]['pkg_length']    = data.length;
    jqBiz('#dgJournalItem').edatagrid('getRows')[curIndex]['pkg_width']     = data.width;
    jqBiz('#dgJournalItem').edatagrid('getRows')[curIndex]['pkg_height']    = data.height;
    jqBiz('#dgJournalItem').edatagrid('getRows')[curIndex]['inventory_type']= data.inventory_type;
    jqBiz('#dgJournalItem').edatagrid('getRows')[curIndex]['item_weight']   = data.item_weight;
    jqBiz('#dgJournalItem').edatagrid('getRows')[curIndex]['qty_stock']     = data.qty_stock;
    jqBiz('#dgJournalItem').edatagrid('getRows')[curIndex]['full_price']    = data.full_price;
    // set the editor values
    jqBiz(qtyEditor.target).numberbox('setValue', qty);
    descEditor.target.val(adjDesc);
    if (glEditor)  jqBiz(glEditor.target).combogrid( 'setValue', gl_account);
    if (taxEditor) jqBiz(taxEditor.target).combogrid('setValue', def_tax_id);
    if (skuEditor) jqBiz(skuEditor.target).combogrid('setValue', data.sku);
    // if purchase, use lead time as date, else sale, use terminal_date as selected by user on order screen
    var jID = jqBiz('#journal_id').val();
    if ((jID=='3' || jID=='4' || jID=='6')) {
        var targetDate = new Date();
        targetDate.setDate(targetDate.getDate() + parseInt(data.lead_time));
        jqBiz('#dgJournalItem').edatagrid('getRows')[curIndex]['date_1'] = formatDate(targetDate);
    } else {
        jqBiz('#dgJournalItem').edatagrid('getRows')[curIndex]['date_1'] = typeof data.date_1 !== 'undefined' ? data.date_1 : bizDateGet('terminal_date');
    }
//  alert('calculating price, curIndex='+curIndex+' and sku = '+data.sku+' and qty = '+qty+' and type = '+type);
    ordersPricing(curIndex, data.sku, qty, type);
}

/**
 * Ajax fetch and fill pricing for a line item, typically called after a user selects an item from the SKU list
 * @param string idx - DOM id
 * @param string sku - line item SKU
 * @param float qty - line item Quantity
 * @param char type - options are c for customers or v for vendors to pull from the proper price sheet
 * @returns filled datagrid values with adjustments for users currency selected
 */
function ordersPricing(idx, sku, qty, type) {
    var cID = jqBiz('#contact_id_b').val();
    if (typeof sku == 'undefined' || sku == '') { return; }
//  alert('ordersPricing idx = '+idx+' and sku = '+sku+' and qty = '+qty+' and type = '+type);
    jqBiz.ajax({
        url: bizunoAjax+'&bizRt=inventory/prices/quote&type='+type+'&cID='+cID+'&sku='+encodeURIComponent(sku)+'&qty='+qty,
        success: function (data) {
            processJson(data);
            iso  = bizSelGet('currency');
            if (data.gl_account !== '') { bizSelEdSet('dgJournalItem', idx, 'gl_account', data.gl_account); }
            xRate= iso != bizDefaults.currency.defaultCur ? bizDefaults.currency.currencies[iso].value : 1;
            bizNumEdSet('dgJournalItem', idx, 'price', data.price * parseFloat(xRate));
            bizNumEdSet('dgJournalItem', idx, 'total', data.price * qty * parseFloat(xRate));
            totalUpdate('ordersPricing');
            if (jqBiz('#dgJournalItem').edatagrid('getRows').length == (idx+1)) { jqBiz('#dgJournalItem').edatagrid('addRow'); } // auto add new row
        }
    });
}

function ordersEditing(rowIndex) {
    curIndex = rowIndex;
    var sku  = jqBiz('#dgJournalItem').edatagrid('getRows')[rowIndex]['sku'];
    var desc = jqBiz('#dgJournalItem').edatagrid('getRows')[rowIndex]['description'];
    if (!sku && !desc) { // blank row, set the defaults
        var glEditor = jqBiz('#dgJournalItem').datagrid('getEditor', {index:rowIndex,field:'gl_account'});
        if (glEditor) {
            jqBiz(glEditor.target).combogrid('setValue',def_contact_gl_acct);
        } else {
            jqBiz('#dgJournalItem').edatagrid('getRows')[rowIndex]['gl_account'] = def_contact_gl_acct;
        }
        var taxEditor = jqBiz('#dgJournalItem').datagrid('getEditor', {index:rowIndex,field:'tax_rate_id'});
        if (taxEditor) jqBiz(taxEditor.target).combogrid('setValue',def_contact_tax_id);
    }
    var skuEditor = jqBiz('#dgJournalItem').datagrid('getEditor', {index:rowIndex,field:'sku'});
    switch (bizDefaults.phreebooks.journalID) { // disable sku editor if linked to SO/PO or at least part of line has been filled
        case  3:
        case  4:
        case  9:
        case 10:
            var bal = jqBiz('#dgJournalItem').edatagrid('getRows')[rowIndex]['bal'];
            if (typeof bal !== 'undefined' && bal > 0) {
                if (skuEditor) jqBiz(skuEditor.target).combogrid({readonly:true}).combogrid('setValue',sku).combogrid('setText',sku);
            }
            break;
        default:
            var item_ref_id= jqBiz('#dgJournalItem').edatagrid('getRows')[rowIndex]['item_ref_id'];
            if (typeof item_ref_id !== 'undefined' && item_ref_id > 0) {
                if (skuEditor) jqBiz(skuEditor.target).combogrid({readonly:true}).combogrid('setValue',sku).combogrid('setText',sku);
            }
            break;
    }
}

function ordersCalc(action) {
    var oldQty   = jqBiz('#dgJournalItem').edatagrid('getRows')[curIndex]['qty'];
    var oldPrice = jqBiz('#dgJournalItem').edatagrid('getRows')[curIndex]['price'];
    var oldTotal = jqBiz('#dgJournalItem').edatagrid('getRows')[curIndex]['total'];
    switch (action) {
        case 'qty':
            newQty   = bizNumEdGet('dgJournalItem', curIndex, 'qty');
            var tmp1 = bizRoundNumber(newQty, false); // check for rounding circular logic
            var tmp2 = bizRoundNumber(oldQty, false);
            if (tmp1 == tmp2) { return; }
//alert('qty change curIdx = '+curIndex+' and precision = '+bizDefaults.locale.precision+' and oldPrice = '+oldPrice+' and newTotal = '+(oldPrice*newQty)+' and newQty = '+newQty);
            bizNumEdSet('dgJournalItem', curIndex, 'qty',   newQty);
            bizNumEdSet('dgJournalItem', curIndex, 'total', newQty*oldPrice);
            var hasSOorPO = parseInt(jqBiz('#so_po_ref_id').val()); // string "0" evaluates to true!
            if (!hasSOorPO && oldQty !== newQty) { // fetch a new price based on the qty change, only if not refered by a SO or Po
                var sku = jqBiz('#dgJournalItem').edatagrid('getRows')[curIndex]['sku'];
                ordersPricing(curIndex, sku, newQty, bizDefaults.phreebooks.type);
            }
            // when uncommented, this prevents qty_so problems when editing (may have been fixed with journal re-design)
            // when commented, automatically opens SO/PO when closed, user may not observe that it was re-opened and when saved, SO/PO is re-opened.
//          if (oldQty !== newQty) jqBiz('#closed').attr('checked', false);
            break;
        case 'price':
            newPrice = bizNumEdGet('dgJournalItem', curIndex, 'price');
            var tmp1 = bizRoundNumber(newPrice, false); // check for rounding circular logic
            var tmp2 = bizRoundNumber(oldPrice, false);
            if (tmp1 == tmp2) { return; }
//alert('price change curIdx = '+curIndex+' and precision = '+bizDefaults.locale.precision+' and newPrice = '+newPrice+' and newTotal = '+(newPrice*oldQty)+' and tmp1 = '+tmp1+' and tmp2 = '+tmp2);
            bizNumEdSet('dgJournalItem', curIndex, 'price', newPrice);
            bizNumEdSet('dgJournalItem', curIndex, 'total', newPrice*oldQty);
            break;
        case 'total':
            newTotal = bizNumEdGet('dgJournalItem', curIndex, 'total');
            var tmp1 = bizRoundNumber(newTotal, false); // check for rounding circular logic
            var tmp2 = bizRoundNumber(oldTotal, false);
            if (tmp1 == tmp2) { return; }
//alert('total change curIdx = '+curIndex+' and precision = '+bizDefaults.locale.precision+' and newTotal = '+newTotal+' and newPrice = '+(newTotal/oldQty)+' and tmp1 = '+tmp1+' and tmp2 = '+tmp2);
            bizNumEdSet('dgJournalItem', curIndex, 'price', newTotal/oldQty);
            bizNumEdSet('dgJournalItem', curIndex, 'total', newTotal);
           break;
    }
    totalUpdate('ordersCalc');
}

/**************************** Banking ******************************************************/
function bankingCalc(action) {
    var discEditor  = jqBiz('#dgJournalItem').datagrid('getEditor', {index:curIndex,field:'discount'});
    var totalEditor = jqBiz('#dgJournalItem').datagrid('getEditor', {index:curIndex,field:'total'});
    if (!discEditor || !totalEditor) return; // editor is not active
    var newDisc = discEditor.target.val();
    if (isNaN(newDisc)) newDisc = 0;
    var newTotal= totalEditor.target.val();
    if (isNaN(newTotal)) newTotal = 0;
//  alert('bankingCalc action = '+action+' and newDisc = '+newDisc+' and newTotal = '+newTotal);
    switch (action) {
        case 'disc':
            var amount  = jqBiz('#dgJournalItem').edatagrid('getRows')[curIndex]['amount'];
            jqBiz('#dgJournalItem').edatagrid('getRows')[curIndex]['discount']= newDisc;
            bizNumEdSet('dgJournalItem', curIndex, 'total', amount - newDisc);
            break;
        case 'direct':
            bizNumEdSet('dgJournalItem', curIndex, 'total', newTotal);
            totalUpdate('bankingCalc');
            break;
    }
}

function bankingEdit(rowIndex) {
    curIndex = rowIndex;
}

/**************************** Order Support Functions ******************************************************/
function inventoryForm(rowData) {
    if (typeof rowData.sku == 'undefined') { return; }
    winOpen('phreeformOpen', 'phreeform/render/open&group=inv:frm&date=a&xfld=inventory.sku&xcr=equal&xmin='+encodeURIComponent(rowData.sku));
}

function inventoryGetAging(rowData) {
    if (typeof rowData.sku == 'undefined') { return; }
    jsonAction('inventory/prices/aging&sku='+encodeURIComponent(rowData.sku));
}

function inventoryGetPrice(rowData, type) {
    if (typeof rowData.sku == 'undefined') { return; }
    jsonAction('inventory/prices/details&cID='+jqBiz('#contact_id_b').val()+'&sku='+encodeURIComponent(rowData.sku)+'&type='+type);
}

function inventoryProperties(rowData) {
    if (typeof rowData.sku == 'undefined') { return; }
    windowEdit('inventory/main/properties&sku='+encodeURIComponent(rowData.sku)+'&qty='+rowData.qty, 'winInvProps', bizLangJS('PROPERTIES'), 800, 600);
}

function shippingEstimate() {
    var data   = { bill:{}, ship:{}, item:[], totals:{} };
    jqBiz("#address_b input").each(function() { if (jqBiz(this).val()) data.bill[jqBiz(this).attr("name")] = jqBiz(this).val(); });
    jqBiz("#address_s input").each(function() { if (jqBiz(this).val()) data.ship[jqBiz(this).attr("name")] = jqBiz(this).val(); });
    var resi   = bizCheckBoxGet('ship_resi');
    jqBiz('#dgJournalItem').edatagrid('saveRow', curIndex);
    var rowData= jqBiz('#dgJournalItem').edatagrid('getData');
    for (var rowIndex=0; rowIndex<rowData.total; rowIndex++) {
        var tmp = {};
        tmp['qty'] = parseFloat(rowData.rows[rowIndex].qty);
        if (isNaN(tmp['qty'])) tmp['qty'] = 0;
        tmp['sku'] = rowData.rows[rowIndex].sku;
        data.item.push(tmp);
    }
    data.storeID = bizSelGet('store_id');
    data.totals['total_amount'] = cleanCurrency(jqBiz('#total_amount').val()) - cleanCurrency(jqBiz('#freight').val());
    var href = bizunoAjax+'&bizRt=shipping/rate/rateMain&resi='+resi+'&data='+encodeURIComponent(JSON.stringify(data));
    var json = { action:'window', id:'shippingEst', title:bizLangJS('SHIPPING_ESTIMATOR'), width:1000, height:600, href:href };
    processJson(json);
}

function selPayment(value) {
    if (value == '') return;
    for (index = 0; index < sel_method_code.length; index++) { jqBiz("#div_"+sel_method_code[index].id).hide('slow'); }
    jqBiz("#div_"+value).show('slow');
    window['payment_'+value]();
}

// *******************  Assemblies  ************************************
function assyUpdateBalance() {
    var onHand = parseFloat(bizNumGet('qty_stock'));
    if (isNaN(onHand)) {
        bizNumSet('qty_stock', 0);
        onHand = 0;
    }
    var qty = parseFloat(bizNumGet('qty'));
    if (isNaN(qty)) {
        bizNumSet('qty', 1);
        qty = 1;
    }
    var rowData= jqBiz('#dgJournalItem').datagrid('getData');
    var total  = 0;
    for (var rowIndex=0; rowIndex<rowData.total; rowIndex++) {
        var unitQty = parseFloat(rowData.rows[rowIndex].qty);
        rowData.rows[rowIndex].qty_required = qty * unitQty;
        total += qty * unitQty;
    }
    jqBiz('#dgJournalItem').datagrid('loadData', rowData);
    jqBiz('#dgJournalItem').datagrid('reloadFooter', [{description: bizLangJS('TOTAL'), qty_required: total}]);
    var bal = onHand+qty;
//    alert('on hand = '+onHand+' and qty = '+qty+' and bal = '+bal);
    bizNumSet('balance', bal);
}

//*******************  Adjustments  ************************************
function adjFill(data) {
    var jID = jqBiz('#journal_id').val();
    var qty = bizNumEdGet('dgJournalItem', curIndex, 'qty');
    if (qty===0) { qty = 1; }
    bizNumEdSet('dgJournalItem', curIndex, 'qty',        qty);
    bizNumEdSet('dgJournalItem', curIndex, 'qty_stock',  cleanNumber(data.qty_stock));
    bizTextEdSet('dgJournalItem',curIndex, 'description',data.description_short);
    if (jID==='15') { bizNumEdSet('dgJournalItem', curIndex, 'balance', cleanNumber(data.qty_stock) - qty); }
    else            { bizNumEdSet('dgJournalItem', curIndex, 'balance', cleanNumber(data.qty_stock) + qty); }
    bizNumEdSet('dgJournalItem', curIndex, 'total',      data.item_cost * qty);
    bizNumEdSet('dgJournalItem', curIndex, 'item_cost',  data.item_cost);
    bizSelEdSet('dgJournalItem', curIndex, 'gl_account', data.gl_inv);
    totalUpdate('adjFill');
}

function adjCalc(action) {
    var jID     = jqBiz('#journal_id').val();
    var newQty  = bizNumEdGet('dgJournalItem', curIndex, 'qty');
    var newTotal= bizNumEdGet('dgJournalItem', curIndex, 'total');
    var onHand  = bizNumEdGet('dgJournalItem', curIndex, 'qty_stock');
//alert('adjCalc action = '+action+' and curIndex = '+curIndex+' and qty = '+newQty+' and total = '+newTotal+' and onHand = '+onHand);
    switch (action) {
        case 'qty':
            if (jID=='16') { bizNumEdSet('dgJournalItem', curIndex, 'balance', onHand + newQty); }
            else           { bizNumEdSet('dgJournalItem', curIndex, 'balance', onHand - newQty); }
            var totalEditor = jqBiz('#dgJournalItem').edatagrid('getEditor', {index:curIndex,field:'total'});
            itemCost = bizNumEdGet('dgJournalItem', curIndex, 'item_cost');
            bizNumEdSet('dgJournalItem', curIndex, 'total', itemCost * newQty);
            break;
        case 'total':
            bizNumEdSet('dgJournalItem', curIndex, 'total', newTotal);
            break;
    }
    totalUpdate('adjCalc');
}

//*******************  Reconciliation  ************************************
lastIndex = -1;
var pauseTotal = true;

function reconInit(row, data) {
    var stmtBal = formatCurrency(data.footer[0].total);
    bizNumSet('stmt_balance', stmtBal);
    pauseTotal = true;
    for (var i=0; i<data.rows.length; i++) {
        if (data.rows[i]['rowChk'] > 0) {
            jqBiz('#tgReconcile').treegrid('checkRow', data.rows[i].id);
            reconCheck(data.rows[i]);
        } else {
            jqBiz('#tgReconcile').treegrid('uncheckRow', data.rows[i].id); // this slows down load but necesary to clear parents during period or acct change
//            reconUncheck(data.rows[i]); // this causes EXTREMELY SLOW page loads, should not be necessary
        }
    }
    pauseTotal = false;
    reconTotal();
}

function reconCheck(row) {
    jqBiz('#tgReconcile').treegrid('update',{ id:row.id, row:{rowChk: true} });
    if (row.id.substr(0, 4) == 'pID_') {
        var node = jqBiz('#tgReconcile').treegrid('getChildren', row.id);
        for (var j=0; j<node.length; j++) {
            jqBiz('#tgReconcile').treegrid('update',{ id:node[j].id, row:{rowChk: true} });
            jqBiz('#tgReconcile').treegrid('checkRow', node[j].id);
        }
    } else if (typeof row._parentId !== 'undefined') {
        reconCheckChild(row._parentId);
    }
}

function reconCheckChild(parentID) {
    var node = jqBiz('#tgReconcile').treegrid('getChildren', parentID);
    var allChecked = true;
    for (var j=0; j<node.length; j++) if (!node[j].rowChk) { allChecked = false; }
    if (allChecked) jqBiz('#tgReconcile').treegrid('update',{ id:parentID, row:{rowChk: true} });
}

function reconUncheck(row) {
    jqBiz('#tgReconcile').treegrid('update',{ id:row.id, row:{rowChk: false} });
    if (row.id.substr(0, 4) == 'pID_') {
        var node = jqBiz('#tgReconcile').treegrid('getChildren', row.id);
        for (var j=0; j<node.length; j++) {
            jqBiz('#tgReconcile').treegrid('update',{ id:node[j].id, row:{rowChk: false} });
            jqBiz('#tgReconcile').treegrid('uncheckRow', node[j].id);
        }
    } else if (typeof row._parentId !== 'undefined') {
        jqBiz('#tgReconcile').treegrid('update',{ id:row._parentId, row:{rowChk: false} });
    }
}

function reconTotal() {
    if (pauseTotal) { return; }
    var openTotal  = 0;
    var closedTotal= 0;
    var items = jqBiz('#tgReconcile').treegrid('getData');
    for (var i=0; i<items.length; i++) {
        if (isNaN(items[i]['total'])) alert('error in total = '+items[i]['total']);
        if (items[i]['id'].substr(0, 4) == 'pID_') {
            var node = jqBiz('#tgReconcile').treegrid('getChildren', items[i]['id']);
            for (var j=0; j<node.length; j++) {
                ttl = parseFloat(node[j]['deposit']) - parseFloat(node[j]['withdrawal']);
                if (node[j]['rowChk']) { closedTotal += ttl; }
                else                    { openTotal += ttl; }
            }
        } else {
            if (items[i]['rowChk']) { closedTotal += parseFloat(items[i]['total']); }
            else                    { openTotal   += parseFloat(items[i]['total']); }
        }
    }
    var stmt  = cleanCurrency(jqBiz('#stmt_balance').val());
    var footer= jqBiz('#tgReconcile').treegrid('getFooterRows');
    var gl    = parseFloat(footer[3]['total']);
    footer[0]['total'] = stmt;
    footer[1]['total'] = closedTotal;
    footer[2]['total'] = openTotal;
    footer[4]['total'] = stmt + openTotal - gl;
    jqBiz('#tgReconcile').datagrid('reloadFooter');
}

function reconcileShowDetails(ref) {
  if(document.all) { // IE browsers
    if (document.getElementById('disp_'+ref).innerText == textHide) {
      document.getElementById('detail_'+ref).style.display = 'none';
      document.getElementById('disp_'+ref).innerText = textShow;
    } else {
      document.getElementById('detail_'+ref).style.display = '';
      document.getElementById('disp_'+ref).innerText = textHide;
    }
  } else {
    if (document.getElementById('disp_'+ref).textContent == textHide) {
      document.getElementById('detail_'+ref).style.display = 'none';
      document.getElementById('disp_'+ref).textContent = textShow;
    } else {
      document.getElementById('detail_'+ref).style.display = '';
      document.getElementById('disp_'+ref).textContent = textHide;
    }
  }
}

function reconcileUpdateSummary(ref) {
  var cnt = 0;
  var rowRef = 'disp_'+ref+'_';
  var checked = document.getElementById('sum_'+ref).checked;
  document.getElementById('disp_'+ref).style.backgroundColor = '';
  while(true) {
    if (!document.getElementById(rowRef+cnt)) break;
    document.getElementById('chk_'+ref).checked = (checked) ? true : false;
    cnt++;
    ref++;
  }
  updateBalance();
}

function reconcileUpdateDetail(ref) {
  var numDetail  = 0;
  var numChecked = 0;
  var rowRef     = 'disp_'+ref+'_';
  var cnt        = 0;
  var origRef    = ref;
  while (true) {
    if (!document.getElementById(rowRef+cnt)) break;
    if (document.getElementById('chk_'+ref).checked) numChecked++;
    numDetail++;
    cnt++;
    ref++;
  }
  if (numChecked == 0) { // none checked
      document.getElementById('disp_'+origRef).style.backgroundColor = '';
    document.getElementById('sum_'+origRef).checked = false;
  } else if (numChecked == numDetail) { // all checked
      document.getElementById('disp_'+origRef).style.backgroundColor = '';
    document.getElementById('sum_'+origRef).checked = true;
  } else { // partial checked
      document.getElementById('disp_'+origRef).style.backgroundColor = 'yellow';
    document.getElementById('sum_'+origRef).checked = true;
  }
  reconcileUpdateBalance();
}

function reconcileUpdateBalance() {
  var value;
  var start_balance = cleanCurrency(document.getElementById('start_balance').value);
  var open_checks   = 0;
  var open_deposits = 0;
  var gl_balance = cleanCurrency(document.getElementById('gl_balance').value);
  for (var i=0; i<totalCnt; i++) {
    if (!document.getElementById('chk_'+i).checked) {
      value = parseFloat(document.getElementById('pmt_'+i).value);
      if (value < 0) {
        if (!isNaN(value)) open_checks -= value;
      } else {
        if (!isNaN(value)) open_deposits += value;
      }
    }
  }
  var sb = new String(start_balance);
  document.getElementById('start_balance').value = formatCurrency(sb);
  var dt = new String(open_checks);
  document.getElementById('open_checks').value = formatCurrency(dt);
  var ct = new String(open_deposits);
  document.getElementById('open_deposits').value = formatCurrency(ct);

  var balance = start_balance - open_checks + open_deposits - gl_balance;
  var tot = new String(balance);
  document.getElementById('balance').value = formatCurrency(tot);
  var numExpr = Math.round(eval(balance) * Math.pow(10, bizDefaults.currency.currencies[bizDefaults.currency.defaultCur].dec_len));
  if (numExpr == 0) {
      document.getElementById('balance').style.color = '';
  } else {
      document.getElementById('balance').style.color = 'red';
  }
}
/*
* jQuery File Download Plugin v1.4.5 - Modified by PhreeSoft to use jqBiz as no conflict
*/
(function($, window){
	// i'll just put them here to get evaluated on script load
	var htmlSpecialCharsRegEx = /[<>&\r\n"']/gm;
	var htmlSpecialCharsPlaceHolders = {
				'<': 'lt;',
				'>': 'gt;',
				'&': 'amp;',
				'\r': "#13;",
				'\n': "#10;",
				'"': 'quot;',
				"'": '#39;' /*single quotes just to be safe, IE8 doesn't support &apos;, so use &#39; instead */
	};
$.extend({
    fileDownload: function (fileUrl, options) {
        var settings = $.extend({
            preparingMessageHtml: null,
            failMessageHtml: null,
            androidPostUnsupportedMessageHtml: "Unfortunately your Android browser doesn't support this type of file download. Please try again with a different browser.",
            dialogOptions: { modal: true },
            prepareCallback: function (url) { },
            successCallback: function (url) { },
            abortCallback: function (url) { },
            failCallback: function (responseHtml, url, error) { },
            httpMethod: "GET",
            data: null,
            checkInterval: 100,
            cookieName: "fileDownload",
            cookieValue: "true",
            cookiePath: "/",
            cookieDomain: null,
            popupWindowTitle: "Initiating file download...",
            encodeHTMLEntities: true
        }, options);
        var deferred = new $.Deferred();
        var userAgent = (navigator.userAgent || navigator.vendor || window.opera).toLowerCase();
        var isIos;                  //has full support of features in iOS 4.0+, uses a new window to accomplish this.
        var isAndroid;              //has full support of GET features in 4.0+ by using a new window. Non-GET is completely unsupported by the browser. See above for specifying a message.
        var isOtherMobileBrowser;   //there is no way to reliably guess here so all other mobile devices will GET and POST to the current window.
        if (/ip(ad|hone|od)/.test(userAgent)) {
            isIos = true;
        } else if (userAgent.indexOf('android') !== -1) {
            isAndroid = true;
        } else {
            isOtherMobileBrowser = /avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|playbook|silk|iemobile|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i.test(userAgent) || /1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|e\-|e\/|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(di|rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|xda(\-|2|g)|yas\-|your|zeto|zte\-/i.test(userAgent.substr(0, 4));
        }
        var httpMethodUpper = settings.httpMethod.toUpperCase();
        if (isAndroid && httpMethodUpper !== "GET" && settings.androidPostUnsupportedMessageHtml) {
            if ($().dialog) {
                $("<div>").html(settings.androidPostUnsupportedMessageHtml).dialog(settings.dialogOptions);
            } else {
                alert(settings.androidPostUnsupportedMessageHtml);
            }
            return deferred.reject();
        }
        var $preparingDialog = null;
        var internalCallbacks = {
            onPrepare: function (url) {
                if (settings.preparingMessageHtml) {
                    $preparingDialog = $("<div>").html(settings.preparingMessageHtml).dialog(settings.dialogOptions);
                } else if (settings.prepareCallback) {
                    settings.prepareCallback(url);
                }
            },
            onSuccess: function (url) {
                if ($preparingDialog) {
                    $preparingDialog.dialog('close');
                }
                settings.successCallback(url);
                deferred.resolve(url);
            },
            onAbort: function (url) {
                if ($preparingDialog) {
                    $preparingDialog.dialog('close');
                };
                settings.abortCallback(url);
                deferred.reject(url);
            },
            onFail: function (responseHtml, url, error) {
                if ($preparingDialog) {
                    $preparingDialog.dialog('close');
                }
                if (settings.failMessageHtml) {
                    $("<div>").html(settings.failMessageHtml).dialog(settings.dialogOptions);
                }
                settings.failCallback(responseHtml, url, error);
                deferred.reject(responseHtml, url);
            }
        };
        internalCallbacks.onPrepare(fileUrl);
        if (settings.data !== null && typeof settings.data !== "string") {
            settings.data = $.param(settings.data);
        }
        var $iframe,
            downloadWindow,
            formDoc,
            $form;
        if (httpMethodUpper === "GET") {
            if (settings.data !== null) {
                var qsStart = fileUrl.indexOf('?');
                if (qsStart !== -1) {
                    if (fileUrl.substring(fileUrl.length - 1) !== "&") {
                        fileUrl = fileUrl + "&";
                    }
                } else {
                    fileUrl = fileUrl + "?";
                }
                fileUrl = fileUrl + settings.data;
            }
            if (isIos || isAndroid) {
                downloadWindow = window.open(fileUrl);
                downloadWindow.document.title = settings.popupWindowTitle;
                window.focus();

            } else if (isOtherMobileBrowser) {
                window.location(fileUrl);
            } else {
                $iframe = $("<iframe>")
                    .hide()
                    .prop("src", fileUrl)
                    .appendTo("body");
            }
        } else {
            var formInnerHtml = "";
            if (settings.data !== null) {
                $.each(settings.data.replace(/\+/g, ' ').split("&"), function () {
                    var kvp = this.split("=");
                    var k = kvp[0];
                    kvp.shift();
                    var v = kvp.join("=");
                    kvp = [k, v];
                    var key = settings.encodeHTMLEntities ? htmlSpecialCharsEntityEncode(decodeURIComponent(kvp[0])) : decodeURIComponent(kvp[0]);
                    if (key) {
                        var value = settings.encodeHTMLEntities ? htmlSpecialCharsEntityEncode(decodeURIComponent(kvp[1])) : decodeURIComponent(kvp[1]);
                    formInnerHtml += '<input type="hidden" name="' + key + '" value="' + value + '" />';
                    }
                });
            }
            if (isOtherMobileBrowser) {
                $form = $("<form>").appendTo("body");
                $form.hide()
                    .prop('method', settings.httpMethod)
                    .prop('action', fileUrl)
                    .html(formInnerHtml);
            } else {
                if (isIos) {
                    downloadWindow = window.open("about:blank");
                    downloadWindow.document.title = settings.popupWindowTitle;
                    formDoc = downloadWindow.document;
                    window.focus();
                } else {
                    $iframe = $("<iframe style='display: none' src='about:blank'></iframe>").appendTo("body");
                    formDoc = getiframeDocument($iframe);
                }
                formDoc.write("<html><head></head><body><form method='" + settings.httpMethod + "' action='" + fileUrl + "'>" + formInnerHtml + "</form>" + settings.popupWindowTitle + "</body></html>");
                $form = $(formDoc).find('form');
            }
            $form.submit();
        }
        setTimeout(checkFileDownloadComplete, settings.checkInterval);
        function checkFileDownloadComplete() {
            var cookieValue = settings.cookieValue;
            if(typeof cookieValue == 'string') {
                cookieValue = cookieValue.toLowerCase();
            }
            var lowerCaseCookie = settings.cookieName.toLowerCase() + "=" + cookieValue;
            if (document.cookie.toLowerCase().indexOf(lowerCaseCookie) > -1) {
                internalCallbacks.onSuccess(fileUrl);
                var cookieData = settings.cookieName + "=; path=" + settings.cookiePath + "; expires=" + new Date(0).toUTCString() + ";";
                if (settings.cookieDomain) cookieData += " domain=" + settings.cookieDomain + ";";
                document.cookie = cookieData;
                cleanUp(false);
                return;
            }
            if (downloadWindow || $iframe) {
                try {
                    var formDoc = downloadWindow ? downloadWindow.document : getiframeDocument($iframe);
                    if (formDoc && formDoc.body !== null && formDoc.body.innerHTML.length) {
                        var isFailure = true;
                        if ($form && $form.length) {
                            var $contents = $(formDoc.body).contents().first();
                            try {
                                if ($contents.length && $contents[0] === $form[0]) {
                                    isFailure = false;
                                }
                            } catch (e) {
                                if (e && e.number == -2146828218) {
                                    isFailure = true;
                                } else {
                                    throw e;
                                }
                            }
                        }
                        if (isFailure) {
                            setTimeout(function () {
                                internalCallbacks.onFail(formDoc.body.innerHTML, fileUrl);
                                cleanUp(true);
                            }, 100);
                            return;
                        }
                    }
                }
                catch (err) {
                    internalCallbacks.onFail('', fileUrl, err);
                    cleanUp(true);
                    return;
                }
            }
            setTimeout(checkFileDownloadComplete, settings.checkInterval);
        }
        function getiframeDocument($iframe) {
            var iframeDoc = $iframe[0].contentWindow || $iframe[0].contentDocument;
            if (iframeDoc.document) {
                iframeDoc = iframeDoc.document;
            }
            return iframeDoc;
        }
        function cleanUp(isFailure) {
            setTimeout(function() {
                if (downloadWindow) {
                    if (isAndroid) {
                        downloadWindow.close();
                    }
                    if (isIos) {
                        if (downloadWindow.focus) {
                            downloadWindow.focus(); //ios safari bug doesn't allow a window to be closed unless it is focused
                            if (isFailure) {
                                downloadWindow.close();
                            }
                        }
                    }
                }
            }, 0);
        }
        function htmlSpecialCharsEntityEncode(str) {
            return str.replace(htmlSpecialCharsRegEx, function(match) {
                return '&' + htmlSpecialCharsPlaceHolders[match];
        	});
        }
        var promise = deferred.promise();
        promise.abort = function() {
            cleanUp();
            $iframe.attr('src', '').html('');
            internalCallbacks.onAbort(fileUrl);
        };
        return promise;
    }
});
})(jqBiz, this || window);
