import React, { useCallback, useEffect, useMemo, useState } from 'react'
import { Table } from 'antd'
import { useTranslation } from 'react-i18next'

import { money } from '../../../../utils/format'
import { useLazyGetInvoiceLineItemsGroupedByOrganizationQuery } from '../../../../api/slice'
import { prepareParams } from '../../redux/actions'
import { usePrevious } from '../../../../dashboard/redux/utils'
import OrdersTable from '../OrdersTable'

export default function OrganizationsTable({
  ordersStates,
  dateRange,
  onlyNotInvoiced,
  reloadKey,
  setSelectedStoreIds,
}) {
  const [currentPage, setCurrentPage] = useState(1)
  const [pageSize, setPageSize] = useState(10)

  const [trigger, { isFetching, data }] =
    useLazyGetInvoiceLineItemsGroupedByOrganizationQuery()

  const previousReloadKey = usePrevious(reloadKey)

  const { t } = useTranslation()

  const params = useMemo(() => {
    if (!dateRange) {
      return null
    }

    return prepareParams({
      dateRange: [
        dateRange[0].format('YYYY-MM-DD'),
        dateRange[1].format('YYYY-MM-DD'),
      ],
      state: ordersStates,
      onlyNotInvoiced: onlyNotInvoiced,
    })
  }, [ordersStates, dateRange, onlyNotInvoiced])

  const { dataSource, total } = useMemo(() => {
    if (!data) {
      return { datasource: undefined, total: 0 }
    }

    return {
      dataSource: data['hydra:member'].map(item => ({
        rowKey: item.storeId,
        storeId: item.storeId,
        name: `${item.organizationLegalName} (${item.ordersCount})`,
        subTotal: money(item.subTotal),
        tax: money(item.tax),
        total: money(item.total),
      })),
      total: data['hydra:totalItems'],
    }
  }, [data])

  const columns = [
    {
      title: t('ADMIN_ORDERS_TO_INVOICE_ORGANIZATION_LABEL'),
      dataIndex: 'name',
      key: 'name',
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
      data-testid="invoicing.organizations"
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
        reloadData(pagination.current, pagination.pageSize)

        setCurrentPage(pagination.current)
        setPageSize(pagination.pageSize)
      }}
      expandable={{
        expandedRowRender: record => {
          return (
            <OrdersTable
              ordersStates={ordersStates}
              dateRange={dateRange}
              onlyNotInvoiced={onlyNotInvoiced}
              storeId={record.storeId}
              reloadKey={reloadKey}
            />
          )
        },
      }}
      rowSelection={{
        type: 'checkbox',
        onChange: (selectedRowKeys, selectedRows) => {
          setSelectedStoreIds(selectedRows.map(row => row.storeId))
        },
      }}
    />
  )
}
