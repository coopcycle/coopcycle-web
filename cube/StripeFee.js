cube(`StripeFee`, {
  sql: `SELECT * FROM public.sylius_adjustment WHERE type = 'stripe_fee'`,

  joins: {
    Order: {
      relationship: `belongsTo`,
      sql: `${StripeFee}.order_id = ${Order}.id`
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
