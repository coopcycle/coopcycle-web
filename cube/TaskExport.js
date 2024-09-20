  const {securityContext} = COMPILE_CONTEXT

  const partitionPath = ({instance, year, month}) => {
    const _ = (name, value) => value ? `${name}=${value}` : "*"
    return `${_("instance", instance)}/${_("year", year)}/${_("month", month)}`
  }

  cube(`TaskExport`, {
    sql: `SELECT * FROM read_parquet('s3://billing/tasks/${partitionPath(securityContext)}/*.parquet', hive_partitioning = true)`,

    refresh_key: {
      every: "1 second"
    },

    // Group By / Index
    dimensions: {
      order_code: {
        sql: `order_code`,
        type: `string`,
        primary_key: true
      },
      type: {
        sql: `type`,
        type: `string`
      },
      instance: {
        sql: `instance`,
        type: `string`
      },
    },


    // Data aggregations
    measures: {
      count: {
        type: 'count'
      }
    },

    dataSource: "duckdb"
  });