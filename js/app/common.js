import numbro from 'numbro'

// @see http://symfony.com/doc/3.4/frontend/encore/legacy-apps.html
const $ = require('jquery')
global.$ = global.jQuery = $

import '../../assets/css/main.scss'

require('bootstrap-sass')

import './i18n'
import { setTimezone, getCurrencySymbol } from './i18n'
import AddressAutosuggest from './widgets/AddressAutosuggest'

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

Number.prototype.formatMoney = function() {

  return numbro(this).format({
    ...numbro.languageData().formats.fullWithTwoDecimals,
    currencySymbol: getCurrencySymbol(),
  })
}

// Initialize Matomo
window._paq = [];

/* Top cart */
document.addEventListener('DOMContentLoaded', function() {

  // Set global timezone used in Moment.js
  const timezone = document.querySelector('body').dataset.timezone
  setTimezone(timezone)

  const inputs = document.querySelectorAll('[data-widget="address-input"]')
  if (inputs.length > 0) {

    const addressElements = {
      latitude: '$1ddress_latitude',
      longitude: '$1ddress_longitude',
      postalCode: '$1ddress_postalCode',
      addressLocality: '$1ddress_addressLocality',
    }

    inputs.forEach(el => {

      // Try to build an address object
      let address = {
        streetAddress: el.value
      }
      for (const addressProp in addressElements) {
        const addressEl = document.getElementById(
          el.getAttribute('id').replace(/([aA])ddress_streetAddress/, addressElements[addressProp])
        )
        if (addressEl) {
          address = {
            ...address,
            [addressProp]: addressEl.value
          }
        }
      }

      address = {
        ...address,
        geo: {
          latitude: address.latitude,
          longitude: address.longitude,
        }
      }

      new AddressAutosuggest(
        el.closest('.form-group'),
        {
          required: el.required,
          address,
          inputProps: {
            id: el.getAttribute('id'),
            name: el.getAttribute('name'),
          },
          onAddressSelected: (text, address) => {
            for (const addressProp in addressElements) {
              const addressEl = document.getElementById(
                el.getAttribute('id').replace(/([aA])ddress_streetAddress/, addressElements[addressProp])
              )
              if (addressEl) {
                addressEl.value = address[addressProp]
              }
            }
          },
          disabled: el.disabled,
        }
      )
    })
  }

})
