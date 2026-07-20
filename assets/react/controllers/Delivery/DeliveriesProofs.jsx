import React, { useState, useRef } from 'react'
import { Alert, Modal, DatePicker, Card, Spin, notification } from 'antd'
import { useTranslation } from 'react-i18next'

function _dateWeekRange(picked) {
  return {
    from: picked.clone().startOf('week'),
    to: picked.clone().endOf('week'),
  }
}

function _saveBlob(blob, filename) {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
}

async function _fetchDeliveriesCount(id, range, signal) {
  const httpClient = new window._auth.httpClient()
  const { response, error } = await httpClient.get(
    window.Routing.generate('_api_/stores/{id}/deliveries_get_collection', {
      id,
    }),
    {
      'pickup.after[after]': range.from.toISOString(),
      'dropoff.before[before]': range.to.toISOString(),
    },
    {},
    { signal },
  )
  if (error) {
    throw error
  }

  return response['hydra:totalItems']
}

async function _downloadZIP(id, range, signal) {
  const httpClient = new window._auth.httpClient()
  const { response, error } = await httpClient.post(
    window.Routing.generate('_api_/stores/{id}/pod_export_post', { id }),
    {
      store: id,
      from: range.from.toISOString(),
      to: range.to.toISOString(),
    },
    null,
    {
      responseType: 'blob',
      signal,
    },
  )
  if (error) {
    throw error
  }

  const blob =
    response instanceof Blob
      ? response
      : new Blob([response], { type: 'application/zip' })
  _saveBlob(
    blob,
    `deliveries_${range.from.format('DD-MM-YYYY')}_${range.to.format('DD-MM-YYYY')}.zip`,
  )
}

export default function DeliveriesProofs({ store_id = null }) {
  const { t } = useTranslation()
  const storeID = Number(store_id) || store_id
  const [open, setOpen] = useState(false)
  const [range, setRange] = useState(null)
  const [deliveries, setDeliveries] = useState(null)
  const [loading, setLoading] = useState(false)
  const [downloading, setDownloading] = useState(false)
  const [error, setError] = useState(null)
  const controller = useRef(null)

  const _handleClose = () => {
    if (controller.current) {
      controller.current.abort()
      controller.current = null
    }
    setOpen(false)
    setRange(null)
    setDeliveries(null)
    setLoading(false)
    setDownloading(false)
    setError(null)
  }

  const _handlePickWeek = async moment => {
    if (!moment) {
      setRange(null)
      setDeliveries(null)
      setError(null)
      return
    }

    const next = _dateWeekRange(moment)
    setRange(next)
    setError(null)

    if (controller.current) {
      controller.current.abort()
    }
    const ctl = new AbortController()
    controller.current = ctl

    setLoading(true)
    try {
      const count = await _fetchDeliveriesCount(storeID, next, ctl.signal)
      if (controller.current === ctl) {
        setDeliveries(count)
      }
    } catch (err) {
      if (err.name !== 'CanceledError' && err.name !== 'AbortError') {
        setDeliveries(null)
        setError(err)
      }
    } finally {
      if (controller.current === ctl) {
        controller.current = null
        setLoading(false)
      }
    }
  }

  const _handleDownload = async () => {
    if (!range) {
      return
    }

    const ctl = new AbortController()
    setDownloading(true)
    try {
      await _downloadZIP(storeID, range, ctl.signal)
    } catch (err) {
      if (err.name !== 'CanceledError' && err.name !== 'AbortError') {
        notification.error({
          message: t('DELIVERY_PROOFS_DOWNLOAD_ERROR', {
            defaultValue: 'Could not download deliveries',
          }),
        })
      }
    } finally {
      setDownloading(false)
    }
  }

  const _renderDeliveriesValue = () => {
    if (loading) {
      return <Spin size="small" />
    }
    if (error || deliveries === null) {
      return '—'
    }
    return t('DELIVERY_PROOFS_DELIVERIES_COUNT', {
      count: deliveries,
      defaultValue: '—',
    })
  }

  const _canDownload = range !== null && !loading && !downloading && !error

  return (
    <>
      <a
        href="#"
        onClick={e => {
          e.preventDefault()
          setOpen(true)
        }}>
        <i className="fa fa-flag-checkered mr-2" aria-hidden="true"></i>
        <span>{t('DELIVERY_PROOFS_LINK_TEXT')}</span>
      </a>
      <Modal
        title={t('DELIVERY_PROOFS_MODAL_TITLE')}
        open={open}
        onCancel={_handleClose}
        confirmLoading={downloading}
        okButtonProps={{ disabled: !_canDownload }}
        onOk={_handleDownload}
        okText={t('DELIVERY_PROOFS_DOWNLOAD_BUTTON')}
        width={640}
        destroyOnHidden>
        <div className="mb-3">
          {t('DELIVERY_PROOFS_CHOOSE_WEEK')}:
          <DatePicker
            picker="week"
            className="mx-2"
            onChange={_handlePickWeek}
          />
        </div>
        {error && (
          <Alert
            type="error"
            showIcon
            className="mb-3"
            message={t('DELIVERY_PROOFS_LOAD_ERROR', {
              defaultValue: 'Could not load deliveries',
            })}
          />
        )}
        <Card>
          <div className="d-flex align-items-center justify-content-between">
            <strong>{t('DELIVERY_PROOFS_TOTAL_DELIVERIES')}</strong>
            <span>{_renderDeliveriesValue()}</span>
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
