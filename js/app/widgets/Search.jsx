import React from 'react'
import { render } from 'react-dom'
import Autocomplete from '../components/Autocomplete.jsx'

window.CoopCycle = window.CoopCycle || {}

window.CoopCycle.Search = function(el, options) {
  render(
    <Autocomplete
      baseURL={ options.url }
      placeholder={ options.placeholder }
      onSuggestionSelected={ options.onSuggestionSelected } />,
    el
  )
}
