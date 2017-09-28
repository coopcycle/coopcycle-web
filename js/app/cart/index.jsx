import React from 'react';
import {render} from 'react-dom';
import Cart from '../Cart.jsx';

function dateToString() {
  return $('#cart-date').val() + ' ' + $('#cart-time').val() + ':00';
}

var cart = document.getElementById('cart');
var cartComponent;

var date = window.AppData.Cart.date || dateToString();

if (cart) {

  cartComponent = render(
    <Cart
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

function onDatePickerChange(e) {
  cartComponent.onDateChange(dateToString());
}

$('#cart-date').on('change', onDatePickerChange);
$('#cart-time').on('change', onDatePickerChange);

cartComponent.onDateChange(dateToString());
