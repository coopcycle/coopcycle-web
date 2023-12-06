cube(`PromotionAdjustment`, {
  sql: `SELECT * FROM public.sylius_adjustment WHERE type IN ('delivery_promotion', 'order_promotion')`,
  extends: Adjustment,
});

