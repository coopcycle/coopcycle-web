import React from 'react';
import {render} from 'react-dom';
import Cart from './Cart.jsx';


var cart = document.getElementById('cart');
var cartComponent;


// 1. User picked date on the restaurant list page
// 2. User has opened a Cart before
var date = localStorage.getItem('search__date') || window.AppData.Cart.date || '',
    geohash = localStorage.getItem('search_geohash') || '',
    streetAddress = localStorage.getItem('search_address') || '';

if (cart) {

  cartComponent = render(
        <Cart
          streetAddress={streetAddress}
          geohash={geohash}
          i18n={window.__i18n}
          deliveryDate={date}
          availabilities={window.AppData.availabilities}
          items={window.AppData.Cart.items}
          date={date}
          addToCartURL={window.AppData.Cart.addToCartURL}
          removeFromCartURL={window.AppData.Cart.removeFromCartURL}
          validateCartURL={window.AppData.Cart.validateCartURL} />,
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
