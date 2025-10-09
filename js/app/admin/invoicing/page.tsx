import { Provider } from 'react-redux'
import OrdersToInvoice from './components/OrdersToInvoice'
import { TopNav } from '../../components/TopNav'
import { useTranslation } from 'react-i18next'
import FeaturePreviewTag from '../../components/FeaturePreviewTag'
import { store } from './redux/store'

export default () => {
  const { t } = useTranslation()

  return (
    <Provider store={store}>
      <div>
        <TopNav>
          {t('ADMIN_INVOICING_TITLE')} <FeaturePreviewTag />
        </TopNav>
        <OrdersToInvoice />
      </div>
    </Provider>
  )
}
