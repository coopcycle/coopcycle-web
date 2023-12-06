cube(`DeliveryAdjustment`, {
  sql: `SELECT * FROM public.sylius_adjustment WHERE type = 'delivery'`,
  extends: Adjustment,
});
