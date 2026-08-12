import React, { useState, useEffect } from 'react'
import { Select, Spin, Button, Input, Space, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { AntdConfigProvider } from '../../../../js/app/utils/antd'

const { Text } = Typography

function getHttpClient() {
  return window._auth ? new window._auth.httpClient() : null
}

function readHiddenInput(inputId) {
  const el = document.getElementById(inputId)
  return el?.value ? parseInt(el.value, 10) : null
}

function writeHiddenInput(inputId, value) {
  const el = document.getElementById(inputId)
  if (el) el.value = value != null ? String(value) : ''
}

export default function ZeltyDishSelect({ restaurantId, inputId }) {
  const { t } = useTranslation()
  const [dishes, setDishes] = useState([])
  const [loading, setLoading] = useState(true)
  const [creating, setCreating] = useState(false)
  const [error, setError] = useState(null)
  const [value, setValue] = useState(() => readHiddenInput(inputId))
  const [manualId, setManualId] = useState(() => {
    const current = readHiddenInput(inputId)
    return current != null ? String(current) : ''
  })

  const fetchDishes = () => {
    const client = getHttpClient()
    if (!client) {
      setLoading(false)
      setError(t('ZELTY_DISH_LOAD_ERROR'))
      return
    }
    setLoading(true)
    setError(null)
    client
      .get(`//${window.location.host}/admin/restaurant/${restaurantId}/zelty/dishes`)
      .then(({ response }) => {
        setDishes(response)
        setLoading(false)
      })
      .catch(() => {
        setLoading(false)
        setError(t('ZELTY_DISH_LOAD_ERROR'))
      })
  }

  useEffect(() => {
    fetchDishes()
    // Re-fetch once the API key has been saved via the "Connect" button,
    // as the initial fetch returns an empty list without a key.
    document.addEventListener('zelty:connected', fetchDishes)
    return () => document.removeEventListener('zelty:connected', fetchDishes)
  }, [restaurantId])

  const applyValue = (val) => {
    setValue(val)
    setManualId(val != null ? String(val) : '')
    writeHiddenInput(inputId, val)
  }

  const handleSelectChange = (val) => {
    applyValue(val ?? null)
  }

  const handleManualIdChange = (e) => {
    const raw = e.target.value
    setManualId(raw)
    const parsed = raw !== '' ? parseInt(raw, 10) : null
    if (raw === '' || !isNaN(parsed)) {
      setValue(parsed)
      writeHiddenInput(inputId, parsed)
    }
  }

  const handleCreate = async () => {
    const client = getHttpClient()
    if (!client) return
    setCreating(true)
    setError(null)
    try {
      const { response } = await client.post(
        `//${window.location.host}/admin/restaurant/${restaurantId}/zelty/delivery-dish`,
        {}
      )
      if (response?.id) {
        const newDish = { id: response.id, name: response.name }
        setDishes(prev =>
          [...prev, newDish].sort((a, b) => a.name.localeCompare(b.name))
        )
        applyValue(response.id)
      }
    } catch {
      setError(t('ZELTY_DISH_CREATE_ERROR'))
    } finally {
      setCreating(false)
    }
  }

  const options = dishes.map(d => ({ value: d.id, label: d.name }))

  return (
    <AntdConfigProvider>
      <Space direction="vertical" style={{ width: '100%' }}>
        <Select
          showSearch
          allowClear
          value={value}
          onChange={handleSelectChange}
          options={options}
          filterOption={(input, option) =>
            option.label.toLowerCase().includes(input.toLowerCase())
          }
          notFoundContent={loading ? <Spin size="small" /> : t('NO_RESULTS')}
          loading={loading}
          style={{ width: '100%' }}
          placeholder={loading ? t('ZELTY_DISH_LOADING') : t('ZELTY_DISH_SEARCH_PLACEHOLDER')}
        />
        <Space>
          <Input
            type="number"
            min={1}
            placeholder={t('ZELTY_DISH_MANUAL_ID_PLACEHOLDER')}
            value={manualId}
            onChange={handleManualIdChange}
            style={{ width: 160 }}
          />
          <Button onClick={handleCreate} loading={creating}>
            {t('ZELTY_DISH_CREATE_BUTTON')}
          </Button>
        </Space>
        {error && <Text type="danger">{error}</Text>}
      </Space>
    </AntdConfigProvider>
  )
}
