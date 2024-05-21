cube(`OrderFee`, {
  sql: `
  SELECT
    COALESCE(a.order_id, i.order_id) AS order_id,
    a.type,
    a.amount
  FROM sylius_adjustment a
  LEFT JOIN sylius_order_item i ON a.order_item_id = i.id
  `,
  dimensions: {
    order_id: {
      sql: `order_id`,
      type: `number`,
      primaryKey: true
    },
    type: {
      sql: `type`,
      type: `string`,
    },
    amount: {
      sql: `amount`,
      type: `number`,
    },
  },
  dataSource: `default`,
  measures: {
    stripe_fee: {
      type: `sum`,
      sql: `ROUND(${CUBE}.amount / 100::numeric, 2)`,
      filters: [{ sql: `${CUBE}.type = 'stripe_fee'` }],
      format: `currency`,
    },
    platform_fee: {
      type: `sum`,
      sql: `ROUND(${CUBE}.amount / 100::numeric, 2)`,
      filters: [{ sql: `${CUBE}.type = 'fee'` }],
      format: `currency`,
    },
    packaging_fee: {
      type: `sum`,
      sql: `ROUND(${CUBE}.amount / 100::numeric, 2)`,
      filters: [{ sql: `${CUBE}.type = 'reusable_packaging'` }],
      format: `currency`,
    },
    delivery_fee: {
      type: `sum`,
      sql: `ROUND(${CUBE}.amount / 100::numeric, 2)`,
      filters: [{ sql: `${CUBE}.type = 'delivery'` }],
      format: `currency`,
    },
    promotions: {
      type: `sum`,
      sql: `ROUND(${CUBE}.amount / 100::numeric, 2)`,
      filters: [{ sql: `${CUBE}.type IN ('delivery_promotion', 'order_promotion')` }],
      format: `currency`,
    },
    tip: {
      type: `sum`,
      sql: `ROUND(${CUBE}.amount / 100::numeric, 2)`,
      filters: [{ sql: `${CUBE}.type = 'tip'` }],
      format: `currency`,
    },
    revenue: {
      // Use COALESCE when there is no corresponding row with JOIN
      sql: `${CUBE.Order.total} - COALESCE(${CUBE.platform_fee}, 0) - COALESCE(${CUBE.stripe_fee}, 0)`,
      type: `number`,
      format: `currency`
    },
  },
})
