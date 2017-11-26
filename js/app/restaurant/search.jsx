import React from 'react'
import { render } from 'react-dom'
import Autocomplete from './Autocomplete.jsx'

window.CoopCycle = window.CoopCycle || {}

window.CoopCycle.RestaurantSearch = function(el, options) {
  render(
    <Autocomplete
      baseURL={ options.url }
      placeholder={ options.placeholder }
      onRestaurantSelected={ options.onRestaurantSelected } />,
    el
  )
}
