import React from 'react';
import {render} from 'react-dom';
import Cart from '../Cart.jsx';
import moment from 'moment';

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
      validateCartURL={window.AppData.Cart.validateCartURL} />, cart);

  $('.js-add-to-cart').on('click', function(e) {
    e.preventDefault();
    var $target = $(e.currentTarget);
    cartComponent.addMenuItemById($target.data('menu-item-id'));
  });
}

function onDatePickerChange(e) {
  cartComponent.onDateChange(dateToString());
}

$('#cart-date').on('change', onDatePickerChange);
$('#cart-time').on('change', onDatePickerChange);
