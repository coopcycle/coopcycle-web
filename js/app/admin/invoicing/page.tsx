import React from 'react'
import { Provider } from 'react-redux'
import { accountSlice } from '../../entities/account/reduxSlice'
import OrdersToInvoice from './components/OrdersToInvoice'
import { TopNav } from '../../components/TopNav'
import { useTranslation } from 'react-i18next'
import FeaturePreviewTag from '../../components/FeaturePreviewTag'
import { createStoreFromPreloadedState } from './redux/store'
import { AntdConfigProvider } from '../../utils/antd'

const buildInitialState = () => {
  return {
    [accountSlice.name]: accountSlice.getInitialState(),
  }
}

const store = createStoreFromPreloadedState(buildInitialState())

export default () => {
  const { t } = useTranslation()

  return (
    <Provider store={store}>
      <AntdConfigProvider>
        <TopNav>
          {t('ADMIN_INVOICING_TITLE')} <FeaturePreviewTag />
        </TopNav>
        <OrdersToInvoice />
      </AntdConfigProvider>
    </Provider>
  )
}
