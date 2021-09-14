cube(`Cuisine`, {
  sql: `SELECT * FROM public.cuisine`,

  joins: {
    Restaurant: {
      relationship: `hasMany`,
      sql: `${Restaurant}.id = ${RestaurantCuisine}.restaurant_id`
    }
  },

  measures: {
    count: {
      type: `count`,
      drillMembers: [id]
    },
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
