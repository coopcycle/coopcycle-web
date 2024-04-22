cube(`PromotionAdjustment`, {
  sql: `SELECT * FROM public.sylius_adjustment WHERE type IN ('delivery_promotion', 'order_promotion')`,
  extends: Adjustment,
  pre_aggregations: {
    main: {
      measures: [
        PromotionAdjustment.totalAmount
      ],
      dimensions: [
        Order.number
      ],
      timeDimension: OrderFulfilledEvent.createdAt,
      granularity: `day`
    }
  }
});

