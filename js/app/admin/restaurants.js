import React from 'react'
import { render } from 'react-dom'
import i18n from '../i18n'

import Autocomplete from '../components/Autocomplete'

$('#form_enable_restaurant_pledges').on('change', function(e) {
  $(e.target).closest('form').submit();
});

const search = document.getElementById('search-restaurants')

if (search) {

  render(
    <Autocomplete
      baseURL="/admin/restaurants/search?format=json"
      placeholder="Search restaurants…"
      onSuggestionSelected={ (selected) => {
        window.location.href = window.Routing.generate('admin_restaurant', {
          id: selected.id
        })
      }}
      clearOnSelect={ true } />
  , search)
}

document.querySelectorAll('.delete-restaurant').forEach((el) => {
    el.addEventListener('click', (e) => {

    if (!window.confirm(i18n.t('CONFIRM_DELETE_WITH_PLACEHOLDER', { object_name: e.target.dataset.restaurantName }))) {
      e.preventDefault()
      return
    }

    const jwt = document.head.querySelector('meta[name="application-auth-jwt"]').content
    const headers = {
      'Authorization': `Bearer ${jwt}`,
      'Accept': 'application/ld+json',
      'Content-Type': 'application/ld+json'
    }

    const url = window.Routing.generate('api_restaurants_delete_item', {
      id: e.target.dataset.restaurantId,
    })

    fetch(url, {method: "DELETE", headers: headers}).then(
      function () { location.reload(); }
    );

  });
})
