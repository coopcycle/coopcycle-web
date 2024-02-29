cube(`Payment`, {
  sql_table: `public.sylius_payment`,
  joins: {
    Order: {
      relationship: `many_to_one`,
      sql: `${CUBE}.order_id = ${Order}.id`
    },
    Refund: {
      relationship: `one_to_many`,
      sql: `${CUBE}.id = ${Refund}.payment_id`
    },
    PaymentMethod: {
      relationship: `one_to_one`,
      sql: `${CUBE}.method_id = ${PaymentMethod}.id`
    },
  },
  measures: {
    refundTotal: {
      sql: `${CUBE.Refund.totalAmount}`,
      type: `number`,
      format: `currency`
    },
  },
  dimensions: {
    id: {
      sql: `id`,
      type: `number`,
      primaryKey: true
    },
    method: {
      sql: `${CUBE.PaymentMethod.code}`,
      type: `string`,
    }
  },
  dataSource: `default`
});
