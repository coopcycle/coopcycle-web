import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Table, TableColumnsType } from 'antd';
import { useTranslation } from 'react-i18next';
import { Moment } from 'moment';

import { money } from '../../../../utils/format';
import { useLazyGetInvoiceLineItemsGroupedByOrganizationQuery } from '../../../../api/slice';
import { prepareParams, SettlementFilter } from '../../redux/actions';
import { usePrevious } from '../../../../dashboard/redux/utils';
import OrdersTable from '../OrdersTable';
import type { InvoiceLineItemGroupedByOrganization } from '../../../../api/types';

type OrganizationRow = {
  rowKey: string;
  organizationId: string;
  name: string;
  // Raw store name, shown as muted subtext when it differs from the
  // displayed name (i.e. when a distinct legalName is set)
  storeName: string | null;
  subTotal: string;
  tax: string;
  total: string;
};

type Props = {
  ordersStates: string[];
  dateRange: Moment[] | null;
  onlyNotInvoiced: boolean;
  settlement: SettlementFilter;
  reloadKey: number;
  setSelectedOrganizationIds: (organizationIds: string[]) => void;
};

export default function OrganizationsTable({
  ordersStates,
  dateRange,
  onlyNotInvoiced,
  settlement,
  reloadKey,
  setSelectedOrganizationIds,
}: Props) {
  const [currentPage, setCurrentPage] = useState(1);
  const [pageSize, setPageSize] = useState(10);

  const [trigger, { isFetching, data }] =
    useLazyGetInvoiceLineItemsGroupedByOrganizationQuery();

  const previousReloadKey = usePrevious(reloadKey);

  const { t } = useTranslation();

  const params = useMemo(() => {
    if (!dateRange) {
      return null;
    }

    return prepareParams({
      dateRange: [
        dateRange[0].format('YYYY-MM-DD'),
        dateRange[1].format('YYYY-MM-DD'),
      ],
      state: ordersStates,
      onlyNotInvoiced: onlyNotInvoiced,
      settlement: settlement,
    });
  }, [ordersStates, dateRange, onlyNotInvoiced, settlement]);

  const { dataSource, total } = useMemo((): {
    dataSource: OrganizationRow[] | undefined;
    total: number;
  } => {
    if (!data) {
      return { dataSource: undefined, total: 0 };
    }

    return {
      dataSource: data['hydra:member'].map(
        (item: InvoiceLineItemGroupedByOrganization): OrganizationRow => ({
          rowKey: item.organizationId.toString(),
          organizationId: item.organizationId.toString(),
          name: `${item.organizationLegalName} (${item.ordersCount})`,
          storeName:
            item.storeName !== item.organizationLegalName
              ? item.storeName
              : null,
          subTotal: money(item.subTotal),
          tax: money(item.tax),
          total: money(item.total),
        }),
      ),
      total: data['hydra:totalItems'],
    };
  }, [data]);

  const columns: TableColumnsType<OrganizationRow> = [
    {
      title: t('ADMIN_ORDERS_TO_INVOICE_ORGANIZATION_LABEL'),
      dataIndex: 'name',
      key: 'name',
      render: (name: string, record: OrganizationRow) => (
        <div>
          <div>{name}</div>
          {record.storeName && (
            <small className="text-muted">{record.storeName}</small>
          )}
        </div>
      ),
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
  ];

  const reloadData = useCallback(
    (page: number, pageSize: number) => {
      if (!params) {
        return;
      }

      trigger({
        params,
        page: page,
        pageSize: pageSize,
      });
    },
    [params, trigger],
  );

  useEffect(() => {
    if (reloadKey === previousReloadKey) {
      return;
    }

    reloadData(currentPage, pageSize);
  }, [reloadKey, previousReloadKey, currentPage, pageSize, reloadData]);

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
        if (!pagination.current) {
          return;
        }
        if (!pagination.pageSize) {
          return;
        }

        reloadData(pagination.current, pagination.pageSize);

        setCurrentPage(pagination.current);
        setPageSize(pagination.pageSize);
      }}
      expandable={{
        expandedRowRender: (record: OrganizationRow) => {
          return (
            <OrdersTable
              ordersStates={ordersStates}
              dateRange={dateRange}
              onlyNotInvoiced={onlyNotInvoiced}
              settlement={settlement}
              organizationId={record.organizationId}
              reloadKey={reloadKey}
            />
          );
        },
      }}
      rowSelection={{
        type: 'checkbox',
        onChange: (_: React.Key[], selectedRows: OrganizationRow[]) => {
          setSelectedOrganizationIds(selectedRows.map(row => row.organizationId));
        },
      }}
    />
  );
}
