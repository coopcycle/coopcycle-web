cube(`ReusablePackagingAdjustment`, {
  sql: `SELECT * FROM public.sylius_adjustment WHERE type = 'reusable_packaging'`,
  extends: Adjustment,
});
