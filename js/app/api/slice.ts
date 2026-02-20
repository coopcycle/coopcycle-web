import { createApi } from '@reduxjs/toolkit/query/react';
import { baseQueryWithReauth } from './baseQuery';
import { fetchAllRecordsUsingFetchWithBQ } from './utils';
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
  StoreTimeSlot,
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
  RetailPrice,
  SuggestOptimizationsRequest,
  OptimizationSuggestions,
  PostDeliveryRequest,
  PutDeliveryRequest,
  PutRecurrenceRuleRequest,
  RecurrenceRulesGenerateOrdersRequest,
  RecurrenceRulesGenerateOrdersResponse,
  CreatePricingRuleSetRequest,
  UpdatePricingRuleSetRequest,
  TimeSlotChoices,
  TimeSlot,
  Uri,
  Incident,
  User,
  TaskEvent,
  PaymentMethodsOutput,
} from './types';

// Define our single API slice object
export const apiSlice = createApi({
  reducerPath: 'api',
  baseQuery: baseQueryWithReauth,
  tagTypes: ['PricingRuleSet'],
  // The "endpoints" represent operations and requests for this server
  // uri is passed in JSON-LD '@id' key, https://www.w3.org/TR/2014/REC-json-ld-20140116/#node-identifiers
  endpoints: builder => ({
    getTaxRates: builder.query<HydraCollection<TaxRate>, void>({
      query: () => `api/tax_rates`,
    }),
    getTags: builder.query<Tag[], void>({
      queryFn: async (args, queryApi, extraOptions, baseQuery) => {
        return await fetchAllRecordsUsingFetchWithBQ<Tag>(
          baseQuery,
          'api/tags',
          100,
        );
      },
    }),
    getZones: builder.query<Zone[], void>({
      queryFn: async (args, queryApi, extraOptions, baseQuery) => {
        return await fetchAllRecordsUsingFetchWithBQ<Zone>(
          baseQuery,
          'api/zones',
          100,
        );
      },
    }),
    getPackages: builder.query<Package[], void>({
      queryFn: async (args, queryApi, extraOptions, baseQuery) => {
        return await fetchAllRecordsUsingFetchWithBQ<Package>(
          baseQuery,
          'api/packages',
          100,
        );
      },
    }),

    getOrderTiming: builder.query<OrderTiming, string>({
      query: (uri: string) => `${uri}/timing`,
    }),
    getOrderValidate: builder.query<OrderValidation, string>({
      query: (uri: string) => `${uri}/validate`,
    }),
    getOrder: builder.query<Order, string>({
      query: (uri: string) => uri,
    }),
    updateOrder: builder.mutation<Order, UpdateOrderRequest>({
      query: ({ nodeId, ...patch }) => ({
        url: nodeId,
        method: 'PUT',
        body: patch,
      }),
    }),

    getTimeSlots: builder.query<TimeSlot[], void>({
      queryFn: async (args, queryApi, extraOptions, baseQuery) => {
        return await fetchAllRecordsUsingFetchWithBQ<TimeSlot>(
          baseQuery,
          'api/time_slots',
          100,
        );
      },
    }),
    getTimeSlotChoices: builder.query<TimeSlotChoices, string>({
      query: (uri: string) => `${uri}/choices`,
    }),

    patchAddress: builder.mutation<Address, PatchAddressRequest>({
      query({ '@id': uri, ...patch }) {
        return {
          url: uri,
          method: 'PATCH',
          body: patch,
        };
      },
    }),

    getStore: builder.query<Store, Uri>({
      query: (uri: Uri) => uri,
    }),
    getStorePaymentMethods: builder.query<PaymentMethodsOutput, Uri>({
      query: (uri: Uri) => `${uri}/payment_methods`,
    }),
    getStoreAddresses: builder.query<Address[], string>({
      queryFn: async (args, queryApi, extraOptions, baseQuery) => {
        return await fetchAllRecordsUsingFetchWithBQ<Address>(
          baseQuery,
          `${args}/addresses`,
          100,
        );
      },
    }),
    getStoreTimeSlots: builder.query<StoreTimeSlot[], string>({
      queryFn: async (args, queryApi, extraOptions, baseQuery) => {
        return await fetchAllRecordsUsingFetchWithBQ<StoreTimeSlot>(
          baseQuery,
          `${args}/time_slots`,
          100,
        );
      },
    }),
    getStorePackages: builder.query<Package[], string>({
      queryFn: async (args, queryApi, extraOptions, baseQuery) => {
        return await fetchAllRecordsUsingFetchWithBQ<Package>(
          baseQuery,
          `${args}/packages`,
          100,
        );
      },
    }),
    postStoreAddress: builder.mutation<Address, PostStoreAddressRequest>({
      query({ storeUri, ...body }) {
        return {
          url: `${storeUri}/addresses`,
          method: 'POST',
          body,
        };
      },
    }),

    calculatePrice: builder.mutation<RetailPrice, CalculatePriceRequest>({
      query(body) {
        return {
          url: `/api/retail_prices/calculate`,
          method: 'POST',
          body,
        };
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
        };
      },
    }),
    postDelivery: builder.mutation<Delivery, PostDeliveryRequest>({
      query(body) {
        return {
          url: `/api/deliveries`,
          method: 'POST',
          body,
        };
      },
    }),
    putDelivery: builder.mutation<Delivery, PutDeliveryRequest>({
      query({ '@id': uri, ...body }) {
        return {
          url: uri,
          method: 'PUT',
          body,
        };
      },
    }),

    putRecurrenceRule: builder.mutation<
      RecurrenceRule,
      PutRecurrenceRuleRequest
    >({
      query({ '@id': uri, ...body }) {
        return {
          url: uri,
          method: 'PUT',
          body,
        };
      },
    }),
    deleteRecurrenceRule: builder.mutation<void, string>({
      query: uri => ({
        url: uri,
        method: 'DELETE',
      }),
    }),
    recurrenceRulesGenerateOrders: builder.mutation<
      RecurrenceRulesGenerateOrdersResponse,
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
        };
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
        };
      },
    }),

    getPricingRuleSets: builder.query<HydraCollection<PricingRuleSet>, void>({
      query: () => 'api/pricing_rule_sets',
      providesTags: ['PricingRuleSet'],
    }),
    getPricingRuleSet: builder.query<PricingRuleSet, Uri>({
      query: (uri: Uri) => uri,
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
    incidentAction: builder.mutation<
      Incident,
      { incidentId: number; action: string, diff?: number }
    >({
      query: ({ incidentId, ...body }) => ({
        url: `api/incidents/${incidentId}/action`,
        method: 'PUT',
        body: body,
      }),
    }),
    getUser: builder.query<User, Uri>({
      query: (uri: Uri) => uri,
    }),
    getTaskEvents: builder.query<TaskEvent[], string>({
      query: (taskUri: string) => `${taskUri}/events`,
      transformResponse: (response: HydraCollection<TaskEvent>) =>
        response['hydra:member'],
    }),
    getTaskIncidents: builder.query<Incident[], string>({
      query: (taskUri: string) => `${taskUri}/incidents`,
      transformResponse: (response: HydraCollection<Incident>) =>
        response['hydra:member'],
    }),
    // TODO Add types
    updateShopCollection: builder.mutation({
      query({ '@id': uri, ...body }) {
        return {
          url: uri,
          method: 'PUT',
          body,
        };
      },
    }),
    // TODO Add types
    createShopCollection: builder.mutation({
      query(body) {
        return {
          url: `/api/shop_collections`,
          method: 'POST',
          body,
        };
      },
    }),
    deleteShopCollection: builder.mutation<void, string>({
      query: uri => ({
        url: uri,
        method: 'DELETE',
      }),
    }),
  }),
});

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
  useGetStorePaymentMethodsQuery,
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
  useIncidentActionMutation,
  useGetUserQuery,
  useGetTaskEventsQuery,
  useGetTaskIncidentsQuery,
  useUpdateShopCollectionMutation,
  useCreateShopCollectionMutation,
  useDeleteShopCollectionMutation,
} = apiSlice;
