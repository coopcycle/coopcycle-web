import React from 'react';
import {render} from 'react-dom';
import Cart from './Cart.jsx';
import moment from 'moment';


let isXsDevice = $('.visible-xs').is(':visible')

var cart = document.getElementById('cart');
var cartComponent;

// 1. User picked date on the restaurant list page
// 2. User has opened a Cart before
var initialDate = localStorage.getItem('search__date') || window.AppData.Cart.date || '',
    availabilities = window.AppData.availabilities,
    // TODO : check with someone knowledgeable in React if it is the right place to do this
    initialDate = moment(initialDate).isAfter(moment(availabilities[0])) ? initialDate : availabilities[0],
    geohash = localStorage.getItem('search_geohash') || '',
    streetAddress = localStorage.getItem('search_address') || '';

if (cart) {

  cartComponent = render(
        <Cart
          streetAddress={streetAddress}
          geohash={geohash}
          i18n={window.__i18n}
          deliveryDate={initialDate}
          availabilities={availabilities}
          items={window.AppData.Cart.items}
          addToCartURL={window.AppData.Cart.addToCartURL}
          removeFromCartURL={window.AppData.Cart.removeFromCartURL}
          validateCartURL={window.AppData.Cart.validateCartURL}
          minimumCartAmount={window.AppData.Cart.minimumCartAmount}
          flatDeliveryPrice={window.AppData.Cart.flatDeliveryPrice}
          isMobileCart={ isXsDevice }
        />,
    cart);

  $('.js-add-to-cart').on('click', function(e) {
    e.preventDefault();
    var $target = $(e.currentTarget),
        menuItemId = $target.data('menu-item-id'),
        modifiersModal = $('#' + menuItemId + '-modifiersModal'),
        modifiers = {};

    // handle modifiers
    if (modifiersModal.length > 0) {
      modifiersModal.find('form').each(function () {
        var modifierId = $(this).data('modifierId'),
            modifierChoices = [],
            selectedChoices= $(this).find('input:checked');

        selectedChoices.each(function () {
          modifierChoices.push($(this).val());
        });

        modifiers[modifierId] = modifierChoices;
      });

      $(modifiersModal).modal('hide');
    }

    cartComponent.addMenuItemById(menuItemId, modifiers);
  });

  // Small helper for better display (remove when changing the modifier modal by a React Component)
  $('.modifier-modal .modifier-item').on('click', function () {
      $(this).closest('.modifier-modal').find('.modifier-item').removeClass('modifier-item__selected') ;
      $(this).addClass('modifier-item__selected');
  });

}
