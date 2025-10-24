import { ErrorInfo, ReactNode, StrictMode } from 'react'
import { Container, createRoot } from 'react-dom/client'
import { ErrorBoundary } from 'react-error-boundary'
import { useTranslation } from 'react-i18next'
import * as Sentry from '@sentry/browser'
import { AntdConfigProvider } from './antd'
import { App } from 'antd';

const logError = (error: Error, info: ErrorInfo) => {
  if (Sentry.isInitialized()) {
    Sentry.withScope(scope => {
      scope.setExtra('errorInfo', info)
      Sentry.captureException(error)
    })
  }

  // no need to pass error to DatadogLogger, as it already logs the errors from the browser
}

function FallbackComponent() {
  const { t } = useTranslation()
  return (
    <div className="d-flex flex-column align-items-center justify-content-center">
      ❌ {t('ERROR_BOUNDARY_FALLBACK_MESSAGE')}
    </div>
  )
}

/**
 * Use this root for pages rendered primarily by React
 */
export function AppRootWithDefaults({ children }: { children: ReactNode }) {
  return (
    <RootWithDefaults>
      <App>{children}</App>
    </RootWithDefaults>
  );
}

/**
 * Use this root for small react components on a page primarily rendered by Twig,
 * on the pages primarily rendered by React, use AppRootWithDefaults instead
 */
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
