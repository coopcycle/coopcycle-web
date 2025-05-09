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
        }
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
    getStore: builder.query({
      query: (nodeId) => nodeId,
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
      query(data) {
        const { nodeId, ...body } = data
        return {
          url: nodeId,
          method: 'PUT',
          body,
        }
      },
    }),
  }),
})

// Export the auto-generated hook for the query endpoints
export const {
  useRecurrenceRulesGenerateOrdersMutation,
  useLazyGetInvoiceLineItemsGroupedByOrganizationQuery,
  useGetInvoiceLineItemsQuery,
  useGetOrderTimingQuery,
  useGetOrderQuery,
  useUpdateOrderMutation,
  useGetTimeSlotsQuery,
  useGetStoreQuery,
  useLazyGetStoreQuery,
  usePostDeliveryMutation,
  usePutDeliveryMutation,
} = apiSlice
