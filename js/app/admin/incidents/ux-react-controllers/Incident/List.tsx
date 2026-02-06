import React, { useMemo, useState } from 'react';
import { Table, Tag, Avatar, Row, Col, Badge, Tooltip } from 'antd';
import IncidentItem from './IncidentItem';
import { useTranslation } from 'react-i18next';
import { moment } from '../../../../../shared';
import {
  useGetIncidentsQuery,
  useGetIncidentFiltersQuery,
} from '../../../../api/slice';
import { useTableFilters } from '../../../../hooks/useTableFilters';
import { toFilterOptions } from '../../../../utils/filter';
import { storeToIri, restaurantToIri, userToIri } from '../../../../utils/iri';
import { connectWithRedux } from './store';

function _showPriority(priority: number) {
  switch (priority) {
    case 1:
      return { text: 'HIGH', status: 'error' };
    case 2:
      return { text: 'MEDIUM', status: 'warning' };
    case 3:
      return { text: 'LOW', status: 'success' };
  }
}

function _statusCtx({
  store,
  order: { restaurant },
}: {
  store: { id: number; name: string };
  order: { restaurant: { id: number; name: string } };
}) {
  if (store.id) {
    return (
      <div>
        <i className="fa fa-truck mr-2" aria-hidden="true"></i>
        {store.name}
      </div>
    );
  }

  if (restaurant.id) {
    return (
      <div>
        <i className="fa fa-cutlery mr-2" aria-hidden="true"></i>
        {restaurant.name}
      </div>
    );
  }
}

function List() {
  const { t } = useTranslation();
  const [pageSize, setPageSize] = useState(10);

  const { data: filtersData } = useGetIncidentFiltersQuery();

  const { searchParams, onChange, page, setPage } = useTableFilters({
    single: ['status', 'priority'],
    iriMappings: [
      {
        columnKey: 'context',
        mappings: [
          { iriPrefix: '/api/stores/', paramKey: 'store' },
          { iriPrefix: '/api/restaurants/', paramKey: 'restaurant' },
        ],
      },
    ],
    multiple: [
      { columnKey: 'customer', paramKey: 'customer' },
      { columnKey: 'createdBy', paramKey: 'createdBy' },
    ],
  });

  const params = searchParams ? [searchParams] : [];

  const { data: incidentsData, isFetching } = useGetIncidentsQuery({
    page,
    pageSize,
    params,
  });
  const incidents = incidentsData?.['hydra:member'];

  const storeFilters = useMemo(
    () =>
      toFilterOptions(
        filtersData?.stores || [],
        store => store.name || '',
        storeToIri,
      ),
    [filtersData],
  );

  const restaurantFilters = useMemo(
    () =>
      toFilterOptions(
        filtersData?.restaurants || [],
        restaurant => restaurant.name || '',
        restaurantToIri,
      ),
    [filtersData],
  );

  const contextFilters = useMemo(
    () => [
      { text: t('STORE'), value: 'store', children: storeFilters },
      {
        text: t('RESTAURANT'),
        value: 'restaurant',
        children: restaurantFilters,
      },
    ],
    [storeFilters, restaurantFilters, t],
  );

  const customerFilters = useMemo(
    () =>
      toFilterOptions(
        filtersData?.customers || [],
        customer => customer.username || '',
        userToIri,
      ),
    [filtersData],
  );

  const authorFilters = useMemo(
    () =>
      toFilterOptions(
        filtersData?.authors || [],
        author => author.username || '',
        userToIri,
      ),
    [filtersData],
  );

  const columns = [
    {
      title: t('TITLE'),
      dataIndex: 'title',
      key: 'title',
    },
    {
      title: t('PRIORITY'),
      dataIndex: 'priority',
      key: 'priority',
      filters: [
        { text: t('LOW'), value: 'LOW' },
        { text: t('MEDIUM'), value: 'MEDIUM' },
        { text: t('HIGH'), value: 'HIGH' },
      ],
      sorter: true,
      render: (priority: number) => {
        const { text, status } = _showPriority(priority);
        return <Badge status={status} text={t(text)} />;
      },
    },
    {
      title: t('STATUS'),
      dataIndex: 'status',
      key: 'status',
      filters: [
        { text: t('OPEN'), value: 'OPEN' },
        { text: t('CLOSED'), value: 'CLOSED' },
      ],
      render: (text: string) => (
        <Tag color={text === 'OPEN' ? 'green' : 'red'}>{text}</Tag>
      ),
    },
    {
      title: t('STORE'),
      key: 'context',
      filters: contextFilters,
      filterSearch: true,
      filterMode: 'tree' as const,
      render: _statusCtx,
    },
    {
      title: t('CUSTOMER'),
      dataIndex: ['order', 'customer', 'username'],
      filters: customerFilters,
      filterSearch: true,
      key: 'customer',
    },
    {
      title: t('REPORTED_BY'),
      dataIndex: 'author',
      key: 'createdBy',
      filters: authorFilters,
      filterSearch: true,
      render: ({ username }: { username: string }) => (
        <>
          <Avatar
            size="small"
            className="mr-2"
            src={window.Routing.generate('user_avatar', { username })}
          />
          {username}
        </>
      ),
    },
    {
      title: t('REPORTED_AT'),
      dataIndex: 'createdAt',
      key: 'createdAt',
      sorter: true,
      render: (createdAt: string) => {
        const date = moment(createdAt);
        return <Tooltip title={date.fromNow()}>{date.format('LLL')}</Tooltip>;
      },
    },
    {
      title: t('ACTION'),
      dataIndex: 'id',
      key: 'action',
      render: (id: number) => (
        <a href={window.Routing.generate('admin_incident', { id })}>
          {t('VIEW')}
        </a>
      ),
    },
  ];

  return (
    <Table
      columns={columns}
      loading={isFetching}
      dataSource={incidents}
      onChange={onChange}
      pagination={{
        current: page,
        pageSize,
        total: incidentsData?.['hydra:totalItems'] || 0,
        onChange: (newPage: number, newPageSize: number) => {
          setPage(newPage);
          setPageSize(newPageSize);
        },
        showSizeChanger: true,
      }}
      expandedRowRender={record => (
        <Row gutter={[16, 16]}>
          <Col span={18}>
            <p>{record.description}</p>
          </Col>
          <Col span={6}>
            <IncidentItem task={record.task} />
          </Col>
        </Row>
      )}
      rowKey="id"
    />
  );
}

export default connectWithRedux(List);
