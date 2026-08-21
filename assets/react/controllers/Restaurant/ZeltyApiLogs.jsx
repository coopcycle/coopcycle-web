import React, { useEffect, useRef, useState } from 'react'
import { Alert, Button, Empty, Space, Table, Tag, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { AntdConfigProvider } from '../../../../js/app/utils/antd'

const { Text, Paragraph } = Typography

const PAGE_SIZE = 25
const MAX_PAGE_SIZE = 100
const POLL_INTERVAL = 30000

function formatAgo(seconds) {
  if (seconds < 60) {
    return `${seconds}s`
  }
  return `${Math.floor(seconds / 60)}m`
}

function getHttpClient() {
  return window._auth ? new window._auth.httpClient() : null
}

function formatBody(body) {
  if (!body) return null
  try {
    return JSON.stringify(JSON.parse(body), null, 2)
  } catch {
    // Not JSON (an HTML error page, a truncated payload…): show it as-is.
    return body
  }
}

function BodyBlock({ title, body }) {
  if (!body) return null

  return (
    <div style={{ marginBottom: '0.75rem' }}>
      <Text strong>{title}</Text>
      <Paragraph>
        <pre
          style={{
            maxHeight: '18rem',
            overflow: 'auto',
            fontSize: '0.75rem',
            marginTop: '0.25rem',
          }}>
          {formatBody(body)}
        </pre>
      </Paragraph>
    </div>
  )
}

export default function ZeltyApiLogs({ restaurantId }) {
  const { t } = useTranslation()
  const [logs, setLogs] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [hasMore, setHasMore] = useState(false)
  const [updatedAt, setUpdatedAt] = useState(null)
  const [secondsAgo, setSecondsAgo] = useState(0)

  // Read from the polling interval, which must not close over a stale value
  const loadedCountRef = useRef(0)
  const inFlightRef = useRef(false)

  const baseUrl = `//${window.location.host}/admin/restaurant/${restaurantId}/zelty/logs`

  const fetchLogs = async (nextOffset = 0, { background = false, limit = PAGE_SIZE } = {}) => {
    const client = getHttpClient()
    if (!client) {
      setLoading(false)
      setError(t('ZELTY_LOGS_LOAD_ERROR'))
      return
    }

    if (inFlightRef.current) {
      return
    }
    inFlightRef.current = true

    if (!background) {
      setLoading(true)
    }
    setError(null)

    const { response, error } = await client.get(
      `${baseUrl}?limit=${limit}&offset=${nextOffset}`,
    )

    inFlightRef.current = false

    if (!background) {
      setLoading(false)
    }

    if (error || !response) {
      setError(t('ZELTY_LOGS_LOAD_ERROR'))
      return
    }

    if (nextOffset === 0) {
      setLogs(response.logs)
      loadedCountRef.current = response.logs.length
    } else {
      setLogs(prev => {
        const merged = [...prev, ...response.logs]
        loadedCountRef.current = merged.length
        return merged
      })
    }

    setHasMore(response.hasMore)
    setUpdatedAt(Date.now())
    setSecondsAgo(0)
  }

  useEffect(() => {
    fetchLogs(0)
  }, [restaurantId])

  // Poll in the background, keeping however many pages are already loaded
  useEffect(() => {
    const interval = setInterval(() => {
      if (document.hidden) {
        return
      }
      const limit = Math.min(
        MAX_PAGE_SIZE,
        Math.max(PAGE_SIZE, loadedCountRef.current),
      )
      fetchLogs(0, { background: true, limit })
    }, POLL_INTERVAL)

    return () => clearInterval(interval)
  }, [restaurantId])

  // Ticker for the "last update" indicator
  useEffect(() => {
    if (updatedAt === null) {
      return
    }
    const interval = setInterval(
      () => setSecondsAgo(Math.round((Date.now() - updatedAt) / 1000)),
      1000,
    )

    return () => clearInterval(interval)
  }, [updatedAt])

  const columns = [
    {
      title: t('ZELTY_LOGS_COLUMN_DATE'),
      dataIndex: 'createdAt',
      width: 170,
      render: value => new Date(value).toLocaleString(),
    },
    {
      title: t('ZELTY_LOGS_COLUMN_DIRECTION'),
      dataIndex: 'direction',
      width: 110,
      render: value => (
        <Tag color={value === 'incoming' ? 'purple' : 'blue'}>
          {value === 'incoming'
            ? t('ZELTY_LOGS_DIRECTION_INCOMING')
            : t('ZELTY_LOGS_DIRECTION_OUTGOING')}
        </Tag>
      ),
    },
    {
      title: t('ZELTY_LOGS_COLUMN_ENDPOINT'),
      dataIndex: 'endpoint',
      render: (value, record) => (
        <span>
          <Text code>{record.method}</Text> {value}
        </span>
      ),
    },
    {
      title: t('ZELTY_LOGS_COLUMN_STATUS'),
      dataIndex: 'statusCode',
      width: 100,
      render: (value, record) => (
        <Tag color={record.successful ? 'green' : 'red'}>
          {value ?? t('ZELTY_LOGS_STATUS_FAILED')}
        </Tag>
      ),
    },
    {
      title: t('ZELTY_LOGS_COLUMN_DURATION'),
      dataIndex: 'durationMs',
      width: 90,
      render: value => (value === null ? '—' : `${value} ms`),
    },
  ]

  return (
    <AntdConfigProvider>
      <Space direction="vertical" style={{ width: '100%' }}>
        <Space wrap>
          <Button onClick={() => fetchLogs(0)} loading={loading}>
            {t('ZELTY_LOGS_REFRESH')}
          </Button>
          {updatedAt !== null && (
            <Text type="secondary">
              {t('ZELTY_LOGS_LAST_UPDATE', { ago: formatAgo(secondsAgo) })}
            </Text>
          )}
          <Text type="secondary">{t('ZELTY_LOGS_HELP')}</Text>
        </Space>
        {error && <Alert type="error" showIcon message={error} />}
        <Table
          size="small"
          rowKey="id"
          columns={columns}
          dataSource={logs}
          loading={loading && logs.length === 0}
          pagination={false}
          scroll={{ x: true }}
          locale={{
            emptyText: <Empty description={t('ZELTY_LOGS_EMPTY')} />,
          }}
          expandable={{
            expandedRowRender: record => (
              <div>
                {record.error && (
                  <Alert
                    type="error"
                    showIcon
                    message={record.error}
                    style={{ marginBottom: '0.75rem' }}
                  />
                )}
                <BodyBlock
                  title={t('ZELTY_LOGS_REQUEST_BODY')}
                  body={record.requestBody}
                />
                <BodyBlock
                  title={t('ZELTY_LOGS_RESPONSE_BODY')}
                  body={record.responseBody}
                />
                {!record.error && !record.requestBody && !record.responseBody && (
                  <Text type="secondary">{t('ZELTY_LOGS_NO_BODY')}</Text>
                )}
              </div>
            ),
          }}
        />
        {hasMore && (
          <Button onClick={() => fetchLogs(loadedCountRef.current)} loading={loading}>
            {t('ZELTY_LOGS_LOAD_MORE')}
          </Button>
        )}
      </Space>
    </AntdConfigProvider>
  )
}
