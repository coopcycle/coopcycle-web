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
    taxTotal: {
      sql: `${CUBE.TaxAdjustment.totalAmount}`,
      type: `number`,
    },
  },
  dataSource: `default`
})
