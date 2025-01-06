import React from 'react'
import { Provider } from 'react-redux'
import { createStoreFromPreloadedState } from '../../order/redux/store'
import { accountSlice } from '../../entities/account/reduxSlice'
import OrdersToInvoice from './components/OrdersToInvoice'
import { TopNav } from '../../components/TopNav'
import { useTranslation } from 'react-i18next'

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
      <TopNav>{t('ADMIN_INVOICING_TITLE')}</TopNav>
      <OrdersToInvoice />
    </Provider>
  )
}
