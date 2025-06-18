import { createApi } from '@reduxjs/toolkit/query/react'
import { baseQueryWithReauth } from './baseQuery'
import { fetchAllRecordsUsingFetchWithBQ } from './utils'

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
      queryFn: async (args, queryApi, extraOptions, baseQuery) => {
        return await fetchAllRecordsUsingFetchWithBQ(baseQuery, 'api/tags', 100)
      },
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
      queryFn: async (args, queryApi, extraOptions, baseQuery) => {
        return await fetchAllRecordsUsingFetchWithBQ(
          baseQuery,
          'api/time_slots',
          100,
        )
      },
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
      queryFn: async (args, queryApi, extraOptions, baseQuery) => {
        return await fetchAllRecordsUsingFetchWithBQ(
          baseQuery,
          `${args}/addresses`,
          100,
        )
      },
    }),
    getStoreTimeSlots: builder.query({
      queryFn: async (args, queryApi, extraOptions, baseQuery) => {
        return await fetchAllRecordsUsingFetchWithBQ(
          baseQuery,
          `${args}/time_slots`,
          100,
        )
      },
    }),
    getStorePackages: builder.query({
      queryFn: async (args, queryApi, extraOptions, baseQuery) => {
        return await fetchAllRecordsUsingFetchWithBQ(
          baseQuery,
          `${args}/packages`,
          100,
        )
      },
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
    suggestOptimizations: builder.mutation({
      query(body) {
        return {
          url: `/api/deliveries/suggest_optimizations`,
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

    putRecurrenceRule: builder.mutation({
      query({ nodeId, ...body }) {
        return {
          url: nodeId,
          method: 'PUT',
          body,
        }
      },
    }),
    deleteRecurrenceRule: builder.mutation({
      query: nodeId => ({
        url: nodeId,
        method: 'DELETE',
      }),
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
  useSuggestOptimizationsMutation,
  usePostDeliveryMutation,
  usePutDeliveryMutation,
  usePutRecurrenceRuleMutation,
  useDeleteRecurrenceRuleMutation,
  useRecurrenceRulesGenerateOrdersMutation,
  useLazyGetInvoiceLineItemsGroupedByOrganizationQuery,
  useGetInvoiceLineItemsQuery,
} = apiSlice
