cube(`StripeFee`, {
  extends: Adjustment,
  sql: `SELECT * FROM public.sylius_adjustment WHERE type = 'stripe_fee'`,
  joins: {
    Order: {
      relationship: `many_to_one`,
      sql: `${CUBE}.order_id = ${Order}.id`
    }
  },
  pre_aggregations: {
    main: {
      measures: [
        StripeFee.totalAmount
      ],
      dimensions: [
        Order.number
      ],
      timeDimension: OrderFulfilledEvent.createdAt,
      granularity: `day`
    }
  }
});
