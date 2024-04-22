cube(`Adjustment`, {
  sql: `SELECT * FROM public.sylius_adjustment`,

  // joins: {
  //   Order: {
  //     relationship: `one_to_many`,
  //     sql: `${CUBE}.order_id = ${Order}.id`
  //   },
  //   OrderItem: {
  //     relationship: `one_to_many`,
  //     sql: `${CUBE}.order_item_id = ${OrderItem}.id`,
  //   },
  // },

  measures: {
    // TODO Check if neutral = false
    totalAmount: {
      sql: `COALESCE(ROUND(${CUBE}.amount / 100::numeric, 2), 0)`,
      type: `sum`,
      format: `currency`
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

