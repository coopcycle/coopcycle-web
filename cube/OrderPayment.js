cube(`OrderPayment`, {
  sql: `
  SELECT
    o.id AS order_id,
    m.code AS method,
    COALESCE(SUM(r.amount), 0) AS refund_total
  FROM sylius_order o
  LEFT JOIN sylius_payment p ON p.order_id = o.id
  LEFT JOIN sylius_payment_method m ON m.id = p.method_id
  LEFT JOIN refund r ON r.payment_id = p.id
  GROUP BY o.id, m.code`,
  joins: {
    Order: {
      relationship: `one_to_one`,
      sql: `${CUBE}.order_id = ${Order}.id`
    },
  },
  dimensions: {
    order_id: {
      sql: `order_id`,
      type: `number`,
      primaryKey: true
    },
    method: {
      sql: `method`,
      type: `string`,
    },
    refund_total: {
      sql: `refund_total`,
      type: `number`,
    },
  }
})
