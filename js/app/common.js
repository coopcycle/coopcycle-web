// @see http://symfony.com/doc/3.4/frontend/encore/legacy-apps.html
const $ = require('jquery')
global.$ = global.jQuery = $

// polyfill for `startsWith` not implemented in IE11
if (!String.prototype.startsWith) {
  String.prototype.startsWith = function(searchString, position) {
    position = position || 0
    return this.indexOf(searchString, position) === position
  }
}