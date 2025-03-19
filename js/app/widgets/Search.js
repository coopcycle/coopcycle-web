import React from 'react'
import { createRoot } from 'react-dom/client'
import Autocomplete from '../components/Autocomplete'

export default function(el, options) {
  createRoot(el).render(
    <Autocomplete
      baseURL={ options.url }
      placeholder={ options.placeholder }
      onSuggestionSelected={ options.onSuggestionSelected }
      clearOnSelect={ options.clearOnSelect || false }
      searchOnEnter={ options.searchOnEnter || false }
      initialValue={ options.initialValue || '' } />
  )
}
