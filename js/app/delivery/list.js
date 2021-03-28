import React from 'react'
import { render } from 'react-dom'

import Autocomplete from '../components/Autocomplete'
import DatePicker from '../widgets/DatePicker'

['start', 'end'].forEach(name => {
  const inputEl = document.querySelector(`#data_export_${name}`)
  const widgetEl = document.querySelector(`#data_export_${name}_widget`)
  if (inputEl && widgetEl) {
    new DatePicker(widgetEl, {
      onChange: function(date) {
        if (date) {
          inputEl.value = date.format('YYYY-MM-DD');
        }
      }
    })
  }
})

const search = document.getElementById('search-deliveries')

if (search) {
  render(
    <Autocomplete
      baseURL="/search/deliveries?limit=5"
      placeholder="Search deliveries…"
      onSuggestionSelected={ (selected) => {
        window.location.href = window.Routing.generate('admin_delivery', {
          id: selected.id
        })
      }}
      clearOnSelect={ true }
      responseProp="hits"
      renderSuggestion={ suggestion => {

        return (
          <div className="d-flex justify-content-between">
            <h4 className="text-monospace">#{ suggestion.id }</h4>
            <div>
              <span>{ suggestion.pickup.address.streetAddress }</span>
              <br />
              <span>{ suggestion.dropoff.address.streetAddress }</span>
            </div>
          </div>
        )
      } } />
  , search)
}
