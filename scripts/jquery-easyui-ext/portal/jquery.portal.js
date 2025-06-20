/**
 * portal - jQuery EasyUI
 * version: 1.0.2
 *
 * Dependencies:
 *   draggable
 *   panel
 * 
 */
(function($){
	/**
	 * initialize the portal
	 */
	function initColumns(target){
		$(target).addClass('portal');
		var table = $('<table border="0" cellspacing="0" cellpadding="0"><tr></tr></table>').appendTo(target);
		var tr = table.find('tr');
		
		var columnWidths = [];
		var totalWidth = 0;
		$(target).children('div:first').addClass('portal-column-left');
		$(target).children('div:last').addClass('portal-column-right');
		$(target).find('>div').each(function(){	// each column panel
			var column = $(this);
			totalWidth += column.outerWidth();
			columnWidths.push(column.outerWidth());
			
			var td = $('<td class="portal-column-td"></td>').appendTo(tr)
			column.addClass('portal-column').appendTo(td);
			column.find('>div').each(function(){	// each portal panel
				buildPortalPanel(target, $(this));
			});
		});
		for(var i=0; i<columnWidths.length; i++){
			columnWidths[i] /= totalWidth;
		}
		
		$(target).bind('_resize', function(){
			var opts = $.data(target, 'portal').options;
			if (opts.fit == true){
				setSize(target);
			}
			return false;
		});
		
		return columnWidths;
	}

	function initRows(target){
		$(target).addClass('portal portal-h');
		$(target).children('div').each(function(){
			var row = $(this).addClass('f-row portal-row');
			$(this).children('div').each(function(){
				var view = $('<div class="portal-view"></div>').appendTo(row);
				var p = $(this).appendTo(view);
				buildPortalPanel(target, p);
				view.css('flex', p.panel('options').flex||1);
			});
		});
		$(target).bind('_resize', function(){
			var opts = $.data(target, 'portal').options;
			if (opts.fit == true){
				setSize(target);
			}
			return false;
		});
		return [];
	}

	function initLayout(target){
		$(target).addClass('portal');
		$(target).find('.portal-view').each(function(){
			var p = $(this).children('div');
			buildPortalPanel(target, p);
		});
		$(target).bind('_resize', function(){
			var opts = $.data(target, 'portal').options;
			if (opts.fit == true){
				setSize(target);
			}
			return false;
		});
		return [];
	}

	function buildPortalPanel(target, p){
		p.addClass('portal-p').panel({
			doSize:false,
			cls:'portal-panel'
		});
		var popts = p.panel('options');
		var onMaximize = popts.onMaximize;
		var onRestore = popts.onRestore;
		popts.onMaximize = function(){
			var width = $(target).width();
			var height = $(target).height();
			var top = $(target).scrollTop();
			$(target).addClass('portal-noscroll');
			popts.stub = $('<div></div>').insertAfter(p.panel('panel'));
			p.panel('panel').addClass('portal-maximized').appendTo(target);
			p.panel('resize', {top:top,left:0,width:width,height:height})
			onMaximize.call(p[0]);
		};
		popts.onRestore = function(){
			$(target).removeClass('portal-noscroll');
			p.panel('panel').removeClass('portal-maximized').insertAfter(popts.stub);
			p.panel('resize');
			popts.stub.remove();
			popts.stub = null;
			onRestore.call(p[0]);
		};
		if (p.length){
			makeDraggable(target, p);
		}
	}

	function initCss(){
		if (!$('#easyui-portal-style').length){
			$('head').append(
				'<style id="easyui-portal-style">' +
				'.portal{position:relative;padding:0;margin:0;overflow:auto;border:1px solid #99bbe8;}' +
				'.portal-noborder{border:0;}' +
				'.portal-noscroll{overflow:hidden;}' +
				'.portal .portal-panel{margin-bottom:10px;}' +
				'.portal .portal-view .portal-panel{margin-bottom:0px;}' +
				'.portal .portal-maximized{position:absolute;}' +
				'.portal-column-td{vertical-align:top;}' +
				'.portal-column{padding:10px 0 10px 10px;overflow:hidden;}' +
				'.portal-column-left{padding-left:10px;}' +
				'.portal-column-right{padding-right:10px;}' +
				'.portal-proxy{opacity:0.6;filter:alpha(opacity=60);}' +
				'.portal-spacer{border:3px dashed #eee;margin-bottom:10px;}' +
				'.portal-view{overflow:hidden;padding:5px;flex-shrink:0}' +
				'.portal-h .portal-spacer{margin:5px}' +
				'</style>'
			);
		}
	}
	
	function setSize(target){
		var t = $(target);
		var opts = $.data(target, 'portal').options;
		if (opts.fit){
			var p = t.parent();
			opts.width = p.width();
			opts.height = p.height();
		}
		if (!isNaN(opts.width)){
			t._outerWidth(opts.width);
		} else {
			t.width('auto');
		}
		if (!isNaN(opts.height)){
			t._outerHeight(opts.height);
		} else {
			t.height('auto');
		}
		
		var hasScroll = t.find('>table').outerHeight() > t.height();
		var width = t.width();
		var columnWidths = $.data(target, 'portal').columnWidths;
		var leftWidth = 0;
		
		// calculate and set every column size
		for(var i=0; i<columnWidths.length; i++){
			var p = t.find('div.portal-column:eq('+i+')');
			var w = Math.floor(width * columnWidths[i]);
			if (i == columnWidths.length - 1){
//				w = width - leftWidth - (hasScroll == true ? 28 : 10);
				w = width - leftWidth - (hasScroll == true ? 18 : 0);
			}
			p._outerWidth(w);
			leftWidth += p.outerWidth();
			
			// resize every panel of the column
			p.find('div.portal-p').panel('resize', {width:p.width()});
		}
		if (opts.useLayout){
			$(target).find('.portal-p').panel('resize', {width:'100%'});
		}
		if (opts.dir == 'h'){
			$(target).find('.portal-p').panel('resize', {width:'100%',height:'100%'});
		}
		opts.onResize.call(target, opts.width, opts.height);
	}
	
	/**
	 * set draggable feature for the specified panel
	 */
	function makeDraggable(target, panel){
		var spacer;
		panel.panel('panel').draggable({
			handle:'>div.panel-header>div.panel-title',
			proxy:function(source){
				var p = $('<div class="portal-proxy"></div>').appendTo(target);
				p.width($(source).width());
				p.height($(source).height());
				p.html($(source).html());
				p.find('div.portal-p').removeClass('portal-p');
				return p;
			},
			onBeforeDrag:function(e){
				e.data.startLeft = $(this).offset().left - $(target).offset().left + $(target).scrollLeft();
				e.data.startTop = $(this).offset().top - $(target).offset().top + $(target).scrollTop();
			},
			onStartDrag:function(e){
				// $(this).hide();
				if ($(target).portal('options').dir == 'h'){
					var view = $(this).parent();
					view.hide();
					spacer = $('<div class="portal-spacer"></div>').insertAfter(view);
					spacer.css('flex', view.css('flex'));
				} else {
					$(this).hide();
					spacer = $('<div class="portal-spacer"></div>').insertAfter(this);
					setSpacerSize($(this).outerWidth(), $(this).outerHeight());
				}
				
			},
			onDrag:function(e){
				var p = findPanel(e, this);
				if (p){
					if (p.pos == 'up'){
						spacer.insertBefore(p.target);
					} else {
						spacer.insertAfter(p.target);
					}
					setSpacerSize($(p.target).outerWidth());
				} else {
					var c = findColumn(e);
					if (c){
						if (c.find('div.portal-spacer').length == 0){
							spacer.appendTo(c);
							setSize(target);
							setSpacerSize(c.width());
						}
					}
				}
			},
			onStopDrag:function(e){
				$(this).css('position', '');
				spacer.hide();
				if ($(target).portal('options').dir == 'h'){
					$(this).parent().show();
					$(this).parent().insertAfter(spacer);
				} else {
					$(this).show();
					$(this).insertAfter(spacer);
				}
				
				spacer.remove();
				setSize(target);
				panel.panel('move');
				
				var opts = $.data(target, 'portal').options;
				opts.onStateChange.call(target, panel);
			}
		});
		
		/**
		 * find which panel the cursor is over
		 */
		function findPanel(e, source){
			var opts = $(target).portal('options');
			var result = null;
			$(target).find('div.portal-p').each(function(){
				var pal = $(this).panel('panel');
				if (pal[0] != source){
					var pos = pal.offset();
					if (e.pageX > pos.left && e.pageX < pos.left + pal.outerWidth()
							&& e.pageY > pos.top && e.pageY < pos.top + pal.outerHeight()){
						if (opts.dir == 'h'){
							pal = pal.parent();
							if (e.pageX > pos.left + pal.outerWidth() / 2){
								result = {
									target:pal,
									pos:'down'
								};
							} else {
								result = {
									target:pal,
									pos:'up'
								};
							}
						} else {
							if (e.pageY > pos.top + pal.outerHeight() / 2){
								result = {
									target:pal,
									pos:'down'
								};
							} else {
								result = {
									target:pal,
									pos:'up'
								}
							}
						}
					}
				}
			});
			return result;
		}
		
		/**
		 * find which portal column the cursor is over
		 */
		function findColumn(e){
			var opts = $(target).portal('options');
			var result = null;
			var col = '.portal-column';
			if (opts.dir == 'h'){
				col = '.portal-row';
			} else if (opts.useLayout){
				col = '.portal-row';
			}
			$(target).find(col).each(function(){
				var pal = $(this);
				var pos = pal.offset();
				if (e.pageX > pos.left && e.pageX < pos.left + pal.outerWidth() &&
					e.pageY > pos.top && e.pageY < pos.top + pal.outerHeight()) {
					result = pal;
				}
			});
			return result;
		}
		
		/**
		 * set the spacer size
		 */
		function setSpacerSize(width, height){
			spacer._outerWidth(width);
			if (height){
				spacer._outerHeight(height);
			}
		}
	}
	
	
	$.fn.portal = function(options, param){
		if (typeof options == 'string'){
			return $.fn.portal.methods[options](this, param);
		}
		
		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'portal');
			if (state){
				$.extend(state.options, options);
			} else {
				var opts = $.extend({}, $.fn.portal.defaults, $.fn.portal.parseOptions(this), options);
				state = $.data(this, 'portal', {
					options: opts,
					columnWidths: opts.useLayout ? initLayout(this) : (opts.dir=='h' ? initRows(this) : initColumns(this))
				});
			}
			if (state.options.border){
				$(this).removeClass('portal-noborder');
			} else {
				$(this).addClass('portal-noborder');
			}
			initCss();
			setSize(this);
		});
	};
	
	$.fn.portal.methods = {
		options: function(jq){
			return $.data(jq[0], 'portal').options;
		},
		resize: function(jq, param){
			return jq.each(function(){
				if (param){
					var opts = $.data(this, 'portal').options;
					if (param.width) opts.width = param.width;
					if (param.height) opts.height = param.height;
				}
				setSize(this);
			});
		},
		getPanels: function(jq, columnIndex){
			var opts = jq.portal('options');
			var c = jq;	// the panel container
			if (columnIndex >= 0){
				if (opts.dir == 'h'){
					c = jq.find('div.portal-row:eq(' + columnIndex + ')');
				} else {
					c = jq.find('div.portal-column:eq(' + columnIndex + ')');
				}
			}
			var panels = [];
			c.find('div.portal-p').each(function(){
				panels.push($(this));
			});
			return panels;
		},
		add: function(jq, param){	// param: {panel,columnIndex}
			return jq.each(function(){
				var opts = $(this).portal('options');
				if (opts.dir == 'h'){
					var c = $(this).find('div.portal-row:eq(' + (param.rowIndex!=null?param.rowIndex:param.columnIndex) + ')');
					var p = param.panel.addClass('portal-p');
					var view = $('<div class="portal-view"></div>').appendTo(c);
					view.css('flex', p.panel('options').flex||1);
					p.panel('panel').addClass('portal-panel').appendTo(view);
					makeDraggable(this, p);
					$(this).portal('resize');
				} else {
					var c = $(this).find('div.portal-column:eq(' + param.columnIndex + ')');
					var p = param.panel.addClass('portal-p');
					p.panel('panel').addClass('portal-panel').appendTo(c);
					makeDraggable(this, p);
					p.panel('resize', {width:c.width()});
				}
			});
		},
		remove: function(jq, panel){
			return jq.each(function(){
				var opts = $(this).portal('options');
				var panels = $(this).portal('getPanels');
				for(var i=0; i<panels.length; i++){
					var p = panels[i];
					if (p[0] == $(panel)[0]){
						var view = opts.dir=='h' ? p.closest('.portal-view') : $();
						p.panel('destroy');
						view.remove();
					}
				}
				$(this).portal('resize');
			});
		},
		disableDragging: function(jq, panel){
			panel.panel('panel').draggable('disable');
			return jq;
		},
		enableDragging: function(jq, panel){
			panel.panel('panel').draggable('enable');
			return jq;
		}
	};
	
	$.fn.portal.parseOptions = function(target){
		var t = $(target);
		return {
			width: (parseInt(target.style.width) || undefined),
			height: (parseInt(target.style.height) || undefined),
			border: (t.attr('border') ? t.attr('border') == 'true' : undefined),
			fit: (t.attr('fit') ? t.attr('fit') == 'true' : undefined),
			useLayout: (t.attr('useLayout') ? t.attr('useLayout') == 'true' : undefined)
		};
	};
	
	$.fn.portal.defaults = {
		width:'auto',
		height:'auto',
		border:true,
		fit:false,
		dir:'v',	// 'h'(horizontal) or 'v'(vertical)
		useLayout:false,
		onResize:function(width,height){},
		onStateChange:function(panel){}
	};
})(jQuery);
