import select2Factory from 'select2/dist/js/select2.full.js';

/*
 * The Select2 full build is a CommonJS-style wrapper: it exports a factory that must be called with
 * the jQuery instance. Calling it here registers the plugin before the language files load.
 */
select2Factory(window, window.jQuery);
