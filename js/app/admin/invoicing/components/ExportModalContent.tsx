import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Radio, RadioChangeEvent, Space } from 'antd'

import Button from '../../../components/core/Button'
import { downloadStandardFile, downloadOdooFile } from '../redux/actions'
import { useAppDispatch } from '../redux/store'
import moment from 'moment'

const DESTINATIONS = {
  standard: 'standard',
  odoo: 'odoo',
}

type Props = {
  dateRange: moment.Moment[]
  params: string[]
  setModalOpen: (open: boolean) => void
}

export default ({ dateRange, params, setModalOpen }: Props) => {
  const [destination, setDestination] = useState(DESTINATIONS.standard)

  const dispatch = useAppDispatch()

  const { t } = useTranslation()

  const onChange = (e: RadioChangeEvent) => {
    setDestination(e.target.value)
  }

  const download = () => {
    const filename = [
      t('ADMIN_ORDERS_TO_INVOICE_FILE_NAME_PREFIX'),
      dateRange[0].format('YYYY-MM-DD'),
      dateRange[1].format('YYYY-MM-DD'),
    ].join('_')

    switch (destination) {
      case DESTINATIONS.standard:
        dispatch(
          downloadStandardFile({
            params,
            filename,
          }),
        )
        break
      case DESTINATIONS.odoo:
        dispatch(
          downloadOdooFile({
            params,
            filename,
          }),
        )
        break
      default:
        break
    }
  }

  return (
    <>
      <div className="modal-header">
        <h4 className="modal-title">
          {t('ADMIN_DASHBOARD_NAV_EXPORT')}
          <a className="pull-right" onClick={() => setModalOpen(false)}>
            <i className="fa fa-close"></i>
          </a>
        </h4>
      </div>
      <main className="modal-body d-flex flex-column">
        <h5>{t('ADMIN_ORDERS_TO_INVOICE_FILE_FORMAT')}:</h5>
        <Radio.Group onChange={onChange} value={destination}>
          <Space direction="vertical">
            <Radio value={DESTINATIONS.standard}>
              {t('ADMIN_ORDERS_TO_INVOICE_DESTINATION_STANDARD')}
            </Radio>
            <Radio value={DESTINATIONS.odoo}>
              {t('ADMIN_ORDERS_TO_INVOICE_DESTINATION_ODOO')}
            </Radio>
          </Space>
        </Radio.Group>
      </main>
      <footer className="modal-footer">
        <Button
          testID="invoicing.download.file"
          primary
          block
          icon="download"
          onClick={() => {
            download()
          }}>
          {t('ADMIN_ORDERS_TO_INVOICE_DOWNLOAD')}
        </Button>
      </footer>
    </>
  )
}
