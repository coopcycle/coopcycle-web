import { createApi } from '@reduxjs/toolkit/query/react'
import { baseQueryWithReauth } from './baseQuery'

// Define our single API slice object
export const apiSlice = createApi({
  reducerPath: 'api',
  baseQuery: baseQueryWithReauth,
  // The "endpoints" represent operations and requests for this server
  // nodeId is passed in JSON-LD '@id' key, https://www.w3.org/TR/2014/REC-json-ld-20140116/#node-identifiers
  endpoints: builder => ({
    getTaxRates: builder.query({
      query: () => `api/tax_rates`,
    }),
    getTags: builder.query({
      query: () => `api/tags`,
    }),

    getOrderTiming: builder.query({
      query: nodeId => `${nodeId}/timing`,
    }),
    getOrderValidate: builder.query({
      query: nodeId => `${nodeId}/validate`,
    }),
    getOrder: builder.query({
      query: nodeId => nodeId,
    }),
    updateOrder: builder.mutation({
      query: ({ nodeId, ...patch }) => ({
        url: nodeId,
        method: 'PUT',
        body: patch,
      }),
    }),

    getTimeSlots: builder.query({
      query: () => `api/time_slots`,
    }),

    patchAddress: builder.mutation({
      query({ nodeId, ...patch }) {
        return {
          url: nodeId,
          method: 'PATCH',
          body: patch,
        }
      },
    }),

    getStore: builder.query({
      query: nodeId => nodeId,
    }),
    getStoreAddresses: builder.query({
      query: storeNodeId => `${storeNodeId}/addresses`,
    }),
    getStoreTimeSlots: builder.query({
      query: storeNodeId => `${storeNodeId}/time_slots`,
    }),
    getStorePackages: builder.query({
      query: storeNodeId => `${storeNodeId}/packages`,
    }),
    postStoreAddress: builder.mutation({
      query({ storeNodeId, ...body }) {
        return {
          url: `${storeNodeId}/addresses`,
          method: 'POST',
          body,
        }
      },
    }),

    calculatePrice: builder.mutation({
      query(body) {
        return {
          url: `/api/retail_prices/calculate`,
          method: 'POST',
          body,
        }
      },
    }),
    postDelivery: builder.mutation({
      query(body) {
        return {
          url: `/api/deliveries`,
          method: 'POST',
          body,
        }
      },
    }),
    putDelivery: builder.mutation({
      query({ nodeId, ...body }) {
        return {
          url: nodeId,
          method: 'PUT',
          body,
        }
      },
    }),

    recurrenceRulesGenerateOrders: builder.mutation({
      query: date => ({
        url: 'api/recurrence_rules/generate_orders',
        params: {
          date: date.format('YYYY-MM-DD'),
        },
      }),
    }),

    getInvoiceLineItemsGroupedByOrganization: builder.query({
      query: args => {
        return {
          url: `api/invoice_line_items/grouped_by_organization?${args.params.join(
            '&',
          )}`,
          params: {
            page: args.page,
            itemsPerPage: args.pageSize,
          },
        }
      },
    }),
    getInvoiceLineItems: builder.query({
      query: args => {
        return {
          url: `api/invoice_line_items?${args.params.join('&')}`,
          params: {
            page: args.page,
            itemsPerPage: args.pageSize,
          },
        }
      },
    }),
  }),
})

// Export the auto-generated hook for the query endpoints
export const {
  useGetTaxRatesQuery,
  useGetTagsQuery,
  useGetOrderTimingQuery,
  useGetOrderQuery,
  useUpdateOrderMutation,
  useGetTimeSlotsQuery,
  useGetStoreQuery,
  useGetStoreAddressesQuery,
  useGetStoreTimeSlotsQuery,
  useGetStorePackagesQuery,
  usePostStoreAddressMutation,
  usePatchAddressMutation,
  useCalculatePriceMutation,
  usePostDeliveryMutation,
  usePutDeliveryMutation,
  useRecurrenceRulesGenerateOrdersMutation,
  useLazyGetInvoiceLineItemsGroupedByOrganizationQuery,
  useGetInvoiceLineItemsQuery,
} = apiSlice
