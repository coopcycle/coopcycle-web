cube(`PlatformFee`, {
  sql: `SELECT * FROM public.sylius_adjustment WHERE type = 'fee'`,

  joins: {
    Order: {
      relationship: `belongsTo`,
      sql: `${PlatformFee}.order_id = ${Order}.id`
    }
  },

  measures: {
    count: {
      type: `count`,
      drillMembers: [id]
    },
    amount: {
      sql: `ROUND(amount / 100::numeric, 2)`,
      type: `sum`,
      format: `currency`
    }
  },

  dimensions: {
    id: {
      sql: `id`,
      type: `number`,
      primaryKey: true
    },
  },

  dataSource: `default`
});
