cube(`OrderItem`, {
  sql_table: `public.sylius_order_item`,
  joins: {
    /*
    Order: {
      relationship: `many_to_one`,
      sql: `${CUBE}.order_id = ${Order}.id`
    },
    */
    OrderItemAdjustment: {
      relationship: `many_to_one`,
      sql: `${CUBE}.id = ${OrderItemAdjustment}.order_item_id`
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
      sql: `${CUBE.OrderItemAdjustment.amount}`,
      type: `sum`,
      filters: [{ sql: `${CUBE.OrderItemAdjustment.type} = 'tax'` }],
    },
    tax_total_standard: {
      sql: `${CUBE.OrderItemAdjustment.amount}`,
      type: `sum`,
      filters: [{
        sql: `${CUBE.OrderItemAdjustment}.type = 'tax' AND ${CUBE.OrderItemAdjustment}.origin_code IN ('FR_BASE_STANDARD_STANDARD', 'FR_SERVICE_STANDARD', 'FR_DRINK_ALCOHOL_STANDARD')`
      }],
    },
    tax_total_intermediary: {
      sql: `${CUBE.OrderItemAdjustment.amount}`,
      type: `sum`,
      filters: [{
        sql: `${CUBE.OrderItemAdjustment.type} = 'tax' AND ${CUBE.OrderItemAdjustment}.origin_code IN ('FR_BASE_INTERMEDIARY_INTERMEDIARY', 'FR_FOOD_TAKEAWAY_INTERMEDIARY')`
      }],
    },
    tax_total_reduced: {
      sql: `${CUBE.OrderItemAdjustment.amount}`,
      type: `sum`,
      filters: [{
        sql: `${CUBE.OrderItemAdjustment.type} = 'tax' AND ${CUBE.OrderItemAdjustment}.origin_code IN ('FR_BASE_REDUCED_REDUCED', 'FR_DRINK_REDUCED')`
      }],
    },
    total_excl_tax: {
      sql: `0`, // `${CUBE.total} - ${CUBE.TaxAdjustment.totalAmount}`,
      type: `number`,
    },
    total_excl_tax_standard: {
      sql: `0`, // `${CUBE.total} - ${CUBE.TaxAdjustment.total_standard}`,
      type: `number`,
    },
    total_excl_tax_intermediary: {
      sql: `0`, // `${CUBE.total} - ${CUBE.TaxAdjustment.total_intermediary}`,
      type: `number`,
    },
    total_excl_tax_reduced: {
      sql: `0`, // `${CUBE.total} - ${CUBE.TaxAdjustment.total_reduced}`,
      type: `number`,
    },
  },
  dataSource: `default`
})
