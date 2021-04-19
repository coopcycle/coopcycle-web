cube(`Restaurant`, {
  sql: `SELECT * FROM public.restaurant`,

  joins: {
    OrderVendor: {
      relationship: `hasMany`,
      sql: `${Restaurant}.id = ${OrderVendor}.restaurant_id`
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
