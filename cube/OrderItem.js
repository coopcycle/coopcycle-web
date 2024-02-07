cube(`OrderItem`, {
  sql_table: `public.sylius_order_item`,
  joins: {
    Order: {
      relationship: `many_to_one`,
      sql: `${CUBE}.order_id = ${Order}.id`
    },
    TaxAdjustment: {
      relationship: `many_to_one`,
      sql: `${CUBE}.id = ${TaxAdjustment}.order_item_id`
    }
  },
  dimensions: {
    id: {
      sql: `id`,
      type: `number`,
      primaryKey: true
    },
  },
  measures: {
    total: {
      sql: `ROUND(${CUBE}.total / 100::numeric, 2)`,
      type: `sum`,
    },
    taxTotal: {
      sql: `${CUBE.TaxAdjustment.totalAmount}`,
      type: `number`,
    },
    tax_total_standard: {
      sql: `${CUBE.TaxAdjustment.total_standard}`,
      type: `number`,
    },
    tax_total_intermediary: {
      sql: `${CUBE.TaxAdjustment.total_intermediary}`,
      type: `number`,
    },
    tax_total_reduced: {
      sql: `${TaxAdjustment.total_reduced}`,
      type: `number`,
    },
    total_excl_tax: {
      sql: `${CUBE.total} - ${CUBE.TaxAdjustment.totalAmount}`,
      type: `number`,
    },
    total_excl_tax_standard: {
      sql: `${CUBE.total} - ${TaxAdjustment.total_standard}`,
      type: `number`,
    },
    total_excl_tax_intermediary: {
      sql: `${CUBE.total} - ${TaxAdjustment.total_intermediary}`,
      type: `number`,
    },
    total_excl_tax_reduced: {
      sql: `${CUBE.total} - ${TaxAdjustment.total_reduced}`,
      type: `number`,
    },
  },
  dataSource: `default`
})
