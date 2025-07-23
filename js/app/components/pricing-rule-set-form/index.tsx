import { createRoot } from 'react-dom/client'
import { Provider } from 'react-redux'

import PricingRuleSetForm from './PricingRuleSetForm'
import { accountSlice } from '../../entities/account/reduxSlice'
import { createStoreFromPreloadedState } from './redux/store'
import { RootWithDefaults } from '../../utils/react'

const container = document.getElementById('pricing-rule-set-form-react')

if (container) {
  const ruleSetId = container.dataset.ruleSetId
  const isNew = container.dataset.isNew === 'true'

  const buildInitialState = () => {
    return {
      [accountSlice.name]: accountSlice.getInitialState(),
    }
  }
  const store = createStoreFromPreloadedState(buildInitialState())

  const root = createRoot(container)

  root.render(
    <RootWithDefaults>
      <Provider store={store}>
        <PricingRuleSetForm
          ruleSetId={ruleSetId ? parseInt(ruleSetId) : null}
          isNew={isNew}
        />
      </Provider>
    </RootWithDefaults>,
  )
}
