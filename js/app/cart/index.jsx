import React from 'react';
import {render} from 'react-dom';
import Cart from './Cart.jsx';
import CartTop from './CartTop.jsx'
import moment from 'moment';

let isXsDevice = $('.visible-xs').is(':visible')

var topCart = document.getElementById('cart-top');
var topCartComponent;

var cart = document.getElementById('cart');
var cartComponent;

let restaurantChangeWarning = false

// It means we are on a restaurant page
if (window.AppData.Restaurant && window.AppData.Cart.restaurant) {
  if (window.AppData.Restaurant.id !== window.AppData.Cart.restaurant.id) {
    restaurantChangeWarning = true
  }
}

if (topCart) {

  topCartComponent = render(
    <CartTop
      restaurantURL={window.AppData.Cart.restaurantURL}
      restaurant={window.AppData.Cart.restaurant}
      total={window.AppData.Cart.total}
      i18n={window.__i18n} />,
    topCart
  );

}

function addItemToBasket(event) {
  event.preventDefault();
  var $target = $(event.currentTarget),
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
}


if (cart) {

// 1. User picked date on the restaurant list page
// 2. User has opened a Cart before
  var initialDate = localStorage.getItem('search__date') || window.AppData.Cart.date || '',
      availabilities = window.AppData.availabilities,
      // TODO : check with someone knowledgeable in React if it is the right place to do this
      initialDate = moment(initialDate).isAfter(moment(availabilities[0])) ? initialDate : availabilities[0],
      geohash = localStorage.getItem('search_geohash') || '',
      streetAddress = localStorage.getItem('search_address') || '';

  cartComponent = render(
        <Cart
          streetAddress={streetAddress}
          geohash={geohash}
          i18n={window.__i18n}
          deliveryDate={initialDate}
          availabilities={availabilities}
          items={window.AppData.Cart.items}
          restaurant={window.AppData.Restaurant}
          addToCartURL={window.AppData.Cart.addToCartURL}
          removeFromCartURL={window.AppData.Cart.removeFromCartURL}
          validateCartURL={window.AppData.Cart.validateCartURL}
          isMobileCart={ isXsDevice }
          onCartChange={total => topCartComponent.setTotal(total)}
        />,
    cart);

  $('.js-add-to-cart').on('click', function(e) {
      if (restaurantChangeWarning) {
        $('#cart-warning-modal').modal('show');
        $('#cart-warning-primary').on('click', function(ev) {
            restaurantChangeWarning = false;
            topCartComponent.setRestaurant(window.AppData.Restaurant);
            $('#cart-warning-modal').modal('hide');
            addItemToBasket(e);
        });
      } else {
        addItemToBasket(e);
      }
    });

  // Small helper for better display (remove when changing the modifier modal by a React Component)
  $('.modifier-modal .modifier-item').on('click', function () {
      $(this).closest('.modifier-modal').find('.modifier-item').removeClass('modifier-item__selected') ;
      $(this).addClass('modifier-item__selected');
  });

}
