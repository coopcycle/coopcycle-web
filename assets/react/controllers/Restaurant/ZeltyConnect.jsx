import React, { useEffect, useState } from 'react'
import { Button, Space, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { AntdConfigProvider } from '../../../../js/app/utils/antd'

const { Text } = Typography

function getHttpClient() {
  return window._auth ? new window._auth.httpClient() : null
}

export default function ZeltyConnect({ restaurantId, inputId, revealSelector }) {
  const { t } = useTranslation()
  const [dirty, setDirty] = useState(false)
  const [connecting, setConnecting] = useState(false)
  const [status, setStatus] = useState(null)

  useEffect(() => {
    const input = document.getElementById(inputId)
    if (!input) return
    const initial = input.value
    const onInput = () =>
      setDirty(input.value.trim() !== '' && input.value !== initial)
    input.addEventListener('input', onInput)
    return () => input.removeEventListener('input', onInput)
  }, [inputId])

  const handleConnect = async () => {
    const input = document.getElementById(inputId)
    const client = getHttpClient()
    if (!input || !client) return

    setConnecting(true)
    setStatus(null)

    const { error } = await client.post(
      `//${window.location.host}/admin/restaurant/${restaurantId}/zelty/connect`,
      { apiKey: input.value.trim() }
    )

    setConnecting(false)

    if (error) {
      setStatus({
        type: 'danger',
        message:
          error.response?.status === 422
            ? t('ZELTY_CONNECT_INVALID_KEY')
            : t('ZELTY_CONNECT_ERROR'),
      })
      return
    }

    setDirty(false)
    setStatus({ type: 'success', message: t('ZELTY_CONNECT_SUCCESS') })

    if (revealSelector) {
      document
        .querySelectorAll(revealSelector)
        .forEach(el => (el.style.display = ''))
    }
    document.dispatchEvent(new CustomEvent('zelty:connected'))
  }

  return (
    <AntdConfigProvider>
      <Space>
        <Button
          type="primary"
          disabled={!dirty}
          loading={connecting}
          onClick={handleConnect}>
          {t('ZELTY_CONNECT_BUTTON')}
        </Button>
        {status && <Text type={status.type}>{status.message}</Text>}
      </Space>
    </AntdConfigProvider>
  )
}
