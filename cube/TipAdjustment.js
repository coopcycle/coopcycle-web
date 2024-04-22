cube(`TipAdjustment`, {
  sql: `SELECT * FROM public.sylius_adjustment WHERE type = 'tip'`,
  extends: Adjustment,
  pre_aggregations: {
    main: {
      measures: [
        TipAdjustment.totalAmount
      ],
      dimensions: [
        Order.number
      ],
      timeDimension: OrderFulfilledEvent.createdAt,
      granularity: `day`
    }
  }
});
