cube(`RestaurantCuisine`, {
  sql: `select * from public.restaurant_cuisine`,

  joins: {
    Restaurant: {
      relationship: `hasOne`,
      sql: `${RestaurantCuisine}.restaurant_id = ${Restaurant}.id`
    },
    Cuisine: {
      relationship: `hasOne`,
      sql: `${RestaurantCuisine}.cuisine_id = ${Restaurant}.id`
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
      sql: `${CUBE}.cuisine_id || '-' || ${CUBE}.restaurant_id`,
      type: `string`,
      primaryKey: true
    },
  },

  dataSource: `default`
})
