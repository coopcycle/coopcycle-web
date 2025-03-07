import React from 'react'
import { Provider } from 'react-redux'
import { createStoreFromPreloadedState } from '../../order/redux/store'
import { accountSlice } from '../../entities/account/reduxSlice'
import OrdersToInvoice from './components/OrdersToInvoice'
import { TopNav } from '../../components/TopNav'
import { useTranslation } from 'react-i18next'
import { antdLocale } from '../../i18n'
import { ConfigProvider } from 'antd'
import FeaturePreviewTag from '../../components/FeaturePreviewTag'

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
      <ConfigProvider locale={antdLocale}>
        <TopNav>
          {t('ADMIN_INVOICING_TITLE')} <FeaturePreviewTag />
        </TopNav>
        <OrdersToInvoice />
      </ConfigProvider>
    </Provider>
  )
}
