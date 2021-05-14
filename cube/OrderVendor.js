cube(`OrderVendor`, {
  sql: `SELECT * FROM public.sylius_order_vendor`,

  joins: {
    Order: {
      relationship: `hasOne`,
      sql: `${Order}.id = ${OrderVendor}.order_id`
    },
    Restaurant: {
      relationship: `hasOne`,
      sql: `${Restaurant}.id = ${OrderVendor}.restaurant_id`
    },
  },

  measures: {
    count: {
      type: `count`,
    },
  },

  dimensions: {
    id: {
      // Define a composite primary key
      sql: `${CUBE}.order_id || '-' || ${CUBE}.restaurant_id`,
      type: `string`,
      primaryKey: true
    },
  },

  dataSource: `default`
});
