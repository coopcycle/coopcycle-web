cube(`Refund`, {
  sql_table: `public.refund`,
  dimensions: {
    id: {
      sql: `id`,
      type: `number`,
      primaryKey: true
    },
    amount: {
      sql: `amount`,
      type: `number`
    }
  },
  dataSource: `default`
});
