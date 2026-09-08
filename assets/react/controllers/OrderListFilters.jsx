import React, { useMemo } from 'react'
import { useTranslation } from 'react-i18next'

import { AntdConfigProvider } from '../../../js/app/utils/antd'
import SearchQueryBar from '../../../js/app/components/SearchQueryBar/SearchQueryBar'

const httpClient = window._auth ? new window._auth.httpClient() : null;

const states = ['new', 'accepted', 'fulfilled', 'refused', 'cancelled'];

export default function OrderListFilters({ defaultValue = '' }) {

  const { t } = useTranslation();

  const fields = useMemo(() => [
    {
      key: 'number',
      label: t('ORDER_LIST_NUMBER'),
    },
    {
      key: 'customer',
      label: t('ORDER_LIST_CUSTOMER'),
    },
    {
      key: 'date',
      label: t('DATE'),
      type: 'date',
    },
    {
      key: 'state',
      label: t('ORDER_LIST_STATE'),
      type: 'enum',
      options: states.map(s => ({ value: s, label: t(s) })),
    },
    {
      key: 'owner',
      label: t('OWNER'),
      type: 'async',
      loadOptions: async (input) => {
        if (!input) {
          return []
        }
        const { response } = await httpClient.get(`//${window.location.host}/search-query/orders/autocomplete:owner?q=${encodeURIComponent(input)}`);
        // De-duplicate names (a restaurant and a store may share a name).
        const seen = new Set()
        return (response.hits || [])
          .filter(hit => (seen.has(hit.label) ? false : seen.add(hit.label)))
          .map(hit => ({ value: hit.label, label: hit.label }));
      },
    },
  ], [t]);

  return (
    <AntdConfigProvider>
      <SearchQueryBar
        fields={fields}
        defaultValue={defaultValue}
        placeholder={t('SEARCH_QUERY_BAR_ORDERS_PLACEHOLDER')}
        onSearch={(query) => {
          const url = new URL(window.location.href);
          if (query) {
            url.searchParams.set('q', query);
          } else {
            url.searchParams.delete('q');
          }
          url.searchParams.delete('page');
          window.location.href = url.toString();
        }}
      />
    </AntdConfigProvider>
  )
}
