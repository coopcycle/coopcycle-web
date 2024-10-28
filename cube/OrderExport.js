const { securityContext } = COMPILE_CONTEXT

const partitionPath = ({instance, year, month}) => {
  const _ = (name, value) => value ? `${name}=${value}` : "*"
  return `${_("instance", instance)}/${_("year", year)}/${_("month", month)}`
}

const resolvePath = ({ s3_path }) => s3_path && s3_path.replace(/^\/|\/$/g, '').replace('%type%', 'orders')

cube(`OrderExport`, {

  sql: `SELECT * FROM read_parquet('s3://${resolvePath(securityContext)}/${partitionPath(securityContext)}/*.parquet', hive_partitioning = true)`,

  joins: {
    TaskExport: {
      relationship: `one_to_many`,
      sql: `${CUBE}.order_code = ${TaskExport.order_code} AND ${CUBE}.instance = ${TaskExport.instance}`,
    }
  },

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
    restaurant: {
      sql: `restaurant`,
      type: `string`
    },
    completed_at: {
      sql: `completed_at`,
      type: `time`
    },
    day_of_week: {
      sql: `DATE_PART('isodow', ${CUBE.completed_at})`,
      type: `number`
    },
    week: {
      sql: `DATE_PART('week', ${CUBE.completed_at})`,
      type: `number`
    },
    hour: {
      sql: `DATE_PART('hour', ${CUBE.completed_at})`,
      type: `number`
    },
    billing_method: {
      sql: `billing_method`,
      type: `string`
    },
    applied_billing: {
      sql: `applied_billing`,
      type: `string`
    }
  },

  measures: {
    count: {
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

  dataSource: "duckdb"
});
