import React, { useEffect, useState } from 'react'
import { Switch } from 'antd'
import { CheckCircleFilled, StopFilled } from '@ant-design/icons'
import { useTranslation } from 'react-i18next'
import { AntdConfigProvider } from '../../../../js/app/utils/antd'

function getHttpClient() {
  return window._auth ? new window._auth.httpClient() : null
}

// Deliberately loud: this banner is the only place telling an admin whether
// orders are reaching the POS, so it has to be readable across the room.
const palette = {
  on: { border: '#237804', background: '#f6ffed', text: '#237804' },
  off: { border: '#a8071a', background: '#fff1f0', text: '#a8071a' },
}

export default function ZeltyOrdersToggle({
  restaurantId,
  enabled: initialEnabled = true,
  visible: initialVisible = false,
}) {
  const { t } = useTranslation()
  const [enabled, setEnabled] = useState(initialEnabled)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState(null)
  const [visible, setVisible] = useState(initialVisible)

  useEffect(() => {
    // The API key can be saved without a page reload, via the "Connect" button.
    const reveal = () => setVisible(true)
    document.addEventListener('zelty:connected', reveal)
    return () => document.removeEventListener('zelty:connected', reveal)
  }, [])

  if (!visible) {
    return null
  }

  const handleChange = async checked => {
    const client = getHttpClient()
    if (!client) return

    const previous = enabled

    setEnabled(checked)
    setSaving(true)
    setError(null)

    const { response, error } = await client.post(
      `//${window.location.host}/admin/restaurant/${restaurantId}/zelty/orders-enabled`,
      { enabled: checked },
    )

    setSaving(false)

    if (error) {
      setEnabled(previous)
      setError(t('ZELTY_ORDERS_TOGGLE_ERROR'))
      return
    }

    setEnabled(response.enabled)
  }

  const colors = enabled ? palette.on : palette.off
  const Icon = enabled ? CheckCircleFilled : StopFilled

  return (
    <AntdConfigProvider>
      <div
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: '1rem',
          padding: '1.25rem 1.5rem',
          border: `3px solid ${colors.border}`,
          borderRadius: '6px',
          background: colors.background,
          color: colors.text,
        }}>
        <Icon style={{ fontSize: '2.5rem' }} />
        <div style={{ flex: 1 }}>
          <div
            style={{
              fontSize: '1.5rem',
              fontWeight: 700,
              lineHeight: 1.2,
              textTransform: 'uppercase',
            }}>
            {enabled
              ? t('ZELTY_ORDERS_TOGGLE_ON_TITLE')
              : t('ZELTY_ORDERS_TOGGLE_OFF_TITLE')}
          </div>
          <div style={{ fontSize: '1rem' }}>
            {enabled
              ? t('ZELTY_ORDERS_TOGGLE_ON_HELP')
              : t('ZELTY_ORDERS_TOGGLE_OFF_HELP')}
          </div>
          {error && (
            <div style={{ fontSize: '1rem', fontWeight: 700 }}>{error}</div>
          )}
        </div>
        <Switch
          checked={enabled}
          loading={saving}
          onChange={handleChange}
          checkedChildren={t('ZELTY_ORDERS_TOGGLE_ON_LABEL')}
          unCheckedChildren={t('ZELTY_ORDERS_TOGGLE_OFF_LABEL')}
          style={{ transform: 'scale(1.4)', transformOrigin: 'right center' }}
        />
      </div>
    </AntdConfigProvider>
  )
}
