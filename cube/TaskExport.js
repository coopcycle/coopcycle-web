asyncModule(async () => {

  const {securityContext} = COMPILE_CONTEXT

  const PER_TASK = 0.125
  const TOTAL_PERCENTAGE = 0.03

  const partitionPath = ({instance, year, month}) => {
    const _ = (name, value) => value ? `${name}=${value}` : "*"
    return `${_("instance", instance)}/${_("year", year)}/${_("month", month)}`
  }

  const fee_sql = (_cube) => {
    console.log("coucou")
    return `order_total`
  }

  cube(`TaskExport`, {
    sql: `SELECT * FROM read_parquet('s3://billing/orders/${partitionPath(securityContext)}/*.parquet', hive_partitioning = true)`,

    refresh_key: {
      every: "1 second"
    },

    // Group By / Index
    dimensions: {
      type: {
        sql: `type`,
        type: `string`
      },
      instance: {
        sql: `instance`,
        type: `string`
      },
      order: {
        sql: `order_id`,
        type: `number`
      }
    },


    // Data aggregations
    measures: {

      order_total: {
        sql: `order_total`,
        type: `sum`,
      },

      order_coopcycle_fee_percentage: {
        sql: `order_total * ${TOTAL_PERCENTAGE}`,
        filters: [{ sql: `${CUBE}.order_id IS NOT NULL` }],
        type: `number`,
        format: `currency`
      },

      order_coopcycle_fee_per_task: {
        sql: `COUNT(*) * ${PER_TASK}`,
        type: `number`,
        filters: [{ sql: `${CUBE}.type = 'DROPOFF'` }],
        format: `currency`
      },

      count: {
        sql: 'id',
        type: 'count'
      }
    },

    dataSource: "duckdb"
  });
})
