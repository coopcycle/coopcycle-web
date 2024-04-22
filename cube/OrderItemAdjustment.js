cube(`OrderItemAdjustment`, {
  sql: `SELECT * FROM public.sylius_adjustment`,
  joins: {
    OrderItem: {
      relationship: `many_to_one`,
      sql: `${CUBE}.order_item_id = ${OrderItem}.id`,
    },
  },
  dimensions: {
    id: {
      sql: `id`,
      type: `number`,
      primaryKey: true
    },
    type: {
      sql: `type`,
      type: `string`
    },
    amount: {
      sql: `amount`,
      type: `number`
    }
  },

  dataSource: `default`
});

