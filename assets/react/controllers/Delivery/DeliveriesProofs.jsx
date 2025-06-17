import React, { useState, useRef } from 'react'
import { Modal, DatePicker, Card, Spin } from 'antd'

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
  const storeID = JSON.parse(store_id)
  const [visible, setVisible] = useState(false)
  const [deliveries, setDeliveries] = useState(null)
  const [loading, setLoading] = useState(false)
  const week = useRef(null)

  return (
    <>
      <a href="#" onClick={() => setVisible(true)}>
        <i class="fa fa-flag-checkered mr-2"></i>
        <span>Deliveries proofs</span>
      </a>
      <Modal
        title="Download Proof of Deliveries"
        open={visible}
        onCancel={() => setVisible(false)}
        okButtonProps={{ disabled: loading || !deliveries }}
        onOk={() => {
          downloadZIP(storeID, week.current)
        }}
        okText="Download">
        <p>
          Select week:
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
            <strong>Deliveries</strong>
            <span>
              {loading ? <Spin size="small" /> : (deliveries ?? '-')} items
            </span>
          </div>
          <div className="d-flex align-items-center justify-content-between">
            <strong>Includes</strong>
            <span>Proofs + Report</span>
          </div>
          <div className="d-flex align-items-center justify-content-between">
            <strong>Format</strong>
            <span>ZIP file</span>
          </div>
        </Card>
      </Modal>
    </>
  )
}
