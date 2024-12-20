import { createApi } from '@reduxjs/toolkit/query/react'
import { baseQueryWithReauth } from './baseQuery'

// Define our single API slice object
export const apiSlice = createApi({
  reducerPath: 'api',
  baseQuery: baseQueryWithReauth,
  // The "endpoints" represent operations and requests for this server
  // nodeId is passed in JSON-LD '@id' key, https://www.w3.org/TR/2014/REC-json-ld-20140116/#node-identifiers
  endpoints: builder => ({
    recurrenceRulesGenerateOrders: builder.mutation({
      query: date => ({
        url: 'api/recurrence_rules/generate_orders',
        params: {
          date: date.format('YYYY-MM-DD'),
        },
        method: 'POST',
        body: {},
      }),
    }),
    getInvoiceLineItems: builder.query({
      query: args => {
        let params = args.store.map(storeId => `store[]=${storeId}`).join('&')

        if (args.state && args.state.length > 0) {
          params +=
            '&' + args.state.map(state => `state[]=${state}`).join('&')
        }

        return {
          url: `api/invoice_line_items?${params}`,
          params: {
            'date[after]': args.dateRange[0],
            'date[before]': args.dateRange[1],
            page: args.page,
            itemsPerPage: args.pageSize,
          },
        }
      },
    }),
    getOrderTiming: builder.query({
      query: nodeId => `${nodeId}/timing`,
    }),
    getOrderValidate: builder.query({
      query: nodeId => `${nodeId}/validate`,
    }),
    updateOrder: builder.mutation({
      query: ({ nodeId, ...patch }) => ({
        url: nodeId,
        method: 'PUT',
        body: patch,
      }),
    }),
  }),
})

// Export the auto-generated hook for the query endpoints
export const {
  useRecurrenceRulesGenerateOrdersMutation,
  useLazyGetInvoiceLineItemsQuery,
  useGetOrderTimingQuery,
  useUpdateOrderMutation,
} = apiSlice
