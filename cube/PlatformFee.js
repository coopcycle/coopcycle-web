cube(`PlatformFee`, {
  extends: Adjustment,
  sql: `SELECT * FROM public.sylius_adjustment WHERE type = 'fee'`,
  joins: {
    Order: {
      relationship: `many_to_one`,
      sql: `${CUBE}.order_id = ${Order}.id`
    }
  },
  measures: {
    // For backwards compatibility
    amount: {
      sql: `ROUND(amount / 100::numeric, 2)`,
      type: `sum`,
      format: `currency`
    }
  }
});
