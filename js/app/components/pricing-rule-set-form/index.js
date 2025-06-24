import React, { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { Provider } from 'react-redux'
import { ConfigProvider } from 'antd'
import { I18nextProvider } from 'react-i18next'

// import PricingRuleSetForm from './PricingRuleSetForm'
import { createStoreFromPreloadedState } from '../delivery-form/redux/store'
import { antdLocale } from '../../i18n'
import i18n from '../../i18n'

// Initialize the app
const container = document.getElementById('pricing-rule-set-form-react')

if (container) {
  const ruleSetId = container.dataset.ruleSetId
  const isNew = container.dataset.isNew === 'true'

  // Create Redux store
  const store = createStoreFromPreloadedState({})

  const root = createRoot(container)

  root.render(
    <StrictMode>
      <Provider store={store}>
        <I18nextProvider i18n={i18n}>
          <ConfigProvider locale={antdLocale}>
            {/*<PricingRuleSetForm*/}
            {/*  ruleSetId={ruleSetId ? parseInt(ruleSetId) : null}*/}
            {/*  isNew={isNew}*/}
            {/*/>*/}
          </ConfigProvider>
        </I18nextProvider>
      </Provider>
    </StrictMode>,
  )
}
