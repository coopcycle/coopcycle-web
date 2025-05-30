import React, { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { ConfigProvider } from 'antd'
import { antdLocale } from '../i18n'

// Drop-in replacement for legacy React 17 render
export function render(component, el) {
  const root = createRoot(el)
  root.render(<StrictMode>{component}</StrictMode>)
}

export function RootWithDefaults({ children }) {
  return (
    <StrictMode>
      <ConfigProvider locale={antdLocale}>{children}</ConfigProvider>
    </StrictMode>
  )
}
