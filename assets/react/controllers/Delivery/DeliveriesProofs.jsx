import React, { useState, useRef } from 'react'
import { Modal, DatePicker, Card, Spin } from 'antd'
import { useTranslation } from 'react-i18next'

function _dateWeekRange(picked) {
  return {
    from: picked.clone().startOf('week'),
    to: picked.clone().endOf('week'),
  }
}

async function deliveriesCount(id, { from, to }) {
  const httpClient = new window._auth.httpClient()

  const { response, error } = await httpClient.get(
    window.Routing.generate('_api_/stores/{id}/deliveries_get_collection', {
      id,
    }),
    {
      'pickup.after[after]': from.toISOString(),
      'dropoff.before[before]': to.toISOString(),
    },
  )
  if (error) {
    return 0
  }

  return response['hydra:totalItems']
}

async function downloadZIP(id, { from, to }) {
  const httpClient = new window._auth.httpClient()
  const { response, error } = await httpClient.post(
    window.Routing.generate('_api_/deliveries/pod_export_post'),
    {
      store: id,
      from: from.toISOString(),
      to: to.toISOString(),
    },
    null,
    {
      responseType: 'blob',
    },
  )
  const blobUrl = URL.createObjectURL(
    new Blob([response], { type: 'application/zip' }),
  )
  const a = document.createElement('a')
  a.href = blobUrl
  a.download = `deliveries_${from.format('DD-MM-YYYY')}_${to.format('DD-MM-YYYY')}.zip`
  a.target = '_blank'
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)

  setTimeout(() => URL.revokeObjectURL(blobUrl), 1000)
}

export default function DeliveriesProofs({ store_id = null }) {
  const { t } = useTranslation()
  const storeID = JSON.parse(store_id)
  const [visible, setVisible] = useState(false)
  const [deliveries, setDeliveries] = useState(null)
  const [loading, setLoading] = useState(false)
  const week = useRef(null)

  return (
    <>
      <a href="#" onClick={() => setVisible(true)}>
        <i class="fa fa-flag-checkered mr-2"></i>
        <span>{t('DELIVERY_PROOFS_LINK_TEXT')}</span>
      </a>
      <Modal
        title={t('DELIVERY_PROOFS_MODAL_TITLE')}
        open={visible}
        onCancel={() => setVisible(false)}
        okButtonProps={{ disabled: loading || !deliveries }}
        onOk={() => {
          downloadZIP(storeID, week.current)
        }}
        okText={t('DELIVERY_PROOFS_DOWNLOAD_BUTTON')}>
        <p>
          {t('DELIVERY_PROOFS_CHOOSE_WEEK')}:
          <DatePicker
            picker="week"
            className="mx-2"
            onChange={async (moment, _) => {
              setLoading(true)
              week.current = _dateWeekRange(moment)
              setDeliveries(await deliveriesCount(storeID, week.current))
              setLoading(false)
            }}
          />
        </p>
        <Card>
          <div className="d-flex align-items-center justify-content-between">
            <strong>{t('DELIVERY_PROOFS_TOTAL_DELIVERIES')}</strong>
            <span>
              {loading ? <Spin size="small" /> : t('DELIVERY_PROOFS_DELIVERIES_COUNT', { count: deliveries ?? 0, defaultValue: '—' })}
            </span>
          </div>
          <div className="d-flex align-items-center justify-content-between">
            <strong>{t('DELIVERY_PROOFS_CONTENTS')}</strong>
            <span>{t('DELIVERY_PROOFS_CONTENTS_VALUE')}</span>
          </div>
          <div className="d-flex align-items-center justify-content-between">
            <strong>{t('DELIVERY_PROOFS_FILE_TYPE')}</strong>
            <span>{t('DELIVERY_PROOFS_FILE_TYPE_VALUE')}</span>
          </div>
        </Card>
      </Modal>
    </>
  )
}
