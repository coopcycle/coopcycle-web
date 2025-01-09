import { Table } from 'antd'
import React, { useCallback, useEffect, useMemo, useState } from 'react'
import { moment } from '../../../../../shared'
import { money } from '../../../../utils/format'
import { useLazyGetInvoiceLineItemsQuery } from '../../../../api/slice'
import { useTranslation } from 'react-i18next'
import { prepareParams } from '../../redux/actions'
import { usePrevious } from '../../../../dashboard/redux/utils'

export default function OrdersTable({
  ordersStates,
  dateRange,
  storeId,
  reloadKey,
}) {
  const [currentPage, setCurrentPage] = useState(1)
  const [pageSize, setPageSize] = useState(10)

  const [trigger, { isFetching, data }] = useLazyGetInvoiceLineItemsQuery()

  const previousReloadKey = usePrevious(reloadKey)

  const { t } = useTranslation()

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
    })
  }, [ordersStates, dateRange, storeId])

  const { dataSource, total } = useMemo(() => {
    if (!data) {
      return { datasource: undefined, total: 0 }
    }

    return {
      dataSource: data['hydra:member'].map(order => ({
        ...order,
        key: order['@id'],
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

  const reloadData = useCallback(
    (page, pageSize) => {
      if (!params) {
        return
      }

      trigger({
        params,
        page: page,
        pageSize: pageSize,
      })
    },
    [params, trigger],
  )

  useEffect(() => {
    if (reloadKey === previousReloadKey) {
      return
    }

    reloadData(currentPage, pageSize)
  }, [reloadKey, previousReloadKey, currentPage, pageSize, reloadData])

  return (
    <Table
      style={{ marginTop: '48px' }}
      columns={columns}
      loading={isFetching}
      dataSource={dataSource}
      rowKey="@id"
      pagination={{
        pageSize,
        total,
      }}
      onChange={pagination => {
        reloadData(pagination.current, pagination.pageSize)

        setCurrentPage(pagination.current)
        setPageSize(pagination.pageSize)
      }}
    />
  )
}
