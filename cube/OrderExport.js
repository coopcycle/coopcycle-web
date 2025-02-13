const { securityContext: { instance: tenant_instance } } = COMPILE_CONTEXT
cube(`OrderExport`, {
  data_source: `clickhouse`,
  sql: tenant_instance
    ? `SELECT * FROM default.orders WHERE instance = '${tenant_instance}'`
    : `SELECT * FROM default.orders`,


  joins: {
    TaskExport: {
      relationship: `one_to_many`,
      sql: `${CUBE.order_code} = ${TaskExport.order_code} AND ${CUBE.instance} = ${TaskExport.instance}`
    }
  },

  dimensions: {
    restaurant: {
      sql: `restaurant`,
      type: `string`
    },

    order_code: {
      sql: `order_code`,
      type: `string`,
      primary_key: true
    },

    courier: {
      sql: `courier`,
      type: `string`
    },

    fillfillment: {
      sql: `fillfillment`,
      type: `string`
    },

    payment_method: {
      sql: `payment_method`,
      type: `string`
    },

    billing_method: {
      type: `string`,
      meta: {
        allowedValues: ['unit', 'percent']
      },
      case: {
        when: [
          { sql: `${CUBE}.billing_method = 'unit'`, label: 'unit' },
          { sql: `${CUBE}.billing_method = 'percent'`, label: 'percent' }
        ],
        else: { label: 'Unknown' }
      },
    },

    applied_billing: {
      type: `string`,
      meta: {
        allowedValues: ['FOODTECH', 'LASTMILE'],
      },
      case: {
        when: [
          { sql: `${CUBE}.applied_billing = 'FOODTECH'`, label: 'FOODTECH' },
          { sql: `${CUBE}.applied_billing = 'LASTMILE'`, label: 'LASTMILE' }
        ],
        else: { label: 'Unknown' }
      },
    },

    instance: {
      sql: `instance`,
      type: `string`
    },

    month: {
      sql: `month`,
      type: `string`
    },

    year: {
      sql: `year`,
      type: `string`
    },

    completed_at: {
      sql: `completed_at`,
      type: `time`
    },
    day_of_week: {
      sql: `toDayOfWeek(${CUBE}.completed_at)`,
      type: `number`
    },
    week: {
      sql: `toISOWeek(${CUBE}.completed_at)`,
      type: `number`
    },
    hour: {
      sql: `toHour(${CUBE}.completed_at)`,
      type: `number`
    }
  },

  measures: {
    count: {
      sql: `*`,
      type: `count`
    },
    total_incl_tax: {
      sql: `total_incl_tax / 100`,
      type: `sum`,
      format: `currency`
    },
    total_incl_tax_avg: {
      sql: `total_incl_tax / 100`,
      type: `avg`,
      format: `currency`
    },
    platform_fee: {
      sql: `platform_fee / 100`,
      type: `sum`,
      format: `currency`
    },
    income: {
      sql: `IF (platform_fee != 0, platform_fee / 100, total_products_incl_vat / 100)`,
      type: `sum`,
      format: `currency`,
    },
    platform_fee_avg: {
      sql: `ROUND((platform_fee / 100), 2)`,
      type: `avg`,
      format: `currency`
    }
  },

  pre_aggregations: {}
});

