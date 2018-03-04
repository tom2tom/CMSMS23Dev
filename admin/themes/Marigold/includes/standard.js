/*!
CMSMS theme functions
(C) Goran Ilic <ja@ich-mach-das.at>
*/
/**
 * CMSMS theme functions
 * Originally developed for OneEleven theme
 * @package CMS Made Simple
 * @module NS
 * @author Goran Ilic - uniqu3 <ja@ich-mach-das.at>
 * NOTE includes a hardcoded url to the cookie processor, and viewport width
 */

(function(global, $) {
    "$:nomunge";
    'use strict';
    /*jslint nomen: true , devel: true*/
    /**
     * @namespace NS
     */
    var NS = global.NS = {};
    $(document).ready(function() {
        NS.helper.init();
        NS.view.init();
    });
    /**
     * @namespace NS.helper
     */
    NS.helper = {
        init: function() {
            var _this = this;
            // open external links with rel="external" attribute in new window
            $('a[rel=external]').attr('target', '_blank');
            // focus on input with .defaultfocus class
            $('input.defaultfocus:eq(0), input[autofocus]').focus();
            // async-load a cookie handler if localStorage is not supported
            if(!_this._isLocalStorage()) {
                _this.loadScript('themes/Marigold/includes/js-cookie.min.js'); //TODO url not hardcoded
            }
        },
        /**
         * @description conditional load script helper function
         * @author Brad Vincent https://gist.github.com/2313262
         * @memberof NS.helper
         * @function loadScript(url, arg1, arg2)
         * @param {string} url
         * @callback requestCallback
         * @param {requestCallback|boolean} arg1
         * @param {requestCallback|boolean} arg2
         */
        loadScript: function(url, arg1, arg2) {
            var cache = true,
                callback = null,
                load = true;
            //arg1 and arg2 can be interchangable
            if($.isFunction(arg1)) {
                callback = arg1;
                cache = arg2 || cache;
            } else {
                cache = arg1 || cache;
                callback = arg2 || callback;
            }
            //check all existing script tags in the page for the url
            $('script[type="text/javascript"]').each(function() {
                var load = (url !== $(this).attr('src'));
                return load;
            });
            if(load) {
                //didn't find it in the page, so load it
                $.ajax({
                    type: 'GET',
                    url: url,
                    async: false,
                    success: callback,
                    dataType: 'script',
                    cache: cache
                });
            } else {
                //already loaded so just call the callback
                if($.isFunction(callback)) {
                    callback.call(this);
                }
            }
        },
        /**
         * @description saves a defined key and value to localStorage if localStorgae is supported, else falls back to cookie script
         * @requires js-cookie https://github.com/js-cookie/js-cookie
         * @memberof NS.helper
         * @function setStorageValue(key, value)
         * @param {string} key
         * @param {string} value
         * @param {number} expires (number in days)
         */
        setStorageValue: function(key, value, expires) {
            var _this = this;
            try {
                if(_this._isLocalStorage() === true) {
                    localStorage.removeItem(key);
                    var obj;
                    if(expires !== null) {
                        var expiration = new Date().getTime() + (expires * 24 * 60 * 60 * 1000);
                        obj = {
                            value: value,
                            timestamp: expiration
                        };
                    } else {
                        obj = {
                            value: value,
                            timestamp: ''
                        };
                    }
                    localStorage.setItem(key, JSON.stringify(obj));
                } else if(_this._isCookieScript() === true) {
                    if(expires !== null) {
                        Cookies.set(key, value, {
                            expires: expires
                        });
                    } else {
                        Cookies.set(key, value);
                    }
                } else {
                    throw "No cookie storage!";
                }
            } catch(error) {
                console.log('localStorage Error: set(' + key + ', ' + value + ')');
                console.log(error);
            }
        },
        /**
         * @description gets value for defined key from localStorage if localStorgae is supported, else falls back to js-cookie script
         * @requires js-cookie https://github.com/js-cookie/js-cookie
         * @memberof NS.helper
         * @function getStorageValue(key)
         * @param {string} key
         */
        getStorageValue: function(key) {
            var _this = this,
                data, value;
            if(_this._isLocalStorage()) {
                data = JSON.parse(localStorage.getItem(key));
                if(data !== null && data.timestamp < new Date().getTime()) {
                    _this.removeStorageValue(key);
                } else if(data !== null) {
                    value = data.value;
                }
            } else if(_this._isCookieScript() === true) {
                value = Cookies(key);
            } else {
                value = ''; //TODO handle no cookie
            }
            return value;
        },
        /**
         * @description removes defined key from localStorage if localStorage is supported, else falls back to js-cookie script
         * @requires js-cookie https://github.com/js-cookie/js-cookie
         * @memberof NS.helper
         * @function removeStorageValue(key)
         * @param {string} key
         */
        removeStorageValue: function(key) {
            var _this = this;
            if(_this._isLocalStorage() === true) {
                localStorage.removeItem(key);
            } else if(_this._isCookieScript() === true) {
                Cookies.remove(key);
            }
        },
        /**
         * @description Sets equal height on specified element group
         * @memberof NS.helper
         * @function equalHeight(obj)
         * @param {object}
         */
        equalHeight: function(obj) {
            var tallest = 0;
            obj.each(function() {
                var elHeight = $(this).height();
                if(elHeight > tallest) {
                    tallest = elHeight;
                }
            });
            obj.height(tallest);
        },
        /**
         * @description detects if localStorage is supported by browser
         * @function _isLocalStorage()
         * @private
         */
        _isLocalStorage: function() {
            return typeof(Storage) !== 'undefined';
        },
        /**
         * @description detects if js-cookie.js is present
         * @function _isCookieScript()
         * @private
         */
        _isCookieScript: function() {
            return typeof(Cookies) !== 'undefined';
        },
        /**
         * @description Basic check for common mobile devices and touch capability
         * @function _isMobileDevice()
         * @private
         */
        _isMobileDevice: function() {
            var ua = navigator.userAgent.toLowerCase(),
                devices = /(Android|iPhone|iPad|iPod|Blackberry|Dolphin|IEMobile|WPhone|Windows Mobile|IEMobile9||IEMobile10||IEMobile11|Kindle|Mobile|MMP|MIDP|Pocket|PSP|Symbian|Smartphone|Sreo|Up.Browser|Up.Link|Vodafone|WAP|Opera Mini|Opera Tablet|Mobile|Fennec)/i;
            if(ua.match(devices) && (('ontouchstart' in window) || (navigator.msMaxTouchPoints > 0) || window.DocumentTouch && document instanceof DocumentTouch)) {
                return true;
            }
        }
    };
    /**
     * @namespace NS.view
     */
    NS.view = {
        init: function() {
            var _this = this,
                $sidebar_toggle = $('.toggle-button'), // object for sidebar toggle
                $container = $('#oe_container'), // page container
                $menu = $('#oe_pagemenu'); // page menu
            // handle navigation sidebar toggling
            $sidebar_toggle.on('click', function(e) {
                e.preventDefault();
                if($container.hasClass('sidebar-on')) {
                    _this._closeSidebar($container, $menu);
                } else {
                    _this._showSidebar($container, $menu);
                }
            });
            // toggle hide/reveal menu children
            _this.toggleSubMenu($menu, 50);
            // handle notifications
            _this.showNotifications();
            // substitute buttons for inputs, etc
            _this.migrateUIElements();
            // setup alert handlers
            _this.setupAlerts();
            // handle updating the display.
            _this.updateDisplay();
            // handles the initial state of the sidebar (collapsed or expanded)
            _this.handleSidebar($container);
            $(window).resize(function() {
                _this.handleSidebar($container);
                _this.updateDisplay();
            });
        },
        /**
         * @description Checks for saved state of sidebar
         * @function handleSidebar(trigger, container)
         * @param {object} trigger
         * @param {object} container
         * @memberof NS.view
         */
        handleSidebar: function(container) {
            var viewportWidth = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;
            if(NS.helper.getStorageValue('sidebar-pref') === 'sidebar-off' || viewportWidth <= 992) {
                container.addClass('sidebar-off').removeClass('sidebar-on');
            } else {
                container.addClass('sidebar-on').removeClass('sidebar-off');
            }
        },
        /**
         * @description Handles toggling of main menu child items
         * @function toggleSubMenu(obj)
         * @param {object} obj - Menu container object
         * @param {number} duration - A positive number for toggle speed control
         * @memberof NS.view
         */
        toggleSubMenu: function(obj, duration) {
            var _this = this;
            obj.find('li.current span').addClass('open-sub');
            obj.find('> li > span').click(function() {
                var ul = $(this).next();
                var _p = [];
                if(ul.is(':visible') === false) {
                    _p.push(obj.find('ul').slideUp(duration));
                }
                _p.push(ul.slideToggle(duration));
                $.when.apply($, _p).done(function() {
                    _this.updateDisplay();
                });
            });
        },
        /**
         * @description Handles core and module messages
         * @function showNotifications()
         */
        showNotifications: function() {
            //TODO some user preference(s) for toast parameters?
            if (typeof cms_data.toastinfos !== 'undefined') {
                $.toast({
                    text: cms_data.toastinfos,
                    showHideTransition: 'fade',
                    hideAfter: 0,
                    loader: false,
                    position: 'top-center',
                    myclass: 'info',
                    closeicon: ''
                });
            }
            if (typeof cms_data.toastgoods !== 'undefined') {
                $.toast({
                    text: cms_data.toastgoods,
                    showHideTransition: 'fade',
                    hideAfter: 0,
                    loader: false,
                    position: 'top-center',
                    myclass: 'success',
                    closeicon: ''
                });
            }
            if (typeof cms_data.toastwarns !== 'undefined') {
                $.toast({
                    text: cms_data.toastwarns,
                    showHideTransition: 'fade',
                    hideAfter: 0,
                    loader: false,
                    position: 'top-center',
                    myclass: 'warn',
                    closeicon: ''
                });
            }
            if (typeof cms_data.toasterrs !== 'undefined') {
                $.toast({
                    text: cms_data.toasterrs,
                    showHideTransition: 'fade',
                    hideAfter: 0,
                    loader: false,
                    position: 'top-center',
                    myclass: 'error',
                    closeicon: ''
                });
            }

			//the rest of this stuff is probably redundant - toasts are created in-place
            $('.pagewarning, .message, .pageerrorcontainer, .pagemcontainer').prepend('<span class="close-warning" title="' + cms_data.lang_gotit + '"></span>');
            $(document).on('click', '.close-warning', function() {
                $(this).parent().hide();
                $(this).parent().remove();
            });
            // pagewarning status hidden?
            var key = $('body').attr('id') + '_notification';
            $('.pagewarning .close-warning').click(function() {
                NS.helper.setStorageValue(key, 'hidden', 60);
            });
            if(NS.helper.getStorageValue(key) === 'hidden') {
                $('.pagewarning').addClass('hidden');
            }
            $(document).on('cms_ajax_apply', function(e) {
                $('button[name=cancel], button[name=m1_cancel]').fadeOut();
                $('button[name=cancel], button[name=m1_cancel]').button('option', 'label', e.close);
                $('button[name=cancel], button[name=m1_cancel]').fadeIn();
                var htmlShow = '';
                if(e.response === 'Success') {
                    htmlShow = '<aside class="messagecontainer" role="status"><span class="close-warning">' + cms_data.lang_close + '</span><p>' + e.details + '</p></aside>';
                } else {
                    htmlShow = '<aside class="pageerror" role="alert"><span class="close-warning">' + cms_data.lang_close + '</span><ul>';
                    htmlShow += e.details;
                    htmlShow += '</ul></aside>';
                }
                $('body').append(htmlShow).slideDown(1000, function() {
                    window.setTimeout(function() {
                        $('.message').slideUp();
                        $('.message').remove();
                    }, 10000);
                });
                $(document).on('click', '.close-warning', function() {
                    $('.message').slideUp();
                    $('.message').remove();
                });
            });
        },
        /**
         * @description Substitutes styled buttons for input-submits. And some links
         * @function migrateUIElements()
         */
        migrateUIElements: function() {
            // Standard input buttons
            $('input[type="submit"], :button[data-ui-icon]').each(function() {
                var button = $(this);
                if(!(button.hasClass('noautobtn') || button.hasClass('no-ui-btn'))) {
                    var xclass, label, $btn;
                    if(button.is('[name*=submit]')) {
                        xclass = 'iconcheck';
                    } else if(button.is('[name*=apply]')) {
                        xclass = 'iconapply';
                    } else if(button.is('[name*=cancel]') || button.is('[name*=close]')) {
                        xclass = 'iconclose';
                    } else if(button.is('[name*=reset]') || button.attr('id') === 'refresh') {
                        xclass = 'iconundo';
                    } else {
                        xclass = '';
                    }
                    //ETC
                    if(button.is('input')) {
                        label = button.val();
                    } else {
                        label = button.text();
                    }
                    $btn = $('<button type="submit" class="adminsubmit ' + xclass + '">' + label + '</button>');
                    $(this.attributes).each(function(idx, attrib) {
                        switch (attrib.name) {
                          case 'type':
                            break;
                          case 'class':
                            var oc = attrib.value.replace(/(^|\s*)ui-\S+/g,'');
                            if (oc !== '') {
                                $btn.attr('class', 'adminsubmit ' + xclass + ' ' + oc);
                            }
                            break;
                          default:
                            $btn.attr(attrib.name, attrib.value);
                            break;
                        }
                    });
                    button.replaceWith($btn);
                }
            });
            // Back links
            $('a.pageback').addClass('link_button iconback');
        },
        /**
         * @description Placeholder function for functions that need to be triggered on window resize
         * @memberof NS.view
         * @function updateDisplay()
         */
        updateDisplay: function() {
            var $menu = $('#oe_menu');
            var $alert_box = $('#admin-alerts');
            var $header = $('header.header');
            var offset = $header.outerHeight() + $header.offset().top;
            if($alert_box.length) offset = $alert_box.outerHeight() + $alert_box.offset().top;
            console.debug('menu height = ' + $menu.outerHeight() + ' offset = ' + offset);
            console.debug('window height = ' + $(window).height());
            if($menu.outerHeight() + offset < $(window).height()) {
                console.debug('fixed');
                $menu.css({ 'position': 'fixed', 'top': offset });
            } else {
                $menu.css({ 'position': '', 'top': '' });
                console.debug('floating');
                if($menu.offset().top < $(window).scrollTop()) {
                    //if the top of the menu is not visible, scroll to it.
                    $('html, body').animate({
                        scrollTop: $("#oe_menu").offset().top
                    }, 1000);
                }
            }
        },
        /**
         * @description Handles setting for Sidebar and sets open state
         * @private
         * @function _showSidebar(obj, target)
         * @params {object} obj
         * @params {object} target
         */
        _showSidebar: function(obj, target) {
            obj.addClass('sidebar-on').removeClass('sidebar-off');
            target.find('li.current ul').show();
            NS.helper.setStorageValue('sidebar-pref', 'sidebar-on', 60);
        },
        /**
         * @description Handles setting for Sidebar and sets closed state
         * @private
         * @function _closeSidebar(obj, target)
         * @params {object} obj
         * @params {object} target
         */
        _closeSidebar: function(obj, target) {
            obj.removeClass('sidebar-on').addClass('sidebar-off');
            target.find('li ul').hide();
            NS.helper.setStorageValue('sidebar-pref', 'sidebar-off', 60);
        },
        _handleAlert: function(target) {
            var _row = $(target).closest('.alert-box');
            var _alert_name = _row.data('alert-name');
            if(!_alert_name) return;
            return $.ajax({
                method: 'POST',
                url: cms_data.ajax_alerts_url, //TODO what is cms_data ?
                data: {
                    op: 'delete',
                    alert: _alert_name
                }
            }).done(function() {
                _row.slideUp(1000);
                var _parent = _row.parent();
                if(_parent.children().length <= 1) {
                    _row.closest('div.ui-dialog-content').dialog('close');
                    $('#alert-noalerts').show();
                    $('a#alerts').closest('li').remove();
                }
                _row.remove();
            }).fail(function(xhr, status, msg) {
                console.debug('problem deleting an alert: ' + msg);
            });
        },
        /**
         * @description Handles popping up the notification area
         * @private
         * @function setupAlerts()
         */
        setupAlerts: function() {
            var _this = this;
            $('a#alerts').click(function(e) {
                e.preventDefault();
                $('#alert-dialog').dialog();
            });
            $('.alert-msg a').click(function(e) {
                e.preventDefault();
                NS.view.handleAlert(e.target);
            });
            $('.alert-icon,.alert-remove').click(function(e) {
                e.preventDefault();
                _this._handleAlert(e.target);
            });
        },
    };
})(this, jQuery);
