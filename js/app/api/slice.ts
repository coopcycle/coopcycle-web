import { createApi } from '@reduxjs/toolkit/query/react'
import { baseQueryWithReauth } from './baseQuery'
import { fetchAllRecordsUsingFetchWithBQ } from './utils'
import {
  GetInvoiceLineItemsGroupedByOrganizationArgs,
  GetInvoiceLineItemsArgs,
  HydraCollection,
  InvoiceLineItemGroupedByOrganization,
  InvoiceLineItem,
  TaxRate,
  Tag,
  Zone,
  Package,
  TimeSlot,
  TimeSlotChoice,
  Order,
  OrderTiming,
  OrderValidation,
  Store,
  Address,
  PricingRuleSet,
  Delivery,
  RecurrenceRule,
  UpdateOrderRequest,
  PatchAddressRequest,
  PostStoreAddressRequest,
  CalculatePriceRequest,
  CalculationOutput,
  SuggestOptimizationsRequest,
  OptimizationSuggestions,
  PostDeliveryRequest,
  PutDeliveryRequest,
  PutRecurrenceRuleRequest,
  RecurrenceRulesGenerateOrdersRequest,
  CreatePricingRuleSetRequest,
  UpdatePricingRuleSetRequest,
} from './types'

// Define our single API slice object
export const apiSlice = createApi({
  reducerPath: 'api',
  baseQuery: baseQueryWithReauth,
  tagTypes: ['PricingRuleSet'],
  // The "endpoints" represent operations and requests for this server
  // nodeId is passed in JSON-LD '@id' key, https://www.w3.org/TR/2014/REC-json-ld-20140116/#node-identifiers
  endpoints: builder => ({
    getTaxRates: builder.query<HydraCollection<TaxRate>, void>({
      query: () => `api/tax_rates`,
    }),
    getTags: builder.query<HydraCollection<Tag>, void>({
      queryFn: async (args, queryApi, extraOptions, baseQuery) => {
        return await fetchAllRecordsUsingFetchWithBQ(baseQuery, 'api/tags', 100)
      },
    }),
    getZones: builder.query<HydraCollection<Zone>, void>({
      queryFn: async (args, queryApi, extraOptions, baseQuery) => {
        return await fetchAllRecordsUsingFetchWithBQ(
          baseQuery,
          'api/zones',
          100,
        )
      },
    }),
    getPackages: builder.query<HydraCollection<Package>, void>({
      queryFn: async (args, queryApi, extraOptions, baseQuery) => {
        return await fetchAllRecordsUsingFetchWithBQ(
          baseQuery,
          'api/packages',
          100,
        )
      },
    }),

    getOrderTiming: builder.query<OrderTiming, string>({
      query: (nodeId: string) => `${nodeId}/timing`,
    }),
    getOrderValidate: builder.query<OrderValidation, string>({
      query: (nodeId: string) => `${nodeId}/validate`,
    }),
    getOrder: builder.query<Order, string>({
      query: (nodeId: string) => nodeId,
    }),
    updateOrder: builder.mutation<Order, UpdateOrderRequest>({
      query: ({ nodeId, ...patch }) => ({
        url: nodeId,
        method: 'PUT',
        body: patch,
      }),
    }),

    getTimeSlots: builder.query<HydraCollection<TimeSlot>, void>({
      queryFn: async (args, queryApi, extraOptions, baseQuery) => {
        return await fetchAllRecordsUsingFetchWithBQ(
          baseQuery,
          'api/time_slots',
          100,
        )
      },
    }),
    getTimeSlotChoices: builder.query<HydraCollection<TimeSlotChoice>, string>({
      query: (nodeId: string) => `${nodeId}/choices`,
    }),

    patchAddress: builder.mutation<Address, PatchAddressRequest>({
      query({ nodeId, ...patch }) {
        return {
          url: nodeId,
          method: 'PATCH',
          body: patch,
        }
      },
    }),

    getStore: builder.query<Store, string>({
      query: (nodeId: string) => nodeId,
    }),
    getStoreAddresses: builder.query<HydraCollection<Address>, string>({
      queryFn: async (args, queryApi, extraOptions, baseQuery) => {
        return await fetchAllRecordsUsingFetchWithBQ(
          baseQuery,
          `${args}/addresses`,
          100,
        )
      },
    }),
    getStoreTimeSlots: builder.query<HydraCollection<TimeSlot>, string>({
      queryFn: async (args, queryApi, extraOptions, baseQuery) => {
        return await fetchAllRecordsUsingFetchWithBQ(
          baseQuery,
          `${args}/time_slots`,
          100,
        )
      },
    }),
    getStorePackages: builder.query<HydraCollection<Package>, string>({
      queryFn: async (args, queryApi, extraOptions, baseQuery) => {
        return await fetchAllRecordsUsingFetchWithBQ(
          baseQuery,
          `${args}/packages`,
          100,
        )
      },
    }),
    postStoreAddress: builder.mutation<Address, PostStoreAddressRequest>({
      query({ storeNodeId, ...body }) {
        return {
          url: `${storeNodeId}/addresses`,
          method: 'POST',
          body,
        }
      },
    }),

    calculatePrice: builder.mutation<CalculationOutput, CalculatePriceRequest>({
      query(body) {
        return {
          url: `/api/retail_prices/calculate`,
          method: 'POST',
          body,
        }
      },
    }),
    suggestOptimizations: builder.mutation<
      OptimizationSuggestions,
      SuggestOptimizationsRequest
    >({
      query(body) {
        return {
          url: `/api/deliveries/suggest_optimizations`,
          method: 'POST',
          body,
        }
      },
    }),
    postDelivery: builder.mutation<Delivery, PostDeliveryRequest>({
      query(body) {
        return {
          url: `/api/deliveries`,
          method: 'POST',
          body,
        }
      },
    }),
    putDelivery: builder.mutation<Delivery, PutDeliveryRequest>({
      query({ nodeId, ...body }) {
        return {
          url: nodeId,
          method: 'PUT',
          body,
        }
      },
    }),

    putRecurrenceRule: builder.mutation<
      RecurrenceRule,
      PutRecurrenceRuleRequest
    >({
      query({ nodeId, ...body }) {
        return {
          url: nodeId,
          method: 'PUT',
          body,
        }
      },
    }),
    deleteRecurrenceRule: builder.mutation<void, string>({
      query: nodeId => ({
        url: nodeId,
        method: 'DELETE',
      }),
    }),
    recurrenceRulesGenerateOrders: builder.mutation<
      any,
      RecurrenceRulesGenerateOrdersRequest
    >({
      query: date => ({
        url: 'api/recurrence_rules/generate_orders',
        params: {
          date: date.format('YYYY-MM-DD'),
        },
      }),
    }),

    getInvoiceLineItemsGroupedByOrganization: builder.query<
      HydraCollection<InvoiceLineItemGroupedByOrganization>,
      GetInvoiceLineItemsGroupedByOrganizationArgs
    >({
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
    getInvoiceLineItems: builder.query<
      HydraCollection<InvoiceLineItem>,
      GetInvoiceLineItemsArgs
    >({
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

    getPricingRuleSets: builder.query<HydraCollection<PricingRuleSet>, void>({
      query: () => 'api/pricing_rule_sets',
      providesTags: ['PricingRuleSet'],
    }),
    getPricingRuleSet: builder.query<PricingRuleSet, number>({
      query: id => `api/pricing_rule_sets/${id}`,
      providesTags: (result, error, id) => [{ type: 'PricingRuleSet', id }],
    }),
    createPricingRuleSet: builder.mutation<
      PricingRuleSet,
      CreatePricingRuleSetRequest
    >({
      query: newPricingRuleSet => ({
        url: 'api/pricing_rule_sets',
        method: 'POST',
        body: newPricingRuleSet,
      }),
      invalidatesTags: ['PricingRuleSet'],
    }),
    updatePricingRuleSet: builder.mutation<
      PricingRuleSet,
      UpdatePricingRuleSetRequest
    >({
      query: ({ id, ...patch }) => ({
        url: `api/pricing_rule_sets/${id}`,
        method: 'PUT',
        body: patch,
      }),
      invalidatesTags: (result, error, { id }) => [
        { type: 'PricingRuleSet', id },
      ],
    }),
    deletePricingRuleSet: builder.mutation<void, number>({
      query: id => ({
        url: `api/pricing_rule_sets/${id}`,
        method: 'DELETE',
      }),
      invalidatesTags: ['PricingRuleSet'],
    }),
  }),
})

// Export the auto-generated hook for the query endpoints
export const {
  useGetTaxRatesQuery,
  useGetTagsQuery,
  useGetZonesQuery,
  useGetPackagesQuery,
  useGetOrderTimingQuery,
  useGetOrderQuery,
  useUpdateOrderMutation,
  useGetTimeSlotsQuery,
  useGetTimeSlotChoicesQuery,
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
  useGetPricingRuleSetsQuery,
  useGetPricingRuleSetQuery,
  useCreatePricingRuleSetMutation,
  useUpdatePricingRuleSetMutation,
  useDeletePricingRuleSetMutation,
} = apiSlice
