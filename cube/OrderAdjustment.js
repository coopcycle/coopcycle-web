cube(`OrderAdjustment`, {
  sql: `SELECT * FROM public.sylius_adjustment`,
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

