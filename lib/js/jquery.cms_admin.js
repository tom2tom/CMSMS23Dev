/*
 * CMSMS Admin Functions
 * Copyright (C) 2004-2012 Ted Kulp <ted@cmsmadesimple.org>
 * Copyright (C) 2012-2018 The CMSMS Dev Team <coreteam@cmsmadesimple.org>
 * This file is a component of CMS Made Simple <http://www.cmsmadesimple.org>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */
/*!
CMSMS Admin Functions
(C) 2012-2018 <coreteam@cmsmadesimple.org>
License GPL2+
*/
(function(global, $) {
    '$:nomunge';
    'use strict';
    /*jslint nomen: true , devel: true*/
    var method,
        noop = function() {},
        methods = [
            'assert', 'clear', 'count', 'debug', 'dir', 'dirxml', 'error',
            'exception', 'group', 'groupCollapsed', 'groupEnd', 'info', 'log',
            'markTimeline', 'profile', 'profileEnd', 'table', 'time', 'timeEnd',
            'timeStamp', 'trace', 'warn'
        ],
        length = methods.length,
        console = (window.console = window.console || {});
    while(length--) {
        method = methods[length];
        // Only stub undefined methods.
        if(!console[method]) {
            console[method] = noop;
        }
    }

    /**
     * @namespace Namespace for CMSMS_Admin classes and functions
     */
    var CMSMS_Admin = global.CMSMS_Admin || {};
    /*
     * Initialize CMSMS_Admin app
     */
    $(document).ready(function() {
        CMSMS_Admin.Loader.init();
    });

    /**
     * @function namespace
     * @description A helper function to create a general purpose namespace method
     * allows to create namespace a bit easier
     * @param {String} namespace The name of namespace to be used
     */
    CMSMS_Admin.namespace = function(namespace) {
        var parts = namespace.split('.'),
            parent = CMSMS_Admin,
            i,
            partname;
        if(parts[0] === 'CMSMS_Admin') {
            parts = parts.slice(1);
        }
        for(i = 0; i < parts.length; i += 1) {
            partname = parts[i];
            if(typeof parent[partname] === 'undefined') {
                parent[partname] = {};
            }
            parent = parent[partname];
        }
        return parent;
    };

    /* =============================
     * CMSMS_Admin loader functions
     * ============================= */

    /**
     * @namespace Namespace for CMSMS_Admin.Loader functions
     */
    var cms_loader = CMSMS_Admin.namespace('CMSMS_Admin.Loader');
/* TODO this causes duplication on jQuery 2.x WHY ?
    cms_loader.reload = function() {
        // Reload functions after ajax success where needed
        $(document).ajaxComplete(function() {
            CMSMS_Admin.Helper.cms_helpDialog();
            CMSMS_Admin.Helper.cms_initTooltips();
        });
    };
*/
    cms_loader.init = function() {
//        CMSMS_Admin.Loader.reload();
        CMSMS_Admin.Helper.cms_resizableTextArea();
        CMSMS_Admin.Helper.cms_helpDialog();
        CMSMS_Admin.Helper.cms_initTabs();
        CMSMS_Admin.Helper.cms_initModalDialog();
        CMSMS_Admin.Helper.cms_initTooltips();
        CMSMS_Admin.Helper.cms_uploadLimit();
    };

    /* =============================
     * CMSMS_Admin helper functions
     * ============================= */

    /**
     * @namespace Namespace for CMSMS_Admin.Helper functions
     */
    var cms_helper = CMSMS_Admin.namespace('CMSMS_Admin.Helper');
    // TODO apply these things only where relevant, on-demand

    /**
     * @description Handle situation where multiple uploads together exceed upload limit
     */
    cms_helper.cms_uploadLimit = function() {
        $('form').on('submit', function(ev) {
            if($(this).attr('novalidate')) {
                return;
            }
            var ups = $('input[type=file]', this);
            if(ups.length === 0) {
                return;
            }
            var total = 0;
            ups.each(function(idx, el) {
                if(el.files.length === 0) {
                    return;
                }
                total += el.files[0].size;
            });
            if(typeof cms_data !== 'undefined' &&
               typeof cms_data.max_upload_size !== 'undefined' &&
               cms_data.max_upload_size > 0 &&
               total > cms_data.max_upload_size) {
                 cms_alert(cms_lang('largeupload'));
                 return false;
            }
        });
    };

    /**
     * @description Detects if Browser supports textarea resize property or .cms_resizable class was applied to
     * textarea element, if conditions match jQueryUI .resizable() plugin is applied
     * @requires jQueryUI
     */
    cms_helper.cms_resizableTextArea = function() {
        // create textarea element for testing
        var textarea = document.createElement('textarea');
        $('textarea').each(function() {
            var $this = $(this);
            if((textarea.style.resize === undefined || $this.hasClass('cms_resizable')) && (!$this.hasClass('MicroTiny')) && (!$this.hasClass('no-resize'))) {
                $this.resizable({
                    handles: 'se',
                    ghost: true
                });
            }
        });
    };

    /**
     * @description handles clicks on all cms_helpicon-class images. help text is loaded via ajax.
     * @function
     */
    cms_helper.cms_helpDialog = function() {
        $('.cms_help img.cms_helpicon').on('click', function() {
            var data = $(this).parent().data(),
                key = data.cmshelpKey,
                self = this;
            $.get(cms_data.ajax_help_url, {
                key: key
            }, function(text) {
                cms_help(self, data, text);
            });
        });
    };

    /**
     * @description Initializes tabbed content for CMSMS admin pages
     * @function
     */
    cms_helper.cms_initTabs = function() {
        function _cms_activateTab(index) {
            var container = $('#navt_tabs');
            if(container.length === 0) {
                container = $('#page_tabs');
            }
            container.find('div:eq(' + index + ')').trigger('mousedown');
        }
        var tabs = $('#navt_tabs, #page_tabs').find('div');
        tabs.on('mousedown', function() {
            var $this = $(this);
            tabs.each(function() {
                var tab = $(this);
                tab.removeClass('active');
                $('#' + tab.attr('id') + '_c').hide();
            });
            $this.addClass('active');
            $('#' + $this.attr('id') + '_c').show();
            return true;
        });
        tabs.on('focus', function(ev) {
            $(this).addClass('focus');
        });
        tabs.on('blur', function(ev) {
            $(this).removeClass('focus');
        });
        tabs.on('keyup', function(ev) {
            if(ev.keyCode == $.ui.keyCode.ENTER) {
                var _i = tabs.index(this);
                _cms_activateTab(_i);
            }
        });
        // intialize active tab
        tabs.attr('tabindex', '0');
        if(tabs.filter('.active').mousedown().length === 0) {
            _cms_activateTab(0);
        }
    };

    /**
     * @description initalizes jQueryUI .dialog() plugin to any element with class .dialog and modal window mode.
     * Element to open the dialog needs class .open.
     * @requires jQueryUI
     */
    cms_helper.cms_initModalDialog = function() {
        // dialogs is Object
        var dialogs = {};
        $('.dialog').each(function() {
            var $this = $(this),
                dialog_id = $(this).prev('.open').attr('title');
            // intialize .dialog() plugin
            dialogs[dialog_id] = $this.dialog({
                autoOpen: false,
                modal: true
            });
        });
        // handle dialog open link
        $('.open').on('click', function(e) {
            e.preventDefault();
            var $this = $(this),
                dialog_id = $this.attr('title');
            dialogs[dialog_id].dialog('open').removeClass('invisible');
            return false;
        });
    };

    cms_helper.cms_initTooltips = function() {
        $('.tooltip').tooltip({
            items: '[title], [data-cms-description], [data-cms-ajax]',
            content: function(callback) {
                var el = $(this),
                    data = el.data(),
                    content,
                    url;
                // for longer descriptions
                if(el.is('[data-cms-description]')) {
                    content = data.cmsDescription;
                    return content;
                }
                // for ajax content
                if(el.is('[data-cms-ajax]')) {
                    url = data.cmsAjax;
                    url += "&cmsjobtype=1";
                    //console.debug(url);
                    $.ajax({
                        url: url,
                        async: true,
                        dataType: 'html',
                        error: function(jqXHR, textStatus, errorThrown) {
                            console.log('Sorry. There was a error in your request: ' + textStatus + ' ' + errorThrown);
                        },
                        success: function(content) {
                            callback(content);
                        }
                    });
                }
                // simple title tooltip
                if(el.is('[title]')) {
                    return el.attr('title');
                }
            }
        });
    };

    /**
     * @description Initializes jQueryUI widgets without JS using HTML5 data- attributes
     * Usage example: <div data-jqui="draggable" data-add-classes="false" data-axis="x">This is draggable</div>
     * @author Lukas Olson
     * @copyright Lukas Olson https://github.com/lukasolson/jQuery-UI-Markup
     * @license https://github.com/lukasolson/jQuery-UI-Markup/blob/master/license
     * @requires jQueryUI
     */
    cms_helper.cms_jquiMarkup = function() {
        $('[data-jqui]').each(function(i, el) {
            var options = $(el).data();
            $.each(options.jqui.split(/\s+/), function(i, method) {
                $(el)[method](options);
            });
        });
    };
}(this, jQuery));

/* =======================
 * GLOBAL PLUGIN FUNCTIONS
 * =======================*/

/**
 * @description toggles all checkboxes from closest target inisde a table row when specified checkbox is checked
 * @requires jQuery
 * @example
 * $('#selectall').cmsms_checkall();
 */
(function($) {
    '$:nomunge';
    'use strict';
    /*jslint nomen: true , devel: true*/
    var cmsms_checkall = 'cmsms_checkall',
        defaults = {
            target: 'table'
        };

    function Plugin(element, options) {
        this.element = element;
        this.settings = $.extend({}, defaults, options);
        this.defaults = defaults;
        this._name = cmsms_checkall;
        this.init();
    }
    Plugin.prototype = {
        init: function() {
            this._toggle(this.element, this.settings.target);
        },
        // @ignore
        _toggle: function(obj, container) {
            var target = $(obj).closest(container),
                $el = $(obj);
            // Handle single checkbox click
            $('[type=checkbox]', target).not($el).on('click', function() {
                var $this = $(this),
                    v = $this.prop('checked', !$this.prop('checked'));
                $el.prop('checked', false);
                $this.prop('checked', !$this.prop('checked'));
                $this.trigger('cms_checkall_toggle', {
                    checked: v
                });
            });
            // toggle all checkboxes on obj click
            $el.on('click', function() {
                var v = $el.is(':checked');
                $('[type=checkbox]', target).each(function() {
                    var $this = $(this);
                    $this.attr('checked', v);
                    $this.trigger('cms_checkall_toggle', {
                        checked: v
                    });
                });
            });
        }
    };
    $.fn[cmsms_checkall] = function(options) {
        return this.each(function() {
            if(!$.data(this, 'plugin_' + cmsms_checkall)) {
                $.data(this, 'plugin_' + cmsms_checkall, new Plugin(this, options));
            }
        });
    };
}(jQuery));

/**
 * @description Intializes jQueryUI .sortable() widget on specified table element
 * @param {String} actionurl The URL for the action that should be performed on update event
 * @param {callback} The callback that handles the response after ui.sortable update event
 * @callback callback
 * @requires jQueryUI
 */
(function($) {
    '$:nomunge';
    'use strict';
    /*jslint nomen: true , devel: true, regexp: true*/
    $.widget('cmsms.cmsms_sortable_table', $.extend({}, $.ui.sortable.prototype, {
        options: {
            actionurl: null,
            update: null,
            helper: null,
            callback: function(data) {}
        },
        _create: function() {
            var self = this;
            this.element.data('sortable', this.element.data('cmsms_sortable_table'));
            this.options.update = function(event, ui) {
                self._update(self.options, self.element);
            };
            this.options.helper = this._uiFixHelper;
            return $.ui.sortable.prototype._create.apply(this, arguments);
        },
        // @ignore override update option
        _update: function(options, el) {
            var url = options.actionurl,
                info = this.serialize($(el));
            $(el).find('tr:even').attr('class', 'row1');
            $(el).find('tr:odd').attr('class', 'row2');
            $.post(url + '&' + info, function(data) {
                options.callback(data);
            });
        },
        serialize: function(o) {
            var items = this._getItemsAsjQuery(o && o.connected),
                str = [];
            o = o || {};
            $(items).each(function() {
                var res = ($(o.item || this).attr(o.attribute || 'id') || '').match(o.expression || (/(.+)[\-=_](.+)/));
                if(res) {
                    str.push((o.key || res[1] + '[]') + '=' + (o.key && o.expression ? res[1] : res[2]));
                }
            });
            if(!str.length && o.key) {
                str.push(o.key + "=");
            }
            return str.join('&');
        },
        // @ignore fix Ui helper for tables
        _uiFixHelper: function(e, ui) {
            ui.children().each(function() {
                $(this).width($(this).width());
            });
            return ui;
        }
    }));
    $.cmsms.cmsms_sortable_table.prototype.options = $.extend(
        {},
        $.ui.sortable.prototype.options,
        $.cmsms.cmsms_sortable_table.prototype.options
    );
}(jQuery));

/* ===================
 *   GLOBAL FUNCTIONS
 * =================== */

/**
 * @description global data store, supplemented before and during runtime
 * by various means: ajax, hooklist ...
 */
if(typeof cms_data === 'undefined') {
    var cms_data = {};
}
cms_data = $.extend(cms_data, {
    //optional replacement methods
    alertfunc: null, //for popup alerts
    dialogfunc: null, //for complex popups
    confirmfunc: null, //for popup confirmations
    notifyfunc: null, //for notifications
    promptfunc: null, //for popup prompts
    //notice accumulators, updated during session
    infonotices: null,
    successnotices: null,
    warnnotices: null,
    errornotices: null
});

/**
 * @description cms_data lang-key getter
 * @function cms_lang(key)
 * @param {String} key element identifier
 */
function cms_lang(key) {
    'use strict';
    key = 'lang_' + key;
    if(typeof cms_data[key] !== 'undefined') {
        return cms_data[key];
    }
    cms_alert('lang key ' + key + ' not set');
}

/**
 * @description a shiv for IE11. ... remove me ASAP
 * @function
 */
if(!String.prototype.startsWith) {
    String.prototype.startsWith = function(searchString, position) {
        position = position || 0;
        return this.substr(position, searchString.length) === searchString;
    };
}

/**
 * @description jQuery backwards compatibility for togglecollapse function
 * @function togglecollapse(cid)
 * @param {String} cid The id name of Element toggle
 */
function togglecollapse(cid) {
    'use strict';
    $('#' + cid).toggle();
}

/**
 * @description pop up a draggable info/help dialog.
 * @function
 * @param {Object} tgt  The page-element whose activation initiated the help
 * @param {Object} data Parameters, of which 'cmshelpTitle' is interrogated here
 * @param {String} text The text to be included in the body of the dialog
 * @requires jquery-toast
 */
function cms_help (tgt, data, text) {
    var offs = $(tgt).offset(),
        top = offs.top - $(window).scrollTop(),
        left = offs.left - $(window).scrollLeft();
    data.lasty = top;
    data.lastx = left;
    var h = data.cmshelpTitle,
        p = h.indexOf('(');
    if(p !== -1) {
        h = h.substring(0, p - 1);
    }
    var title = cms_lang('title_help') + ': ' + h;
    $.toast({
        text: text,
        heading: title,
        showHideTransition: 'fade',
        hideAfter: 0,
        loader: false,
        position: {
          top: top,
          left: left
        },
        myclass: 'info',
        closeicon: '',
        beforeShow: function(el) {
            var $hd = $(el).find('.jqt-heading');
            if($hd.length > 0) {
                //drag it from the header
                $hd[0].onmousedown = dragMouseDown;
            } else {
                //drag it from anywhere inside
                el.onmousedown = dragMouseDown;
            }

            function dragMouseDown(ev) {
                ev = ev || window.event;
                // log the pointer's element-relative position at startup
                data.offy = ev.clientY - data.lasty;
                data.offx = ev.clientX - data.lastx;
                // stop dragging when pointer-device button released
                document.onmouseup = function () {
                    document.onmouseup = null;
                    document.onmousemove = null;
                };
                // process button-down pointer-moves
                document.onmousemove = function (ev) {
                    ev = ev || window.event;
                    // calculate the new cursor position
                    var newy = ev.clientY - data.offy,
                        newx = ev.clientX - data.offx;
                    if (Math.abs(newy - data.lasty) > 2 || Math.abs(newx - data.lastx) > 2) { //debounce
                        // set the element's new position
                        $(el).css({
                           'top': newy,
                           'left': newx
                        });
                        data.lasty = newy;
                        data.lastx = newx;
                    }
                };
            }
        }
    });
}

/**
 * @description display a popup dialog including caller-specified content (default jquery-ui dialog)
 * @function cms_dialog(content, opts)
 * @param (object) content jquery object representing the content to be displayed
 * @param (mixed) opts Optional scalar property, or properties object, to be applied to the dialog
 */
function cms_dialog(content, opts) {
    'use strict';
    if(typeof opts === 'undefined') {
        opts = {};
    }
    if (typeof opts === 'object' && opts !== null && typeof opts.closeText === 'undefined') {
        opts.closeText = cms_lang('close');
    }
    if(typeof cms_data.dialogfunc !== 'function') {
        content.dialog(opts);
    } else {
        cms_data.dialogfunc(content,opts);
    }
}

/**
 * @description display a modal prompt dialog (default jquery-ui .dialog)
 * @function cms_alert(msg, title)
 * @param (String) msg The input prompt to display
 * @param (String) suggest Optional input value to display
 * @param (String) title Optional title string
 * @return promise
 */
function cms_prompt(msg, suggest, title) {
    'use strict';
    if(typeof msg === 'undefined' || msg == '') return;
    if(typeof suggest === 'undefined') suggest = '';
    if(typeof title === 'undefined') title = '';
    if(typeof cms_data.promptfunc !== 'function') {
        if($('#cmsms_promptDialog').length === 0) {
            $('<div id="cmsms_promptDialog" style="display:none;"></div>').insertAfter('body');
        }
        var content = '<h4 class="prompt">'+msg+'</h4><input type="text" class="prompt" value="'+suggest+'" />';
        var yestxt = cms_lang('ok'),
            notxt = cms_lang('cancel'),
            _d = $.Deferred();
        $('#cmsms_promptDialog').html(content).dialog({
            modal: true,
            title: title,
            width: 'auto',
            buttons: [{
                    text: yestxt,
                    icons: {
                        primary: 'ui-icon-check'
                    },
                    click: function(ev) {
                        content = $('#cmsms_promptDialog input').val();
                        $(this).dialog('close');
                        if (content) {
                           _d.resolve(content);
                        } else {
                           _d.reject(null);
                        }
                    }
                },
                {
                    text: notxt,
                    icons: {
                        primary: 'ui-icon-close'
                    },
                    click: function(ev) {
                        $(this).dialog('close');
                        _d.reject(null);
                    }
                }
            ]
        });
        return _d.promise();
    }
    return cms_data.promptfunc(msg, suggest, title);
}

/**
 * @description display a modal alert dialog (default jquery-ui .dialog)
 * @function cms_alert(msg, title)
 * @param (String) msg The message to display
 * @param (String) title An optional title string.
 * @return promise
 */
function cms_alert(msg, title) {
    'use strict';
    if(typeof msg === 'undefined' || msg == '') return;
    if(typeof title === 'undefined' || title === '') title = cms_lang('alert');

    if(typeof cms_data.alertfunc !== 'function') {
        if($('#cmsms_errorDialog').length === 0) {
            $('<div id="cmsms_errorDialog" style="display:none;"></div>').insertAfter('body');
        }
        var _d = $.Deferred()
          label = cms_lang('close');
        $('#cmsms_errorDialog').html(msg).dialog({
            modal: true,
            title: title,
            buttons: [{
               text: label,
               icons: {
                   primary: 'ui-icon-close'
               },
               click: function(ev) {
                   $(this).dialog('close');
                   _d.resolve();
               }
            }],
            close: function(event, ui) {
                _d.resolve();
            }
        });
        return _d.promise();
    }
    return cms_data.alertfunc(msg, title);
}

/**
 * @description display a modal confirm dialog (default jquery-ui .dialog)
 * @function
 * @param (String) msg The message to display
 * @param (String) title Optional title string
 * @param (String) yestxt Optional text for the yes button
 * @param (String) notxt Optional text for the no button
 * @return promise
 */
function cms_confirm(msg, title, yestxt, notxt) {
    'use strict';
    if(typeof msg === 'undefined' || msg == '') return;
    if(typeof title === 'undefined' || title == '') title = cms_lang('confirm');
    if(typeof yestxt === 'undefined' || yestxt == '') yestxt = cms_lang('ok');
    if(typeof notxt === 'undefined' || notxt == '') notxt = cms_lang('cancel');

    if(typeof cms_data.confirmfunc !== 'function') {
        if($('#cmsms_confirmDialog').length === 0) {
            $('<div id="cmsms_confirmDialog" style="display:none;"></div>').insertAfter('body');
        }
        var _d = $.Deferred();
        $('#cmsms_confirmDialog').html(msg).dialog({
            modal: true,
            title: title,
            buttons: [{
                    text: yestxt,
                    icons: {
                        primary: 'ui-icon-check'
                    },
                    click: function(ev) {
                        $(this).dialog('close');
                        _d.resolve(yestxt);
                    }
                },
                {
                    text: notxt,
                    icons: {
                        primary: 'ui-icon-close'
                    },
                    click: function(ev) {
                        $(this).dialog('close');
                        _d.reject(notxt);
                    }
                }
            ]
        });
        return _d.promise();
    }
    return cms_data.confirmfunc(msg, title, yestxt, notxt);
}

/**
 * @description display a modal confirm dialog (default jquery-ui .dialog)
 * then activate the specified element if confirmed
 * @function
 * @param (object) btn form input-submit or button element
 * @param (String) msg The message to display
 * @param (String) title Optional title string
 * @param (String) yestxt Optional text for the yes button
 * @param (String) notxt Optional text for the no button
 * @return promise
 */

function cms_confirm_btnclick(btn, msg, title, yestxt, notxt) {
    cms_confirm(msg, title, yestxt, notxt).done(function() {
        $(btn).unbind('click').click();
    });
}

/**
 * @description display a modal confirm dialog (default jquery-ui .dialog)
 * then go to the specified link's url if confirmed
 * @function
 * @param (object) link form anchor element
 * @param (String) msg The message to display
 * @param (String) title Optional title string
 * @param (String) yestxt Optional text for the yes button
 * @param (String) notxt Optional text for the no button
 * @return promise
 */
function cms_confirm_linkclick(link, msg, title, yestxt, notxt) {
    cms_confirm(msg, title, yestxt, notxt).done(function() {
        window.location = $(link).attr('href');
    });
}

/**
 * @description display a popup notice (default jquery toast)
 * @function
 * @param (String) type One of 'info','warn', 'error','success'
 * @param (Mixed) msg The message(s) to display, string or strings-array
 * @param (String) title Optional title string
 * @param (Object) props Optional extra or over-riding properties for the message-displayer
 * @return Object whatever is returned by the plugin
 */
function cms_notify(type, msg, title, params) {
    'use strict';
    if(typeof title === 'undefined') title = '';
    if(typeof params === 'undefined') params = {};

    if(typeof cms_data.notifyfunc !== 'function') {
        //TODO maybe some user preference(s) for toast parameters?
        //TODO maybe a sanity-check on type?
        var settings = $.extend({
            text: msg,
            heading: title,
            showHideTransition: 'fade',
            hideAfter: 0,
            loader: false,
            position: 'top-center',
            myclass: type,
            closeicon: ''
        }, params);
        return $.toast(settings);
    }
    return cms_data.notifyfunc(type, msg, title, params);
}

/**
 * @description display all relevant notifications
 * @function cms_notify_all()
 * Uses any/all of cms_data{} properties (if they exist):
 *  infonotices, successnotices, warnnotices, errornotices
 * Their values may each be a string or strings-array
 */
function cms_notify_all() {
    'use strict';
    //stack the popup(s) from info to error
    if (typeof cms_data.infonotices !== 'undefined' && cms_data.infonotices) {
        cms_notify('info', cms_data.infonotices);
    }
    if (typeof cms_data.successnotices !== 'undefined' && cms_data.successnotices) {
        cms_notify('success', cms_data.successnotices);
    }
    if (typeof cms_data.warnnotices !== 'undefined' && cms_data.warnnotices) {
        cms_notify('warn', cms_data.warnnotices);
    }
    if (typeof cms_data.errornotices !== 'undefined' && cms_data.errornotices) {
        cms_notify('error', cms_data.errornotices);
    }
}

/**
 * @description toggle a busy-spinner
 * @function cms_busy(flag)
 * @param (Bool) flag Whether to enable or disable the busy state.
 */
function cms_busy(flag) {
    if(typeof flag === 'undefined') flag = true;
    var $_div = $('#cms_busy');
    if(!$_div.length) {
        // gotta add one.
        var _e = $('<div/>').attr('id', 'cms_busy').addClass('busy').hide();
        $('body').append(_e);
    }
    if(flag) {
        // try to find a busy div
        setTimeout(function() {
            $_div.show();
        }, 10);
    } else if($_div.length) {
        $_div.hide();
    }
}

/**
 * @description set height of specified elements equal to the maximum
 * @function equalHeight(obj)
 * @param {selection-object}
 */
function cms_equalHeight(obj) {
    var max = 0, h;
    obj.each(function() {
        h = $(this).height();
        if(h > max) {
            max = h;
        }
    });
    obj.height(max);
};

/**
 * @description set width of specified elements equal to the maximum
 * @function equalWidth(obj)
 * @param {selection-object}
 */
function cms_equalWidth(obj) {
    var max = 0, w;
    obj.each(function() {
        w = $(this).width();
        if(w > max) {
            max = w;
        }
    });
    obj.width(max);
};
