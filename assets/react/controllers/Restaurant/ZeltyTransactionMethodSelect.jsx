import React, { useEffect, useState } from 'react'
import { Select, Spin, Space, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { AntdConfigProvider } from '../../../../js/app/utils/antd'

const { Text } = Typography

function getHttpClient() {
  return window._auth ? new window._auth.httpClient() : null
}

function readHiddenInput(inputId) {
  return document.getElementById(inputId)?.value || ''
}

function writeHiddenInput(inputId, value) {
  const el = document.getElementById(inputId)
  if (el) el.value = value || ''
}

export default function ZeltyTransactionMethodSelect({ restaurantId, inputId }) {
  const { t } = useTranslation()
  const [methods, setMethods] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [value, setValue] = useState(() => readHiddenInput(inputId))

  const fetchMethods = async () => {
    const client = getHttpClient()
    if (!client) {
      setLoading(false)
      setError(t('ZELTY_TRANSACTION_METHOD_LOAD_ERROR'))
      return
    }

    setLoading(true)
    setError(null)

    const { response, error } = await client.get(
      `//${window.location.host}/admin/restaurant/${restaurantId}/zelty/transaction-methods`,
    )

    setLoading(false)

    // The http client resolves on errors too, so check the payload explicitly.
    if (error || !Array.isArray(response)) {
      setError(t('ZELTY_TRANSACTION_METHOD_LOAD_ERROR'))
      return
    }

    setMethods(response)
  }

  useEffect(() => {
    fetchMethods()
    // Re-fetch once the API key has been saved via the "Connect" button,
    // as the initial fetch returns an empty list without a key.
    document.addEventListener('zelty:connected', fetchMethods)
    return () => document.removeEventListener('zelty:connected', fetchMethods)
  }, [restaurantId])

  const handleChange = val => {
    const next = val || ''
    setValue(next)
    writeHiddenInput(inputId, next)
  }

  // The orders API takes the method by name, so that is what we store
  const options = methods.map(m => ({ value: m.name, label: m.name }))

  // The saved method may be missing from the list: renamed on the till, deleted,
  // or simply the "CB" default this shop never had. Keep it selectable.
  if (value && !options.some(o => o.value === value)) {
    options.unshift({ value, label: value })
  }

  return (
    <AntdConfigProvider>
      <Space direction="vertical" style={{ width: '100%' }}>
        <Select
          showSearch
          value={value || undefined}
          onChange={handleChange}
          options={options}
          filterOption={(input, option) =>
            option.label.toLowerCase().includes(input.toLowerCase())
          }
          notFoundContent={loading ? <Spin size="small" /> : t('NO_RESULTS')}
          loading={loading}
          style={{ width: '100%' }}
          placeholder={
            loading
              ? t('ZELTY_TRANSACTION_METHOD_LOADING')
              : t('ZELTY_TRANSACTION_METHOD_PLACEHOLDER')
          }
        />
        {error && <Text type="danger">{error}</Text>}
      </Space>
    </AntdConfigProvider>
  )
}
