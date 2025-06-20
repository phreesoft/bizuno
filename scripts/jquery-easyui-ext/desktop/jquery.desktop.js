/**
 * Desktop Extension for jQuery EasyUI
 * version: 1.0.2
 */
(function($){
	var TASKID = 1;

	function buildDesktop(target){
		var state = $(target).data('desktop');
		var opts = state.options;
		$(target).addClass('desktop').layout({

		});
		$(target).layout('add', {
			region: 'south',
			bodyCls: 'desktop-taskbar',
			border: false
		});
		$(target).layout('add', {
			region: 'center',
			bodyCls: 'desktop-wall',
			border: false,
			onResize: function(){
				// rangeApps(target)
			}
		});
		state.taskbar = $(target).layout('panel', 'south');
		state.taskbar.append('<div class="desktop-taskbar-mask"></div><div class="desktop-start"></div><div class="desktop-tasks"></div>');
		if (opts.wallpaper){
			$(target).desktop('setWallpaper', opts.wallpaper);
		}
		if (opts.buttons){
			$(opts.buttons).addClass('desktop-buttons').appendTo(state.taskbar);
		}
		state.taskbar.off('.desktop').on('contextmenu.desktop', function(e){
			opts.onTaskContextMenu.call(target, e);
		});
	}
	function buildStartMenu(target){
		var state = $(target).data('desktop');
		var opts = state.options;
		var start = state.taskbar.children('.desktop-start');
		state.menu = $('<div class="desktop-menu"></div>').appendTo('body');
		state.menu.menu({
			width: 200,
			height: 'auto',
			minHeight: 200,
			noline: true,
			alignTo: start
		});
		_buildMenu(null, opts.menus);
		start.bind('click', function(){
			state.menu.menu('show')
		});

		function _buildMenu(pmenu, menus){
			if (menus && menus.length){
				var parent = pmenu ? state.menu.menu('findItem', pmenu.text) : null;
				$.map(menus, function(menu){
					var submenu = $.extend({}, menu, {
						parent: parent?parent.target:null
					});
					state.menu.menu('appendItem', submenu);
					_buildMenu(submenu, submenu.menus);
				});
			}
		}
	}
	function buildTimer(target){
		var state = $(target).data('desktop');
		var opts = state.options;
		var timer = $('<div class="desktop-timer">4:59 PM</div>').appendTo(state.taskbar);
		var setTime = function(){
			timer.html(opts.timer())
		}
		setTime();
		setInterval(function(){
			setTime();
		},30000);
	}
	function buildApps(target){
		var opts = $(target).desktop('options');
		var wall = $(target).layout('panel', 'center');
		for(var i=0; i<opts.apps.length; i++){
			var app = opts.apps[i];
			var shortcut = $('<div class="desktop-app"><div class="desktop-app-mask"></div></div>').appendTo(wall);
			var img = $('<img class="desktop-app-icon">').appendTo(shortcut);
			img.attr('src', app.icon);
			$('<div class="desktop-app-name"></div>').html(app.name).appendTo(shortcut);
			app.shortcut = shortcut;
			shortcut.unbind('.desktop').bind('click.desktop', {app:app}, function(e){
				if (opts.isDragAction){
					opts.isDragAction = false;
					return;
				}
				openApp(target, e.data.app);
			}).bind('contextmenu.desktop', {app:app}, function(e){
				opts.onShortcutContextMenu.call(target, e, e.data.app);
			});
			app.shortcut.draggable({
				app: app,
				cursor: 'pointer',
				onStopDrag: function(e){
					opts.isDragAction = true;
					var dragOpts = $(this).draggable('options');
					dragOpts.app.shortcutLeft = e.data.left;
					dragOpts.app.shortcutTop = e.data.top;
				}
			});
		}
		rangeApps(target)
	}

	function rangeApps(target){
		var opts = $(target).desktop('options');
		// var apps = $.extend([], opts.apps);
		var apps = [];
		for(var i=0; i<opts.apps.length; i++){
			var app = opts.apps[i];
			if (!app.shortcutLeft && !app.shortcutTop){
				apps.push(app);
			}
		}

		var wall = $(target).layout('panel', 'center');
		var wallHeight = wall.height();
		var rows = Math.floor((wall.height()-20)/(opts.shortcutSize+10));
		var rowspan = (wall.height()-20-opts.shortcutSize*rows)/(rows-1);
		if (rowspan > 20){
			rowspan = 20;
		}
		var groups = [];
		while(apps.length){
			groups.push(apps.splice(0,rows));
		}
		var left = 10;
		for(var i=0; i<groups.length; i++){
			var top = 10;
			$.map(groups[i], function(app){
				$(app.shortcut).css({left:left,top:top});
				top += opts.shortcutSize + rowspan;
			});
			left += opts.shortcutSize + rowspan;
		}
	}

	function minApp(target, app){
		if (!app.win || !app.win.is(':visible')){
			return;
		}
		var opts = app.win.dialog('options');
		opts.originalLeft = opts.left;
		opts.originalTop = opts.top;
		opts.originalWidth = opts.width;
		opts.originalHeight = opts.height;
		var taskOffset = app.task.offset();
		app.win.dialog('open');
		app.win.dialog('dialog').animate({
			left: taskOffset.left,
			top: taskOffset.top,
			width: $(app.task).width(),
			height: $(app.task).height()
		}, function(){
			app.win.dialog('dialog').hide();
		});
	}

	function restoreApp(target, app){
		if (!app.win || app.win.is(':visible')){
			return;
		}
		var opts = app.win.dialog('options');
		app.win.dialog('open');
		app.win.dialog('dialog').show().animate({
			left: opts.originalLeft||opts.left,
			top: opts.originalTop||opts.top,
			width: opts.originalWidth||opts.width,
			height: opts.originalHeight||opts.height
		});
	}

	function openApp(target, app){
		var state = $(target).data('desktop');
		var opts = state.options;
		if (app.win){
			restoreApp(target, app);
			return;
		}
		var winOpts = $.extend({
			cls: 'desktop-window',
			headerCls: 'desktop-window-header',
			app: app,
			inline: true,
			shadow: false,
			closed: true,
			border: 'thin',
			title: app.name,
			iconCls: 'desktop-default-icon',
			width: opts.winWidth,
			height: opts.winHeight,
			collapsible: false,
			minimizable: false,
			maximizable: true,
			resizable: true,
			tools: [{
				iconCls: 'panel-tool-min',
				handler: function(){
					minApp(target, app);
				}
			}],
			onClose: function(){
				$('#'+app.taskId).remove();
				$(this).dialog('destroy');
				app.win = null;
			},
			onMove: function(){
				var opt = $(this).dialog('options');
				app.left = opt.left;
				app.top = opt.top;
			},
			onResize: function(){
				var opt = $(this).dialog('options');
				app.width = opt.width;
				app.height = opt.height;
			}

		}, app);
		app.win = $('<div></div>').appendTo($(target).layout('panel', 'center'));
		app.win.dialog(winOpts);
		var header = app.win.dialog('header');
		if (app.icon){
			header.find('.panel-icon').attr('class','panel-icon').html('<img src="'+app.icon+'">');
		}
		header.bind('dblclick.desktop', function(){
			if (app.win.dialog('options').maximized){
				app.win.dialog('restore');
			} else {
				app.win.dialog('maximize');
			}
		});
		header.find('.panel-tool').children('a').removeAttr('href').css('cursor','pointer');
		app.win.dialog('open');
		app.taskId = 'desktop_task'+(TASKID++);

		app.task = $('<div class="desktop-task"><div class="desktop-task-mask"></div></div>').appendTo(state.taskbar.children('.desktop-tasks'));
		if (app.icon){
			$('<img class="desktop-task-icon">').attr('src', app.icon).appendTo(app.task);
		} else {
			$('<span class="desktop-task-icon desktop-default-icon"></span>').appendTo(app.task);
		}
		$('<span class="desktop-task-name"></span>').html(app.name).appendTo(app.task);
		app.task.attr('id',app.taskId).bind('click', function(){
			if (app.win.is(':visible')){
				if (isTopApp(target, app)) {
					minApp(target, app);
				} else {
					app.win.dialog('open');
				}
			} else {
				restoreApp(target, app);
			}
		}).bind('contextmenu', function(e){
			var el = $(e.target).closest('.desktop-task');
			if (el.length){
				e.stopPropagation();
				opts.onTaskContextMenu.call(target, e, app);
			}
		});
	}

	function isTopApp(target, app){
		var state = $(target).data('desktop');
		var opts = state.options;
		var topIndex = 0;
		for(var i=0; i<opts.apps.length; i++){
			var a = opts.apps[i];
			if (a.win){
				var zindex = a.win.dialog('dialog').css('z-index');
				if (topIndex < zindex){
					topIndex = zindex;
				}
			}
		}
		if (app.win && app.win.dialog('dialog').css('z-index') == topIndex){
			return true;
		} else {
			return false;
		}
	}

	$.fn.desktop = function(options, param){
		if (typeof options == 'string'){
			var method = $.fn.desktop.methods[options];
			if (method){
				return method(this, param);
			}
		}
		options = options || {};
		return this.each(function(){
			var state = $.data(this, 'desktop');
			if (state){
				$.extend(state.options, options);
			} else {
				state = $.data(this, 'desktop', {
					options: $.extend({}, $.fn.desktop.defaults, $.fn.desktop.parseOptions(this), options)
				});
			}
			buildDesktop(this);
			buildStartMenu(this);
			buildTimer(this);
			buildApps(this);
		});

	};
	$.fn.desktop.methods = {
		options: function(jq){
			return jq.data('desktop').options;
		},
		openApp: function(jq, app){
			return jq.each(function(){
				openApp(this, app)
			});
		},
		setWallpaper: function(jq, wallpaper){
			return jq.each(function(){
				$(this).desktop('options').wallpaper = wallpaper;
				$(this).css({
					backgroundImage: 'url("'+wallpaper+'")'
				});
			});
		},
		getOpenedApps: function(jq){
			return $.map(jq.layout('panel','center').children('.desktop-window'), function(win){
				var opts = $(win).children('.window-body').dialog('options');
				return opts.app;
			});
		}
	};
	$.fn.desktop.parseOptions = function(target){
		return $.extend({}, {

		});
	};
	$.fn.desktop.defaults = {
		shortcutSize: 90,
		apps: [],
		menus: [],
		winWidth: 600,
		winHeight: 300,
		wallpaper: null,
		buttons: null,
		timer: function(){
			var now = new Date();
			var h = now.getHours();
			var m = now.getMinutes();
			var ampm = h >= 12 ? 'PM' : 'AM';
			h = h % 12;
			m = m < 10 ? '0'+m : m;
			return (h+':'+m+' '+ampm);
		},
		onShortcutContextMenu: function(e,app){},
		onTaskContextMenu: function(e,app){}
	};
})(jQuery);
