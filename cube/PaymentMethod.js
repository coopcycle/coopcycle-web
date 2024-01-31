cube(`PaymentMethod`, {
  sql_table: `public.sylius_payment_method`,
  dimensions: {
    id: {
      sql: `id`,
      type: `number`,
      primaryKey: true
    },
    code: {
      sql: `code`,
      type: `string`,
    },
  },
  dataSource: `default`
});
