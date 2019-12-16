import React from 'react'
import { render } from 'react-dom'

// @see http://symfony.com/doc/3.4/frontend/encore/legacy-apps.html
const $ = require('jquery')
global.$ = global.jQuery = $

import '../../assets/css/main.scss'

require('bootstrap-sass')

import { setTimezone } from './i18n'
import CartTop from './cart/CartTop'

global.ClipboardJS = require('clipboard')

// polyfill for `startsWith` not implemented in IE11
if (!String.prototype.startsWith) {
  String.prototype.startsWith = function(searchString, position) {
    position = position || 0
    return this.indexOf(searchString, position) === position
  }
}

// @see https://developer.mozilla.org/fr/docs/Web/API/Element/closest#Polyfill
if (!Element.prototype.matches)
  Element.prototype.matches = Element.prototype.msMatchesSelector ||
                              Element.prototype.webkitMatchesSelector

if (!Element.prototype.closest)
  Element.prototype.closest = function(s) {
    var el = this
    if (!document.documentElement.contains(el)) return null
    do {
      if (el.matches(s)) return el
      el = el.parentElement || el.parentNode
    } while (el !== null && el.nodeType == 1)

    return null
  }

// additional method to format currencies
Number.prototype.formatMoney = function(places, symbol, thousand, decimal) {
  places = !isNaN(places = Math.abs(places)) ? places : 2
  symbol = symbol !== undefined ? symbol : '€'
  thousand = thousand || '.'
  decimal = decimal || ','
  var number = this,
    negative = number < 0 ? '-' : '',
    i = parseInt(number = Math.abs(+number || 0).toFixed(places), 10) + '',
    j = (j = i.length) > 3 ? j % 3 : 0
  return negative + (j ? i.substr(0, j) + thousand : '') + i.substr(j).replace(/(\d{3})(?=\d)/g, '$1' + thousand) + (places ? decimal + Math.abs(number - i).toFixed(places).slice(2) : '') + symbol
}

window.CoopCycle = window.CoopCycle || {}
window.CoopCycle.setTimezone = setTimezone

/* Top cart */
document.addEventListener('DOMContentLoaded', function() {

  const cartTopElement = document.querySelector('#cart-top')
  const cartDataElement = document.querySelector('#js-cart-data')

  if (cartTopElement && cartDataElement) {

    const { restaurant, itemsTotal, total } = cartDataElement.dataset

    render(
      <CartTop
        restaurant={ restaurant ? JSON.parse(restaurant) : null }
        total={ total }
        itemsTotal={ itemsTotal }
      />, cartTopElement)
  }

})
