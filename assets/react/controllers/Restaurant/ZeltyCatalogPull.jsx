import React, { useState } from 'react'
import { Alert, Button, Input, List, Modal, Popconfirm, Spin, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { AntdConfigProvider } from '../../../../js/app/utils/antd'

const { Text } = Typography

function getHttpClient() {
  return window._auth ? new window._auth.httpClient() : null
}

export default function ZeltyCatalogPull({ catalogsUrl, pullUrlTemplate }) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const [loading, setLoading] = useState(false)
  const [catalogs, setCatalogs] = useState([])
  const [pulling, setPulling] = useState(null)
  const [status, setStatus] = useState(null)
  const [search, setSearch] = useState('')

  const handleOpen = async () => {
    setOpen(true)
    setStatus(null)
    setSearch('')
    setLoading(true)

    const client = getHttpClient()
    if (!client) {
      setLoading(false)
      setStatus({ type: 'error', message: t('ZELTY_CATALOGS_LOAD_ERROR') })
      return
    }

    const { response, error } = await client.get(catalogsUrl)

    setLoading(false)

    if (error || !Array.isArray(response)) {
      setStatus({ type: 'error', message: t('ZELTY_CATALOGS_LOAD_ERROR') })
      return
    }

    setCatalogs(response)
  }

  const handlePull = async catalog => {
    const client = getHttpClient()
    if (!client) return

    setPulling(catalog.id)
    setStatus(null)

    const { error } = await client.post(
      pullUrlTemplate.replace('__CATALOG_ID__', encodeURIComponent(catalog.id)),
      {}
    )

    setPulling(null)

    if (error) {
      setStatus({ type: 'error', message: t('ZELTY_CATALOG_PULL_ERROR') })
      return
    }

    setStatus({ type: 'success', message: t('ZELTY_CATALOG_PULL_QUEUED') })
  }

  const needle = search.trim().toLowerCase()
  const filtered = needle
    ? catalogs.filter(c => (c.name || '').toLowerCase().includes(needle))
    : catalogs

  return (
    <AntdConfigProvider>
      <Button onClick={handleOpen}>{t('ZELTY_CATALOG_PULL_BUTTON')}</Button>
      <Modal
        open={open}
        title={t('ZELTY_CATALOG_PULL_TITLE')}
        onCancel={() => setOpen(false)}
        styles={{ body: { maxHeight: '60vh', overflowY: 'auto' } }}
        footer={
          <Button onClick={() => setOpen(false)}>
            {t('ZELTY_CATALOG_PULL_CLOSE')}
          </Button>
        }>
        {status && (
          <Alert
            type={status.type}
            showIcon
            message={status.message}
            style={{ marginBottom: '1rem' }}
          />
        )}
        {loading ? (
          <Spin />
        ) : (
          <>
          <Input.Search
            allowClear
            placeholder={t('ZELTY_CATALOGS_SEARCH_PLACEHOLDER')}
            value={search}
            onChange={e => setSearch(e.target.value)}
            style={{ marginBottom: '1rem' }}
          />
          <List
            dataSource={filtered}
            locale={{ emptyText: t('ZELTY_CATALOGS_EMPTY') }}
            renderItem={catalog => (
              <List.Item
                actions={[
                  <Popconfirm
                    key="pull"
                    title={t('ZELTY_CATALOG_PULL_CONFIRM', {
                      name: catalog.name,
                    })}
                    okText={t('ZELTY_CATALOG_PULL_ACTION')}
                    cancelText={t('ZELTY_CATALOG_PULL_CANCEL')}
                    onConfirm={() => handlePull(catalog)}>
                    <Button
                      type="primary"
                      loading={pulling === catalog.id}
                      disabled={pulling !== null}>
                      {t('ZELTY_CATALOG_PULL_ACTION')}
                    </Button>
                  </Popconfirm>,
                ]}>
                <List.Item.Meta
                  title={catalog.name}
                  description={
                    catalog.description ? (
                      <Text type="secondary">{catalog.description}</Text>
                    ) : null
                  }
                />
              </List.Item>
            )}
          />
          </>
        )}
      </Modal>
    </AntdConfigProvider>
  )
}
