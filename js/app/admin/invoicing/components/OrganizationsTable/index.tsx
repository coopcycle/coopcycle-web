import React, { useCallback, useEffect, useMemo, useState } from 'react'
import { Table, TableColumnsType } from 'antd'
import { useTranslation } from 'react-i18next'
import { Moment } from 'moment'

import { money } from '../../../../utils/format'
import { useLazyGetInvoiceLineItemsGroupedByOrganizationQuery } from '../../../../api/slice'
import { prepareParams } from '../../redux/actions'
import { usePrevious } from '../../../../dashboard/redux/utils'
import OrdersTable from '../OrdersTable'
import type { InvoiceLineItemGroupedByOrganization } from '../../../../api/types'

type OrganizationRow = {
  rowKey: string
  storeId: string
  name: string
  subTotal: string
  tax: string
  total: string
}

type Props = {
  ordersStates: string[]
  dateRange: Moment[]
  onlyNotInvoiced: boolean
  reloadKey: number
  setSelectedStoreIds: (storeIds: string[]) => void
}

export default function OrganizationsTable({
  ordersStates,
  dateRange,
  onlyNotInvoiced,
  reloadKey,
  setSelectedStoreIds,
}: Props) {
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

  const { dataSource, total } = useMemo((): {
    dataSource: OrganizationRow[] | undefined
    total: number
  } => {
    if (!data) {
      return { dataSource: undefined, total: 0 }
    }

    return {
      dataSource: data['hydra:member'].map(
        (item: InvoiceLineItemGroupedByOrganization): OrganizationRow => ({
          rowKey: item.storeId.toString(),
          storeId: item.storeId.toString(),
          name: `${item.organizationLegalName} (${item.ordersCount})`,
          subTotal: money(item.subTotal),
          tax: money(item.tax),
          total: money(item.total),
        }),
      ),
      total: data['hydra:totalItems'],
    }
  }, [data])

  const columns: TableColumnsType<OrganizationRow> = [
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
    (page: number, pageSize: number) => {
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
        expandedRowRender: (record: OrganizationRow) => {
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
        onChange: (_: React.Key[], selectedRows: OrganizationRow[]) => {
          setSelectedStoreIds(selectedRows.map(row => row.storeId))
        },
      }}
    />
  )
}
