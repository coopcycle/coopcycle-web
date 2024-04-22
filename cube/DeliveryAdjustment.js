cube(`DeliveryAdjustment`, {
  sql: `SELECT * FROM public.sylius_adjustment WHERE type = 'delivery'`,
  extends: Adjustment,
  pre_aggregations: {
    main: {
      measures: [
        DeliveryAdjustment.totalAmount
      ],
      dimensions: [
        Order.number
      ],
      timeDimension: OrderFulfilledEvent.createdAt,
      granularity: `day`
    }
  }
});
