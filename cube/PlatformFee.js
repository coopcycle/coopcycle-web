cube(`PlatformFee`, {
  extends: Adjustment,
  sql: `SELECT * FROM public.sylius_adjustment WHERE type = 'fee'`,
  joins: {
    Order: {
      relationship: `many_to_one`,
      sql: `${CUBE}.order_id = ${Order}.id`
    }
  },
});
