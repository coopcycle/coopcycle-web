import React, { useEffect, useState } from 'react'
import { Alert, Button, Input, Space, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { AntdConfigProvider } from '../../../../js/app/utils/antd'

const { Text } = Typography

function getHttpClient() {
  return window._auth ? new window._auth.httpClient() : null
}

export default function ZeltyConnect({
  restaurantId,
  inputId,
  revealSelector,
  needsSecret: initialNeedsSecret = false,
}) {
  const { t } = useTranslation()
  const [dirty, setDirty] = useState(false)
  const [connecting, setConnecting] = useState(false)
  const [status, setStatus] = useState(null)
  const [needsSecret, setNeedsSecret] = useState(initialNeedsSecret)
  const [secretValue, setSecretValue] = useState('')
  const [savingSecret, setSavingSecret] = useState(false)
  const [secretStatus, setSecretStatus] = useState(null)

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

    const { response, error } = await client.post(
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
    setNeedsSecret(!response?.secretSaved)
    setSecretStatus(null)

    if (revealSelector) {
      const el = document.querySelector(revealSelector)
      if (el) el.style.display = ''
    }
    document.dispatchEvent(new CustomEvent('zelty:connected'))
  }

  const handleSaveSecret = async () => {
    const client = getHttpClient()
    if (!client) return

    setSavingSecret(true)
    setSecretStatus(null)

    const { error } = await client.post(
      `//${window.location.host}/admin/restaurant/${restaurantId}/zelty/webhook-secret`,
      { secretKey: secretValue.trim() }
    )

    setSavingSecret(false)

    if (error) {
      setSecretStatus({ type: 'danger', message: t('ZELTY_SECRET_SAVE_ERROR') })
      return
    }

    setNeedsSecret(false)
    setSecretStatus({ type: 'success', message: t('ZELTY_SECRET_SAVE_SUCCESS') })
  }

  return (
    <AntdConfigProvider>
      <Space direction="vertical" style={{ width: '100%' }}>
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
        {needsSecret && (
          <Alert
            type="warning"
            showIcon
            message={t('ZELTY_SECRET_PROMPT')}
            description={
              <Space>
                <Input.Password
                  placeholder={t('ZELTY_SECRET_PLACEHOLDER')}
                  value={secretValue}
                  onChange={e => setSecretValue(e.target.value)}
                  style={{ width: 320 }}
                />
                <Button
                  disabled={secretValue.trim() === ''}
                  loading={savingSecret}
                  onClick={handleSaveSecret}>
                  {t('ZELTY_SECRET_SAVE_BUTTON')}
                </Button>
              </Space>
            }
          />
        )}
        {secretStatus && (
          <Text type={secretStatus.type}>{secretStatus.message}</Text>
        )}
      </Space>
    </AntdConfigProvider>
  )
}
