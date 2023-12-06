cube(`OrderVendor`, {
  sql: `SELECT * FROM public.sylius_order_vendor`,

  joins: {
    Order: {
      relationship: `one_to_one`,
      sql: `${Order}.id = ${OrderVendor}.order_id`
    },
    Restaurant: {
      relationship: `one_to_one`,
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
    name: {
      type: `string`,
      case: {
        when: [
          {
            sql: `${CUBE.Restaurant}.hub_id IS NOT NULL`,
            label: { sql: `${CUBE.Restaurant.Hub}.name` },
          },
        ],
        else: {
          label: { sql: `${CUBE.Restaurant}.name` },
        },
      },
    },
  },

  dataSource: `default`
});
