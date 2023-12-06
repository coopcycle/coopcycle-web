cube(`Restaurant`, {
  sql: `SELECT * FROM public.restaurant`,

  joins: {
    OrderVendor: {
      relationship: `one_to_many`,
      sql: `${Restaurant}.id = ${OrderVendor}.restaurant_id`
    },
    Hub: {
      relationship: `one_to_one`,
      sql: `${Restaurant}.hub_id = ${Hub}.id`
    }
  },

  measures: {
    count: {
      type: `count`,
      drillMembers: [id]
    },
    orderCount: {
      type: `number`,
      sql: `${OrderVendor.count}`,
    }
  },

  dimensions: {
    id: {
      sql: `id`,
      type: `number`,
      primaryKey: true
    },

    name: {
      sql: `name`,
      type: `string`
    },

  },

  dataSource: `default`
});
