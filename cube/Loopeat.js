cube(`Loopeat`, {
  sql: `
  SELECT
      r.name AS restaurant_name,
      o.number AS order_number,
      (LOWER(o.shipping_time_range) + (UPPER(o.shipping_time_range) - LOWER(o.shipping_time_range)) / 2) AS order_date,
      c.email_canonical AS customer_email,
      u.username AS delivered_by,
      SUM(COALESCE(rpa.amount, 0)) AS packaging_fee
  FROM ${OrderItem.sql()} i
  JOIN sylius_product_variant v ON i.variant_id = v.id
  JOIN sylius_product p on v.product_id = p.id
  JOIN ${Order.sql()} o on i.order_id = o.id
  JOIN sylius_customer c on o.customer_id = c.id
  JOIN ${OrderVendor.sql()} sov ON o.id = sov.order_id
  JOIN ${Restaurant.sql()} r ON sov.restaurant_id = r.id
  LEFT JOIN ${Adjustment.sql()} rpa ON o.id = rpa.order_id AND rpa.type = 'reusable_packaging'
  JOIN ${Delivery.sql()} d on d.order_id = o.id
  JOIN ${Task.sql()} t on t.delivery_id = d.id AND t.type = 'DROPOFF'
  JOIN ${User.sql()} u on t.assigned_to = u.id
  WHERE
      o.state = 'fulfilled'
  AND o.reusable_packaging_enabled = 't'
  AND p.reusable_packaging_enabled = 't'
  GROUP BY
      r.name,
      o.number,
      o.shipping_time_range,
      c.email_canonical,
      u.username
  ORDER BY
      o.shipping_time_range DESC
  `,

  joins: {

  },

  measures: {

  },

  dimensions: {
    orderNumber: {
      sql: `order_number`,
      type: `string`,
    },
    restaurantName: {
      sql: `restaurant_name`,
      type: `string`,
    },
    orderDate: {
      sql: `order_date`,
      type: `time`,
    },
    customerEmail: {
      sql: `customer_email`,
      type: `string`,
    },
    deliveredBy: {
      sql: `delivered_by`,
      type: `string`,
    },
    packagingFee: {
      sql: `ROUND(${CUBE}.packaging_fee / 100::numeric, 2)`,
      type: `number`,
      format: `currency`
    },
  },

  dataSource: `default`
});
