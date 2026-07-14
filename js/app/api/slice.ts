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
  Shift,
  ShiftPayload,
  PutShiftRequest,
  DateRangeArgs,
  GetHolidayRequestsArgs,
  HolidayRequest,
  PostHolidayRequestRequest,
  CopyWeekRequest,
  PlanningUser,
  ShiftSettings,
  PutShiftSettingsRequest,
  ShiftScheduleSuggestion,
  ShiftBatchResult,
  ShiftDispatchSyncResult,
  ProposedShift,
  BankHolidays,
  ShiftCalendar,
  ShiftCompliance,
  ShiftDashboard,
  GetShiftDashboardArgs,
  Skill,
  SkillWithUsers,
  SkillPayload,
  PutSkillRequest,
  Me,
  SchedulePublication,
} from './types';

// Define our single API slice object
export const apiSlice = createApi({
  reducerPath: 'api',
  baseQuery: baseQueryWithReauth,
  tagTypes: [
    'PricingRuleSet',
    'Shift',
    'HolidayRequest',
    'ShiftSettings',
    'Skill',
    'SchedulePublication',
  ],
  // The "endpoints" represent operations and requests for this server
  // uri is passed in JSON-LD '@id' key, https://www.w3.org/TR/2014/REC-json-ld-20140116/#node-identifiers
  endpoints: builder => ({
    getTaxRates: builder.query<HydraCollection<TaxRate>, void>({
      query: () => ({
        url: `api/tax_rates`,
        headers: { Accept: 'application/ld+json' },
      }),
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
      { incidentId: number; action: string; diff?: number }
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
    getIncidents: builder.query<
      HydraCollection<Incident>,
      { page: number; pageSize: number; params?: string[] }
    >({
      query: ({ page, pageSize, params = [] }) => ({
        url: `api/incidents?${params.join('&')}`,
        params: {
          page,
          itemsPerPage: pageSize,
        },
      }),
    }),
    getIncidentFilters: builder.query<
      {
        stores: { id: number; name: string }[];
        restaurants: { id: number; name: string }[];
        authors: { id: number; username: string }[];
        customers: { id: number; username: string }[];
      },
      void
    >({
      query: () => 'api/incidents/filters',
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

    getShifts: builder.query<HydraCollection<Shift>, DateRangeArgs>({
      query: ({ after, before }) => ({
        url: 'api/shifts',
        params: { 'date[after]': after, 'date[before]': before },
      }),
      providesTags: ['Shift'],
    }),
    getMyShifts: builder.query<HydraCollection<Shift>, DateRangeArgs>({
      query: ({ after, before }) => ({
        url: 'api/me/shifts',
        params: { 'date[after]': after, 'date[before]': before },
      }),
      providesTags: ['Shift'],
    }),
    postShift: builder.mutation<Shift, ShiftPayload>({
      query: body => ({
        url: 'api/shifts',
        method: 'POST',
        body,
      }),
      invalidatesTags: ['Shift'],
    }),
    putShift: builder.mutation<Shift, PutShiftRequest>({
      query: ({ '@id': uri, ...body }) => ({
        url: uri,
        method: 'PUT',
        body,
      }),
      invalidatesTags: ['Shift'],
      // Optimistically patch the cached week(s) so the grid reflects the
      // change on drop (calendar drag/resize) instead of snapping back until
      // the invalidation refetch lands; undone if the request fails.
      // Assignments are left untouched — the payload only has user IRIs, the
      // refetch reconciles them.
      async onQueryStarted(arg, { dispatch, getState, queryFulfilled }) {
        const patches = apiSlice.util
          .selectCachedArgsForQuery(getState(), 'getShifts')
          .map(args =>
            dispatch(
              apiSlice.util.updateQueryData('getShifts', args, draft => {
                const shift = draft['hydra:member'].find(
                  s => s['@id'] === arg['@id'],
                );
                if (shift) {
                  shift.type = arg.type;
                  shift.startsAt = arg.startsAt;
                  shift.endsAt = arg.endsAt;
                  shift.slots = arg.slots;
                  if (arg.breakMinutes !== undefined) {
                    shift.breakMinutes = arg.breakMinutes;
                  }
                  if (arg.comment !== undefined) {
                    shift.comment = arg.comment;
                  }
                }
              }),
            ),
          );

        try {
          await queryFulfilled;
        } catch {
          patches.forEach(patch => patch.undo());
        }
      },
    }),
    deleteShift: builder.mutation<void, Uri>({
      query: uri => ({
        url: uri,
        method: 'DELETE',
      }),
      invalidatesTags: ['Shift'],
    }),
    copyWeek: builder.mutation<void, CopyWeekRequest>({
      query: body => ({
        url: 'api/shifts/copy_week',
        method: 'POST',
        body,
      }),
      invalidatesTags: ['Shift'],
    }),
    getHolidayRequests: builder.query<
      HydraCollection<HolidayRequest>,
      GetHolidayRequestsArgs
    >({
      query: ({ after, before, status }) => {
        const params = new URLSearchParams({
          'date[after]': after,
          'date[before]': before,
        });
        (status || []).forEach(s => params.append('status[]', s));
        return { url: `api/holiday_requests?${params.toString()}` };
      },
      providesTags: ['HolidayRequest'],
    }),
    getMyHolidayRequests: builder.query<HydraCollection<HolidayRequest>, void>({
      query: () => 'api/me/holiday_requests',
      providesTags: ['HolidayRequest'],
    }),
    postHolidayRequest: builder.mutation<
      HolidayRequest,
      PostHolidayRequestRequest
    >({
      query: body => ({
        url: 'api/holiday_requests',
        method: 'POST',
        body,
      }),
      invalidatesTags: ['HolidayRequest'],
    }),
    approveHolidayRequest: builder.mutation<HolidayRequest, Uri>({
      query: uri => ({
        url: `${uri}/approve`,
        method: 'PUT',
        body: {},
      }),
      invalidatesTags: ['HolidayRequest'],
    }),
    rejectHolidayRequest: builder.mutation<HolidayRequest, Uri>({
      query: uri => ({
        url: `${uri}/reject`,
        method: 'PUT',
        body: {},
      }),
      invalidatesTags: ['HolidayRequest'],
    }),
    deleteHolidayRequest: builder.mutation<void, Uri>({
      query: uri => ({
        url: uri,
        method: 'DELETE',
      }),
      invalidatesTags: ['HolidayRequest'],
    }),
    getPlanningUsers: builder.query<PlanningUser[], void>({
      query: () =>
        'api/users?roles[]=ROLE_COURIER&roles[]=ROLE_DISPATCHER&roles[]=ROLE_ADMIN',
      transformResponse: (response: HydraCollection<PlanningUser>) =>
        response['hydra:member'],
      // A user's skills come from here, so a skill (un)assignment must refresh it
      providesTags: ['Skill'],
    }),

    getSkills: builder.query<SkillWithUsers[], void>({
      query: () => 'api/skills',
      transformResponse: (response: HydraCollection<SkillWithUsers>) =>
        response['hydra:member'],
      providesTags: ['Skill'],
    }),
    postSkill: builder.mutation<Skill, SkillPayload>({
      query: body => ({
        url: 'api/skills',
        method: 'POST',
        body,
      }),
      invalidatesTags: ['Skill'],
    }),
    putSkill: builder.mutation<Skill, PutSkillRequest>({
      query: ({ '@id': uri, ...body }) => ({
        url: uri,
        method: 'PUT',
        body,
      }),
      invalidatesTags: ['Skill'],
    }),
    deleteSkill: builder.mutation<void, Uri>({
      query: uri => ({
        url: uri,
        method: 'DELETE',
      }),
      invalidatesTags: ['Skill'],
    }),

    getShiftSettings: builder.query<ShiftSettings, void>({
      query: () => 'api/shift_settings',
      providesTags: ['ShiftSettings'],
    }),
    putShiftSettings: builder.mutation<
      ShiftSettings,
      PutShiftSettingsRequest
    >({
      query: body => ({
        url: 'api/shift_settings',
        method: 'PUT',
        body,
      }),
      invalidatesTags: ['ShiftSettings'],
    }),

    generateSchedule: builder.mutation<ShiftScheduleSuggestion, { week: string }>({
      query: body => ({
        url: 'api/shifts/generate_schedule',
        method: 'POST',
        body,
      }),
    }),
    batchCreateShifts: builder.mutation<
      ShiftBatchResult,
      { shifts: ProposedShift[] }
    >({
      query: body => ({
        url: 'api/shifts/batch',
        method: 'POST',
        body,
      }),
      invalidatesTags: ['Shift'],
    }),
    syncDispatch: builder.mutation<ShiftDispatchSyncResult, { week: string }>({
      query: body => ({
        url: 'api/shifts/dispatch_sync',
        method: 'POST',
        body,
      }),
    }),

    getBankHolidays: builder.query<BankHolidays, DateRangeArgs>({
      query: ({ after, before }) => ({
        url: 'api/bank_holidays',
        params: { 'date[after]': after, 'date[before]': before },
      }),
    }),

    getShiftDashboard: builder.query<ShiftDashboard, GetShiftDashboardArgs>({
      query: ({ weeks } = {}) => ({
        url: 'api/shifts/dashboard',
        params: weeks ? { weeks } : undefined,
      }),
      providesTags: ['Shift', 'SchedulePublication'],
    }),

    getMe: builder.query<Me, void>({
      query: () => 'api/me',
    }),
    getSchedulePublications: builder.query<
      SchedulePublication[],
      { weekStart: string }
    >({
      query: ({ weekStart }) => ({
        url: 'api/schedule_publications',
        params: { weekStart },
      }),
      transformResponse: (response: HydraCollection<SchedulePublication>) =>
        response['hydra:member'],
      providesTags: ['SchedulePublication'],
    }),
    publishWeek: builder.mutation<void, { week: string }>({
      query: body => ({
        url: 'api/shifts/publish_week',
        method: 'POST',
        body,
      }),
      invalidatesTags: ['SchedulePublication', 'Shift'],
    }),
    getOpenShifts: builder.query<HydraCollection<Shift>, DateRangeArgs>({
      query: ({ after, before }) => ({
        url: 'api/shifts/open',
        params: { 'date[after]': after, 'date[before]': before },
      }),
      providesTags: ['Shift', 'SchedulePublication'],
    }),
    getShiftCompliance: builder.query<ShiftCompliance, { week: string }>({
      query: ({ week }) => ({
        url: 'api/shifts/compliance',
        params: { week },
      }),
      // Recheck whenever shifts change or the legal config is edited
      providesTags: ['Shift', 'ShiftSettings'],
    }),
    getShiftCalendar: builder.query<ShiftCalendar, void>({
      query: () => 'api/me/shift_calendar',
    }),
    applyToShift: builder.mutation<Shift, Uri>({
      query: uri => ({
        url: `${uri}/apply`,
        method: 'PUT',
        body: {},
      }),
      invalidatesTags: ['Shift'],
    }),
    unapplyFromShift: builder.mutation<Shift, Uri>({
      query: uri => ({
        url: `${uri}/unapply`,
        method: 'PUT',
        body: {},
      }),
      invalidatesTags: ['Shift'],
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
  useGetIncidentsQuery,
  useGetIncidentFiltersQuery,
  useUpdateShopCollectionMutation,
  useCreateShopCollectionMutation,
  useDeleteShopCollectionMutation,
  useGetShiftsQuery,
  useGetMyShiftsQuery,
  usePostShiftMutation,
  usePutShiftMutation,
  useDeleteShiftMutation,
  useCopyWeekMutation,
  useGetHolidayRequestsQuery,
  useGetMyHolidayRequestsQuery,
  usePostHolidayRequestMutation,
  useApproveHolidayRequestMutation,
  useRejectHolidayRequestMutation,
  useDeleteHolidayRequestMutation,
  useGetPlanningUsersQuery,
  useGetShiftSettingsQuery,
  usePutShiftSettingsMutation,
  useGenerateScheduleMutation,
  useBatchCreateShiftsMutation,
  useSyncDispatchMutation,
  useGetBankHolidaysQuery,
  useGetShiftDashboardQuery,
  useGetSkillsQuery,
  usePostSkillMutation,
  usePutSkillMutation,
  useDeleteSkillMutation,
  useGetMeQuery,
  useGetSchedulePublicationsQuery,
  usePublishWeekMutation,
  useGetOpenShiftsQuery,
  useGetShiftComplianceQuery,
  useGetShiftCalendarQuery,
  useApplyToShiftMutation,
  useUnapplyFromShiftMutation,
} = apiSlice;
