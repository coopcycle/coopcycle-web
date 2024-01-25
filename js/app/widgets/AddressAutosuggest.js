import React from 'react'
import { createRoot } from 'react-dom/client';
import AddressAutosuggest from '../components/AddressAutosuggest'

export default function(el, options) {
  const root = createRoot(el);
  root.render(<AddressAutosuggest { ...options } />)
}
