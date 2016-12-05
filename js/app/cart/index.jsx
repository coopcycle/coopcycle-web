import React from 'react';
import {render} from 'react-dom';
import Cart from '../Cart.jsx';

var cart = document.getElementById('cart');
if (cart) {
  var cartComponent = render(
    <Cart
        items={window.AppData.Cart.items}
        addToCartURL={window.AppData.Cart.addToCartURL}
        removeFromCartURL={window.AppData.Cart.removeFromCartURL} />,
    cart
  );
  $('.js-add-to-cart').on('click', function(e) {
    e.preventDefault();
    var $target = $(e.currentTarget);
    cartComponent.addProductById($target.data('product-id'));
  });
}
