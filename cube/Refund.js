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
  measures: {
    totalAmount: {
      sql: `ROUND(${CUBE}.amount / 100::numeric, 2)`,
      type: `sum`,
      format: `currency`
    },
  },
  dataSource: `default`
});
