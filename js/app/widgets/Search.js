import React from 'react'
import { render } from 'react-dom'
import Autocomplete from '../components/Autocomplete'

export default function(el, options) {
  render(
    <Autocomplete
      baseURL={ options.url }
      placeholder={ options.placeholder }
      onSuggestionSelected={ options.onSuggestionSelected }
      clearOnSelect={ options.clearOnSelect || false }
      searchOnEnter={ options.searchOnEnter || false }
      initialValue={ options.initialValue || '' } />,
    el
  )
}
