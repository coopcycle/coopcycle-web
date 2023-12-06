cube(`TaxAdjustment`, {
  sql: `SELECT * FROM public.sylius_adjustment WHERE type = 'tax'`,
  extends: Adjustment,
});

