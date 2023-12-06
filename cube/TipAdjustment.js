cube(`TipAdjustment`, {
  sql: `SELECT * FROM public.sylius_adjustment WHERE type = 'tip'`,
  extends: Adjustment,
});
