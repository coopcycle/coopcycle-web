cube(`Order`, {
  sql: `SELECT * FROM public.sylius_order`,

  joins: {
    OrderVendor: {
      relationship: `hasMany`,
      sql: `${Order}.id = ${OrderVendor}.order_id`
    }
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
    // https://stackoverflow.com/questions/45141426/how-to-get-average-between-two-dates-in-postgresql
    shippingTimeRange: {
      sql: `(LOWER(shipping_time_range) + (UPPER(shipping_time_range) - LOWER(shipping_time_range)) / 2)`,
      type: `time`
    },

    dayOfWeek: {
      // https://www.postgresql.org/docs/current/functions-formatting.html
      // ISO 8601 day of the week, Monday (1) to Sunday (7)
      sql: `TO_CHAR(${shippingTimeRange}, 'ID')`,
      type: `string`
    },

    hour : {
      sql: `TO_CHAR(${shippingTimeRange}, 'HH24')::numeric`,
      type: `number`
    },

    hourRange: {
      // This will output ranges of 2 hours, like 10-12, 12-14, etc...
      sql: `LPAD((${hour} - (${hour} % 2))::text, 2, '0') || '-' || LPAD((${hour} - (${hour} % 2) + 2)::text, 2, '0')`,
      type: `string`
    },

  },

  dataSource: `default`
});
