const { securityContext } = COMPILE_CONTEXT

const partitionPath = ({instance, year, month}) => {
  const _ = (name, value) => value ? `${name}=${value}` : "*"
  return `${_("instance", instance)}/${_("year", year)}/${_("month", month)}`
}

const resolvePath = ({ s3_path }) => s3_path && s3_path.replace(/^\/|\/$/g, '').replace('%type%', 'tasks')

cube(`TaskExport`, {
  sql: `SELECT * FROM read_parquet('s3://${resolvePath(securityContext)}/${partitionPath(securityContext)}/*.parquet', hive_partitioning = true)`,

  refresh_key: {
    every: "1 second"
  },

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

  measures: {
    count: {
      type: 'count'
    }
  },

  dataSource: "duckdb"
});
