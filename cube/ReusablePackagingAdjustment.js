cube(`ReusablePackagingAdjustment`, {
  sql: `SELECT * FROM public.sylius_adjustment WHERE type = 'reusable_packaging'`,
  extends: Adjustment,
  pre_aggregations: {
    main: {
      measures: [
        ReusablePackagingAdjustment.totalAmount
      ],
      dimensions: [
        Order.number
      ],
      timeDimension: OrderFulfilledEvent.createdAt,
      granularity: `day`
    }
  }
});
