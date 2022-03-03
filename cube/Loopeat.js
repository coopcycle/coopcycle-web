cube(`Loopeat`, {
  sql: `
  SELECT
      r.name AS restaurant_name,
      o.number AS order_number,
      LOWER(o.shipping_time_range) AS order_date,
      c.email_canonical AS customer_email,
      CEIL(SUM(p.reusable_packaging_unit * i.quantity))::integer AS grabbed_quantity,
      o.reusable_packaging_pledge_return AS returned_quantity
  FROM sylius_order_item i
  JOIN sylius_product_variant v ON i.variant_id = v.id
  JOIN sylius_product p on v.product_id = p.id
  JOIN sylius_order o on i.order_id = o.id
  JOIN sylius_customer c on o.customer_id = c.id
  JOIN vendor vnd ON o.vendor_id = vnd.id
  JOIN restaurant r ON vnd.restaurant_id = r.id
  WHERE
      o.state = 'fulfilled'
  AND o.reusable_packaging_enabled = 't'
  AND p.reusable_packaging_enabled = 't'
  GROUP BY
      c.email_canonical,
      r.name,
      o.number,
      o.shipping_time_range,
      o.reusable_packaging_pledge_return
  HAVING
     CEIL(SUM(p.reusable_packaging_unit * i.quantity)) > 0
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
    grabbedQuantity: {
      sql: `grabbed_quantity`,
      type: `number`,
    },
    returnedQuantity: {
      sql: `returned_quantity`,
      type: `number`,
    },
  },

  dataSource: `default`
});
