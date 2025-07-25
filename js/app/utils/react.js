import React, { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { AntdConfigProvider } from './antd'
import { ErrorBoundary } from 'react-error-boundary'
import { useTranslation } from 'react-i18next'

function FallbackComponent() {
  const { t } = useTranslation()
  return (
    <div className="d-flex flex-column align-items-center">
      {t('ERROR_BOUNDARY_FALLBACK_MESSAGE')}
    </div>
  )
}

export function RootWithDefaults({ children }) {
  return (
    <StrictMode>
      <ErrorBoundary fallback={<FallbackComponent />}>
        <AntdConfigProvider>{children}</AntdConfigProvider>
      </ErrorBoundary>
    </StrictMode>
  )
}

// Drop-in replacement for legacy React 17 render
export function render(component, el) {
  const root = createRoot(el)
  root.render(<RootWithDefaults>{component}</RootWithDefaults>)
}
