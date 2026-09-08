const { securityContext: { instance: tenant_instance } } = COMPILE_CONTEXT
cube(`TaskExport`, {
  data_source: `clickhouse`,
  // FINAL because the table is a ReplacingMergeTree: a row that has been
  // exported more than once (a late completion, a refund, a replayed file)
  // exists several times until the parts are merged, and only the copy with
  // the highest exported_at is current.
  sql: tenant_instance
    ? `SELECT * FROM default.tasks_v2 FINAL WHERE instance = '${tenant_instance}'`
    : `SELECT * FROM default.tasks_v2 FINAL`,

  joins: {
    OrderExport: {
      relationship: `many_to_one`,
      sql: `${CUBE.order_code} = ${OrderExport.order_code} AND ${CUBE.instance} = ${OrderExport.instance}`
    }
  },

  dimensions: {
    id: {
      sql: `id`,
      type: `number`,
    },

    order_code: {
      sql: `order_code`,
      type: `string`,
      primary_key: true
    },

    type: {
      sql: `type`,
      type: `string`
    },

    address: {
      sql: `address`,
      type: `string`
    },

    status: {
      sql: `status`,
      type: `string`
    },

    courier: {
      sql: `courier`,
      type: `string`
    },

    organization: {
      sql: `organization`,
      type: `string`
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

    after: {
      sql: `after`,
      type: `time`
    },

    before: {
      sql: `before`,
      type: `time`
    },

    finished: {
      sql: `finished`,
      type: `time`
    }
  },

  measures: {
    count: {
      sql: `*`,
      type: `count`,
    },

    count_distinct: {
      sql: `DISTINCT ${CUBE}.id`,
      type: `count`
    },

    order_total: {
      sql: `order_total`,
      type: `sum`
    }
  },

  pre_aggregations: {}
});

