import React from 'react'
import { render } from 'react-dom'
import Autocomplete from '../components/Autocomplete.jsx'

export default (el, options) => {
  render(
    <Autocomplete
      baseURL={ options.url }
      placeholder={ options.placeholder }
      onSuggestionSelected={ options.onSuggestionSelected }
      clearOnSelect={ options.clearOnSelect || false } />,
    el
  )
}
