cube(`TaxAdjustment`, {
  sql: `SELECT * FROM public.sylius_adjustment WHERE type = 'tax'`,
  extends: Adjustment,
  joins: {
    TaxRate: {
      relationship: `one_to_one`,
      sql: `${CUBE}.origin_code = ${TaxRate}.code`
    },
  },
});
