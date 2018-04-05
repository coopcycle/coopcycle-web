import React from 'react'
import { render } from 'react-dom'
import Switch from 'antd/lib/switch'

var $restaurants = $('#update_profile_restaurants');
var $stores = $('#update_profile_stores');

var restaurantSearch = document.querySelector('#restaurant-search');
var storeSearch = document.querySelector('#store-search');

if ($restaurants.length === 1 && restaurantSearch) {

  var options = {
    url: window.AppData.restaurantsSearchUrl,
    placeholder: window.AppData.__i18n.restaurantsSearch,
    onSuggestionSelected: function(restaurant) {
      var newRestaurant = $restaurants.attr('data-prototype');
      newRestaurant = newRestaurant.replace(/__name__/g, $restaurants.find('tbody > tr').length);
      newRestaurant = newRestaurant.replace(/__value__/g, restaurant.id);
      newRestaurant = newRestaurant.replace(/__restaurant_name__/g, restaurant.name);
      $restaurants.find('tbody').append($(newRestaurant));
    }
  }

  new CoopCycle.Search(restaurantSearch, options);

  $(document).on('click', '.remove-restaurant', function(e) {
    e.preventDefault();
    $(this).closest('tr').remove();
  });

}

if ($stores.length === 1 && storeSearch) {

  var options = {
    url: window.AppData.storesSearchUrl,
    placeholder: window.AppData.__i18n.storesSearch,
    onSuggestionSelected: function(store) {
      var newStore = $stores.attr('data-prototype');
      newStore = newStore.replace(/__name__/g, $stores.find('tbody > tr').length);
      newStore = newStore.replace(/__value__/g, store.id);
      newStore = newStore.replace(/__store_name__/g, store.name);
      $stores.find('tbody').append($(newStore));
    }
  }

  new CoopCycle.Search(storeSearch, options);

  $(document).on('click', '.remove-store', function(e) {
    e.preventDefault();
    $(this).closest('tr').remove();
  });

}


function renderSwitch($input) {

  const $parent = $input.closest('div.checkbox').parent()

  const $switch = $('<div class="display-inline-block">')
  const $hidden = $('<input>')

  $switch.addClass('switch')

  $hidden
    .attr('type', 'hidden')
    .attr('name', $input.attr('name'))
    .attr('value', $input.attr('value'))

  $parent.prepend($switch)
  $parent.prepend($hidden)

  const checked = $input.is(':checked')

  $input.closest('div.checkbox').remove()

  render(
    <Switch
      defaultChecked={ checked }
      checkedChildren={ window.AppData.__i18n['Enabled'] }
      unCheckedChildren={ window.AppData.__i18n['Disabled'] }
      onChange={(checked) => {
        if (checked) {
          $parent.append($hidden)
        } else {
          $hidden.remove()
        }
      }}
    />,
    $switch.get(0)
  );

}

$(function() {
  // Render Switch on page load
  $('form[name="update_profile"]').find('.switch').each((index, el) => renderSwitch($(el)))
})