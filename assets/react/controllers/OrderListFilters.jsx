import React, { useState, useEffect, useCallback, useMemo } from 'react'
import { AutoComplete, Checkbox, DatePicker, Divider, Flex, Select, Tag, Button, Typography, Spin } from 'antd';
import qs from 'qs'
import _ from 'lodash'
import moment from 'moment'
import { useTranslation } from 'react-i18next';

import { AntdConfigProvider } from '../../../js/app/utils/antd'

const httpClient = window._auth ? new window._auth.httpClient() : null;

const states = ['new', 'accepted', 'fulfilled', 'refused'];

const tagRender = props => {
  const { label, value, closable, onClose } = props;

  const onPreventMouseDown = event => {
    event.preventDefault();
    event.stopPropagation();
  };

  return (
    <Tag
      onMouseDown={onPreventMouseDown}
      closable={closable}
      onClose={onClose}
      style={{ marginInlineEnd: 4 }}
    >
      {label}
    </Tag>
  );
};

function transformDefaultFilters(filters) {
  if (Array.isArray(filters) && filters.length === 0) {
    return {}
  }

  let newFilters = { ...filters }

  if (filters.owner) {
    newFilters = {
      ...newFilters,
      owner: filters.owner.value
    }
  }

  return newFilters;
}

export default function OrderListFilters({ defaultFilters }) {

  const { t } = useTranslation();
  const [filters, setFilters] = useState(transformDefaultFilters(defaultFilters));
  const [owners, setOwners] = useState([])
  const [ownerValue, setOwnerValue] = useState()
  const [isFetching, setIsFetching] = useState(false)

  useEffect(() => {
    console.log(qs.stringify(filters))
  }, [filters]);

  let datePickerProps = {}
  if (defaultFilters.date) {
    datePickerProps = {
      ...datePickerProps,
      defaultValue: moment(defaultFilters.date)
    }
  }

  const search = useCallback(async (text) => {

    setIsFetching(true);

    const { response } = await httpClient.get(`//${window.location.host}/search/order-owners?q=${text}`);

    setIsFetching(false);
    setOwners(response.hits)

  }, [setOwners]);

  const options = useMemo(() => {
    return states.map(s => ({
      value: s,
      label: t(s)
    }))
  }, [t])

  return (
    <AntdConfigProvider>
      <Flex gap="small" wrap align="center">
        <Typography.Text>{ t('ADMIN_DASHBOARD_NAV_FILTERS') }</Typography.Text>
        <Divider type="vertical" />
        <Typography.Text>{t('DATE')}</Typography.Text>
        <DatePicker
          onChange={(date, dateString) => {
            setFilters({
              ...filters,
              date: dateString
            })
          }}
          {...datePickerProps}
        />
        <Divider type="vertical" />
        <Typography.Text>{t('ORDER_LIST_STATE')}</Typography.Text>
        <Select
          mode="multiple"
          tagRender={tagRender}
          defaultValue={[]}
          style={{ minWidth: '150px' }}
          options={options}
          defaultValue={ defaultFilters.state }
          onChange={(value) => {
            setFilters({
              ...filters,
              state: value
            })
          }}
        />
        <Divider type="vertical" />
        <Typography.Text>{t('OWNER')}</Typography.Text>
        <Select
          showSearch
          onSearch={_.debounce(search, 300)}
          onChange={(value) => {
            setFilters({
              ...filters,
              owner: value
            })
          }}
          defaultValue={defaultFilters.owner}
          allowClear={true}
          defaultActiveFirstOption={false}
          suffixIcon={null}
          filterOption={false}
          notFoundContent={isFetching ? <Spin size="small" /> : t('NO_RESULTS')}
          options={owners}
          style={{ minWidth: '150px' }}
          placeholder={t('ADMIN_DASHBOARD_SEARCH')}
        />
        <Divider type="vertical" />
        <Button
          type="primary"
          disabled={ _.isEmpty(filters) }
          onClick={() => {
            window.location.href = `${window.location.pathname}?${qs.stringify(filters)}`;
          }}>{ t('ADMIN_DASHBOARD_FILTERS_APPLY') }</Button>
        <Button
          disabled={ _.isEmpty(filters) }
          onClick={() => {
            window.location.href = window.location.pathname;
          }}>{ t('ADMIN_DASHBOARD_NAV_FILTERS_CLEAR') }</Button>
      </Flex>
    </AntdConfigProvider>
  )
}
