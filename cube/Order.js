cube(`Order`, {
  sql: `SELECT * FROM public.sylius_order`,

  joins: {

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
    },

    averageTotal: {
      sql: `ROUND(total / 100::numeric, 2)`,
      type: `avg`
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
      // https://www.postgresql.org/docs/current/functions-formatting.html
      // ISO 8601 day of the week, Monday (1) to Sunday (7)
      sql: `TO_CHAR(LOWER(shipping_time_range), 'ID')`,
      type: `string`
    },

  },

  dataSource: `default`
});
