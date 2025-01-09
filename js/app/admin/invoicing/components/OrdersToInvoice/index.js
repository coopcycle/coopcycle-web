import React, { useMemo, useState } from 'react'
import Modal from 'react-modal'
import { useTranslation } from 'react-i18next'
import { InputNumber, DatePicker } from 'antd'

import Button from '../../../../components/core/Button'
import { prepareParams } from '../../redux/actions'
import ExportModalContent from '../ExportModalContent'
import OrdersTable from '../OrdersTable'

const ordersStates = ['new', 'accepted', 'fulfilled']

export default () => {
  const [storeId, setStoreId] = useState(null)
  const [dateRange, setDateRange] = useState(null)

  const [reloadKey, setReloadKey] = useState(0)

  const [isModalOpen, setModalOpen] = useState(false)

  const { t } = useTranslation()

  const params = useMemo(() => {
    if (!storeId) {
      return null
    }

    if (!dateRange) {
      return null
    }

    return prepareParams({
      store: [storeId],
      dateRange: [
        dateRange[0].format('YYYY-MM-DD'),
        dateRange[1].format('YYYY-MM-DD'),
      ],
      state: ordersStates,
    })
  }, [storeId, dateRange])

  return (
    // marginTop: 48px: h5 marginTop (10px) + 38px
    <div style={{ marginTop: '38px' }}>
      <h5>{t('ADMIN_ORDERS_TO_INVOICE_TITLE')}</h5>
      <div className="d-flex" style={{ marginTop: '12px', gap: '24px' }}>
        {t('ADMIN_DASHBOARD_NAV_FILTERS')}:
        <div className="d-flex flex-column">
          {t('ADMIN_ORDERS_TO_INVOICE_FILTER_RANGE')}
          <DatePicker.RangePicker onChange={setDateRange} />
        </div>
        <div className="d-flex flex-column">
          {t('ADMIN_ORDERS_TO_INVOICE_FILTER_STORE')}
          <InputNumber min={0} onChange={setStoreId} />
        </div>
        <div className="d-flex flex-column justify-content-end">
          <Button
            primary
            onClick={() => {
              setReloadKey(reloadKey + 1)
            }}>
            {t('ADMIN_ORDERS_TO_INVOICE_REFRESH')}
          </Button>
        </div>
      </div>
      <OrdersTable
        ordersStates={ordersStates}
        dateRange={dateRange}
        storeId={storeId}
        reloadKey={reloadKey}
      />
      <div className="d-flex justify-content-end" style={{ marginTop: '24px' }}>
        <Button
          primary
          onClick={() => {
            if (!params) {
              return
            }

            setModalOpen(true)
          }}>
          {t('ADMIN_ORDERS_TO_INVOICE_DOWNLOAD')}
        </Button>
      </div>
      <Modal
        isOpen={isModalOpen}
        appElement={document.getElementById('invoicing')}
        className="ReactModal__Content--no-default" // disable additional inline style from react-modal
        shouldCloseOnOverlayClick={true}
        shouldCloseOnEsc={true}
        style={{ content: { overflow: 'unset' } }}>
        <ExportModalContent
          dateRange={dateRange}
          params={params}
          setModalOpen={setModalOpen}
        />
      </Modal>
    </div>
  )
}
