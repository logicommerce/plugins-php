/**
 * DCS Custom CSS Loader
 * 
 * Loads widget template CSS dynamically via POST request
 * and injects it into the page
 */
(function(window, document) {
    'use strict';

    /**
     * DCS CSS Loader
     */
    var DcsCustomCssLoader = {
        
        /**
         * Configuration
         */
        config: {
            apiUrl: null,
            dcsToken: null,
            dcsPageId: null,
            functionType: null,
            styleElementId: 'dcs-custom-css',
            debug: false
        },

        /**
         * Initialize the loader
         * 
         * @param {Object} options Configuration options
         */
        init: function(options) {
            // Merge options with config
            this.config = this._extend(this.config, options || {});

            // Validate required parameters
            if (!this.config.apiUrl) {
                this._error('API URL is required');
                return;
            }

            if (!this.config.dcsToken) {
                this._warn('dcsToken is missing, skipping CSS load');
                return;
            }

            if (!this.config.dcsPageId) {
                this._warn('dcsPageId is missing, skipping CSS load');
                return;
            }

            if (!this.config.functionType) {
                this._error('functionType is required');
                return;
            }

            // Create style element if it doesn't exist
            this._createStyleElement();

            // Load CSS
            this.loadCss();
        },

        /**
         * Load CSS via POST request
         */
        loadCss: function() {
            var self = this;

            // Prepare POST data
            var formData = new FormData();
            formData.append('type', this.config.functionType);
            formData.append('dcsToken', this.config.dcsToken);
            formData.append('dcsPageId', this.config.dcsPageId);

            this._log('Loading custom CSS...', {
                apiUrl: this.config.apiUrl,
                pageId: this.config.dcsPageId
            });

            // Make POST request
            fetch(this.config.apiUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.text();
            })
            .then(function(css) {
                self._injectCss(css);
                self._log('Custom CSS loaded successfully', {
                    length: css.length
                });
            })
            .catch(function(error) {
                self._error('Failed to load custom CSS:', error);
                self._injectFallbackCss();
            });
        },

        /**
         * Create style element in DOM
         * @private
         */
        _createStyleElement: function() {
            var existing = document.getElementById(this.config.styleElementId);
            if (!existing) {
                var style = document.createElement('style');
                style.id = this.config.styleElementId;
                style.textContent = '/* DCS Custom CSS loading... */';
                
                // Insert at the beginning of <head> for lower priority
                var head = document.head || document.getElementsByTagName('head')[0];
                if (head.firstChild) {
                    head.insertBefore(style, head.firstChild);
                } else {
                    head.appendChild(style);
                }
            }
        },

        /**
         * Inject CSS into the page
         * @private
         */
        _injectCss: function(css) {
            var styleElement = document.getElementById(this.config.styleElementId);
            if (styleElement) {
                styleElement.textContent = css;
            } else {
                this._error('Style element not found: ' + this.config.styleElementId);
            }
        },

        /**
         * Inject fallback CSS on error
         * @private
         */
        _injectFallbackCss: function() {
            var fallbackCss = '/* DCS Custom CSS: Failed to load */\n' +
                '.commerce-content {\n' +
                '    outline: 2px dashed #e6007a;\n' +
                '    outline-offset: -2px;\n' +
                '}\n';
            this._injectCss(fallbackCss);
        },

        /**
         * Extend object (simple polyfill for Object.assign)
         * @private
         */
        _extend: function(target, source) {
            for (var key in source) {
                if (source.hasOwnProperty(key)) {
                    target[key] = source[key];
                }
            }
            return target;
        },

        /**
         * Log message (if debug enabled)
         * @private
         */
        _log: function(message, data) {
            if (this.config.debug && console && console.log) {
                console.log('[DCS CSS Loader] ' + message, data || '');
            }
        },

        /**
         * Log warning
         * @private
         */
        _warn: function(message) {
            if (console && console.warn) {
                console.warn('[DCS CSS Loader] ' + message);
            }
        },

        /**
         * Log error
         * @private
         */
        _error: function(message, error) {
            if (console && console.error) {
                console.error('[DCS CSS Loader] ' + message, error || '');
            }
        }
    };

    // Expose to global scope
    window.DcsCustomCssLoader = DcsCustomCssLoader;

})(window, document);
