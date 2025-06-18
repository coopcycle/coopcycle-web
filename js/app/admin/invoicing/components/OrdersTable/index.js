import React, { useEffect, useMemo, useState } from 'react'
import { Table, Tag, Tooltip } from 'antd'
import { useTranslation } from 'react-i18next'
import moment from 'moment'

import { money } from '../../../../utils/format'
import { useGetInvoiceLineItemsQuery } from '../../../../api/slice'
import { prepareParams } from '../../redux/actions'
import { usePrevious } from '../../../../dashboard/redux/utils'

export default function OrdersTable({
  ordersStates,
  dateRange,
  onlyNotInvoiced,
  storeId,
  reloadKey,
}) {
  const [currentPage, setCurrentPage] = useState(1)
  const [pageSize, setPageSize] = useState(10)

  const previousReloadKey = usePrevious(reloadKey)

  const params = useMemo(() => {
    if (!storeId) {
      return null
    }

    if (!dateRange) {
      return null
    }

    return prepareParams({
      store: [storeId],
      dateRange: [
        dateRange[0].format('YYYY-MM-DD'),
        dateRange[1].format('YYYY-MM-DD'),
      ],
      state: ordersStates,
      onlyNotInvoiced: onlyNotInvoiced,
    })
  }, [ordersStates, dateRange, onlyNotInvoiced, storeId])

  const { isFetching, data, refetch } = useGetInvoiceLineItemsQuery({
    params,
    page: currentPage,
    pageSize: pageSize,
  })

  const { t } = useTranslation()

  const { dataSource, total } = useMemo(() => {
    if (!data) {
      return { datasource: undefined, total: 0 }
    }

    return {
      dataSource: data['hydra:member'].map(order => ({
        rowKey: order['@id'],
        orderId: order.orderId,
        fileExports: order.exports,
        number: order.orderNumber,
        date: order.date ? moment(order.date).format('l') : '?',
        description: order.description,
        subTotal: money(order.subTotal),
        tax: money(order.tax),
        total: money(order.total),
      })),
      total: data['hydra:totalItems'],
    }
  }, [data])

  const columns = [
    {
      title: t('ADMIN_ORDERS_TO_INVOICE_ORDER_NUMBER_LABEL'),
      dataIndex: 'number',
      key: 'number',
    },
    {
      title: t('ADMIN_ORDERS_TO_INVOICE_EXPORTS_LABEL'),
      dataIndex: 'exports',
      key: 'exports',
      render: (_, { fileExports }) => {
        if (fileExports.length === 0) {
          return <Tag>{t('ADMIN_ORDERS_TO_INVOICE_NOT_EXPORTED')}</Tag>
        } else {
          return (
            <>
              {t('ADMIN_ORDERS_TO_INVOICE_EXPORTED')}
              {fileExports.map(fileExport => {
                return (
                  <Tooltip
                    key={fileExport.requestId}
                    title={moment(fileExport.createdAt).format('llll')}>
                    <Tag color={'green'}>
                      {fileExport.requestId.substring(0, 7)}
                    </Tag>
                  </Tooltip>
                )
              })}
            </>
          )
        }
      },
    },
    {
      title: t('ADMIN_ORDERS_TO_INVOICE_DATE_LABEL'),
      dataIndex: 'date',
      key: 'date',
    },
    {
      title: t('ADMIN_ORDERS_TO_INVOICE_DESCRIPTION_LABEL'),
      dataIndex: 'description',
      key: 'description',
    },
    {
      title: t('ADMIN_ORDERS_TO_INVOICE_SUB_TOTAL_LABEL'),
      dataIndex: 'subTotal',
      key: 'subTotal',
    },
    {
      title: t('ADMIN_ORDERS_TO_INVOICE_TAXES_LABEL'),
      dataIndex: 'tax',
      key: 'tax',
    },
    {
      title: t('ADMIN_ORDERS_TO_INVOICE_TOTAL_LABEL'),
      dataIndex: 'total',
      key: 'total',
    },
    {
      key: 'action',
      dataIndex: 'orderId',
      align: 'right',
      render: orderId => (
        <a
          href={window.Routing.generate('admin_order', { id: orderId })}
          target="_blank"
          rel="noopener noreferrer">
          {t('VIEW')}
        </a>
      ),
    },
  ]

  useEffect(() => {
    if (reloadKey === previousReloadKey) {
      return
    }

    refetch()
  }, [reloadKey, previousReloadKey, refetch])

  return (
    <div data-testid={`invoicing.orders.${storeId}`}>
      <Table
        style={{ marginTop: '48px' }}
        columns={columns}
        loading={isFetching}
        dataSource={dataSource}
        rowKey="rowKey"
        pagination={{
          pageSize,
          total,
          showSizeChanger: true,
        }}
        onChange={pagination => {
          setCurrentPage(pagination.current)
          setPageSize(pagination.pageSize)
        }}
      />
    </div>
  )
}
