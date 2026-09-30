import React, { useMemo } from 'react'
import { useTranslation } from 'react-i18next'

import { AntdConfigProvider } from '../../../js/app/utils/antd'
import SearchQueryBar from '../../../js/app/components/SearchQueryBar/SearchQueryBar'

const httpClient = window._auth ? new window._auth.httpClient() : null;

const states = ['new', 'accepted', 'fulfilled', 'refused', 'cancelled'];

// Backs the "async" search bar fields - one endpoint per field under
// AppBundle\SearchQuery\OrdersAutocompleteController.
async function fetchOrdersAutocomplete(field, input) {
  const { response } = await httpClient.get(`//${window.location.host}/search-query/orders/autocomplete:${field}?q=${encodeURIComponent(input)}`);
  return response.hits || [];
}

export default function OrderListFilters({ defaultValue = '' }) {

  const { t } = useTranslation();

  const fields = useMemo(() => [
    {
      key: 'number',
      label: t('ORDER_LIST_NUMBER'),
      type: 'async',
      loadOptions: async (input) => {
        if (!input) {
          return []
        }
        const hits = await fetchOrdersAutocomplete('number', input);
        // An order number on its own says nothing - show what identifies the
        // order underneath it (see OrdersAutocompleteController::number).
        return hits.map(hit => ({
          value: hit.value,
          label: hit.label,
          description: [
            hit.date ? new Date(hit.date).toLocaleDateString() : null,
            hit.owner,
            hit.customer,
          ].filter(Boolean).join(' · '),
        }));
      },
    },
    {
      key: 'customer',
      label: t('ORDER_LIST_CUSTOMER'),
      type: 'async',
      // Matches on email, full name or phone number - the first two
      // fuzzily, the last one exactly.
      loadOptions: async (input) => {
        if (!input) {
          return []
        }
        const hits = await fetchOrdersAutocomplete('customer', input);
        // A hit may have been found by phone number rather than by the
        // name/email its label shows - surface the number underneath, so
        // it's clear why the customer is in the list (see
        // OrdersAutocompleteController::customer).
        return hits.map(hit => ({
          value: hit.value,
          label: hit.label,
          description: hit.telephone,
        }));
      },
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
      multi: true,
      loadOptions: async (input) => {
        if (!input) {
          return []
        }
        const hits = await fetchOrdersAutocomplete('owner', input);
        // De-duplicate names (a restaurant and a store may share a name).
        // The value used is the name itself, not the hit's IRI - that's
        // what the "owner" filter resolves against server-side.
        const seen = new Set()
        return hits
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
        scope="orders"
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
