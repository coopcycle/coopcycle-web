cube(`Order`, {
  sql: `SELECT * FROM public.sylius_order`,

  joins: {
    Vendor: {
      relationship: `hasOne`,
      sql: `${Order}.vendor_id = ${Vendor}.id`
    },
  },

  measures: {
    count: {
      type: `count`,
      drillMembers: [id]
    },

    itemsTotal: {
      sql: `items_total`,
      type: `sum`
    },

    adjustmentsTotal: {
      sql: `adjustments_total`,
      type: `sum`
    },

    total: {
      sql: `ROUND(total / 100::numeric, 2)`,
      type: `sum`,
      format: `currency`
    }
  },

  dimensions: {
    id: {
      sql: `id`,
      type: `number`,
      primaryKey: true
    },

    state: {
      sql: `state`,
      type: `string`
    },

    // https://cube.dev/docs/working-with-string-time-dimensions
    shippingTimeRange: {
      sql: `LOWER(shipping_time_range)`,
      type: `time`
    },

    dayOfWeek: {
      sql: `TO_CHAR(LOWER(shipping_time_range), 'Day')`,
      type: `string`
    },

  },

  dataSource: `default`
});
