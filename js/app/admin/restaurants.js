import React from 'react'
import { createRoot } from 'react-dom/client'

import Autocomplete from '../components/Autocomplete'

$('#form_enable_restaurant_pledges').on('change', function(e) {
  $(e.target).closest('form').submit();
});

const search = document.getElementById('search-restaurants')

if (search) {

  createRoot(search).render(
    <Autocomplete
      baseURL="/admin/restaurants/search?format=json"
      placeholder="Search restaurants…"
      onSuggestionSelected={ (selected) => {
        window.location.href = window.Routing.generate('admin_restaurant', {
          id: selected.id
        })
      }}
      clearOnSelect={ true } />
  )
}
