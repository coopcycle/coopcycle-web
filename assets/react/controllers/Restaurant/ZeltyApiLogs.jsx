import React, { useEffect, useState } from 'react'
import { Alert, Button, Empty, Space, Table, Tag, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { AntdConfigProvider } from '../../../../js/app/utils/antd'

const { Text, Paragraph } = Typography

const PAGE_SIZE = 25

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
  const [offset, setOffset] = useState(0)
  const [hasMore, setHasMore] = useState(false)

  const baseUrl = `//${window.location.host}/admin/restaurant/${restaurantId}/zelty/logs`

  const fetchLogs = async (nextOffset = 0) => {
    const client = getHttpClient()
    if (!client) {
      setLoading(false)
      setError(t('ZELTY_LOGS_LOAD_ERROR'))
      return
    }

    setLoading(true)
    setError(null)

    const { response, error } = await client.get(
      `${baseUrl}?limit=${PAGE_SIZE}&offset=${nextOffset}`,
    )

    setLoading(false)

    if (error || !response) {
      setError(t('ZELTY_LOGS_LOAD_ERROR'))
      return
    }

    setLogs(nextOffset === 0 ? response.logs : prev => [...prev, ...response.logs])
    setHasMore(response.hasMore)
    setOffset(nextOffset)
  }

  useEffect(() => {
    fetchLogs(0)
  }, [restaurantId])

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
        <Space>
          <Button onClick={() => fetchLogs(0)} loading={loading}>
            {t('ZELTY_LOGS_REFRESH')}
          </Button>
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
          <Button onClick={() => fetchLogs(offset + PAGE_SIZE)} loading={loading}>
            {t('ZELTY_LOGS_LOAD_MORE')}
          </Button>
        )}
      </Space>
    </AntdConfigProvider>
  )
}
