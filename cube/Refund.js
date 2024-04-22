cube(`Refund`, {
  sql_table: `public.refund`,
  // joins: {
  //   Payment: {
  //     relationship: `many_to_one`,
  //     sql: `${CUBE}.id = ${Paymen}.refund_id`
  //   },
  // },
  dimensions: {
    id: {
      sql: `id`,
      type: `number`,
      primaryKey: true
    },
    amount: {
      sql: `amount`,
      type: `number`
    }
  },
  measures: {
    totalAmount: {
      sql: `ROUND(${CUBE}.amount / 100::numeric, 2)`,
      type: `sum`,
      format: `currency`
    },
  },
  // pre_aggregations: {
  //   main: {
  //     measures: [
  //       Refund.totalAmount
  //     ],
  //     dimensions: [
  //       Order.number
  //     ],
  //     timeDimension: OrderFulfilledEvent.createdAt,
  //     granularity: `day`
  //   }
  // },
  dataSource: `default`
});
