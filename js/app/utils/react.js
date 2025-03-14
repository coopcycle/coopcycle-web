import React, { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'

// Drop-in replacement for legacy React 17 render
export function render(component, el) {
  const root = createRoot(el)
  root.render(<StrictMode>{component}</StrictMode>)
}
