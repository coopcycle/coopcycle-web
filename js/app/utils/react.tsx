import { ErrorInfo, ReactNode, StrictMode } from 'react'
import { Container, createRoot } from 'react-dom/client'
import { ErrorBoundary } from 'react-error-boundary'
import { useTranslation } from 'react-i18next'
import * as Sentry from '@sentry/browser'
import { AntdConfigProvider } from './antd'

const logError = (error: Error, info: ErrorInfo) => {
  if (Sentry.isInitialized()) {
    Sentry.withScope(scope => {
      scope.setExtra('errorInfo', info)
      Sentry.captureException(error)
    })
  }

  if (window.DatadogLogger) {
    window.DatadogLogger.error(error.message, info, error)
  }
}

function FallbackComponent() {
  const { t } = useTranslation()
  return (
    <div className="d-flex flex-column align-items-center">
      {t('ERROR_BOUNDARY_FALLBACK_MESSAGE')}
    </div>
  )
}

export function RootWithDefaults({ children }: { children: ReactNode }) {
  return (
    <StrictMode>
      <ErrorBoundary fallback={<FallbackComponent />} onError={logError}>
        <AntdConfigProvider>{children}</AntdConfigProvider>
      </ErrorBoundary>
    </StrictMode>
  )
}

// Drop-in replacement for legacy React 17 render
export function render(component: ReactNode, el: Container) {
  const root = createRoot(el)
  root.render(<RootWithDefaults>{component}</RootWithDefaults>)
}
