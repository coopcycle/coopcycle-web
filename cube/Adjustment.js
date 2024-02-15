cube(`Adjustment`, {
  sql: `SELECT * FROM public.sylius_adjustment`,

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
    }
  },

  dataSource: `default`
});

