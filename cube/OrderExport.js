  const {securityContext} = COMPILE_CONTEXT

  const PER_TASK = 0.125
  const TOTAL_PERCENTAGE = 0.03

  const partitionPath = ({instance, year, month}) => {
    const _ = (name, value) => value ? `${name}=${value}` : "*"
    return `${_("instance", instance)}/${_("year", year)}/${_("month", month)}`
  }

  cube(`OrderExport`, {
    sql: `SELECT * FROM read_parquet('s3://billing/orders/${partitionPath(securityContext)}/*.parquet', hive_partitioning = true)`,

    refresh_key: {
      every: "1 second"
    },

    //TODO: Check join per instances to avoid order code collision
    joins: {
      TaskExport: {
        relationship: `one_to_many`,
        sql: `${CUBE}.order_code = ${TaskExport.order_code}`
      }
    },

    // Group By / Index
    dimensions: {
      order_code: {
        sql: `order_code`,
        type: `string`,
        primary_key: true,
        public: true
      },
      instance: {
        sql: `instance`,
        type: `string`
      },
    },


    // Data aggregations
    measures: {

      total_vat: {
        sql: `total_vat`,
        type: `sum`,
        format: `currency`
      },
      order_coopcycle_fee_percentage: {
        sql: `ROUND(${CUBE.total_vat} * ${TOTAL_PERCENTAGE}, 2)`,
        type: `number`,
        format: `currency`
      },
      order_coopcycle_fee_per_task: {
        sql: `ROUND(GREATEST((${TaskExport.count} - 1) * ${PER_TASK}, 0), 2)`,
        type: `number`,
      },
    },

    dataSource: "duckdb"
  });
