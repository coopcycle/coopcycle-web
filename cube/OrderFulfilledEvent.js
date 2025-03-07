cube(`OrderFulfilledEvent`, {
  sql: `SELECT * FROM public.sylius_order_event WHERE type = 'order:fulfilled'`,
  dimensions: {
    id: {
      sql: `id`,
      type: `number`,
      primaryKey: true
    },
    createdAt: {
      sql: `created_at`,
      type: `time`
    },
  },
  dataSource: `default`
});
