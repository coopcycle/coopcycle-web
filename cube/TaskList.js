cube(`TaskList`, {
  sql: `SELECT tl.id, tl.date, u.username, tc.distance FROM public.task_list tl JOIN public.task_collection tc ON tl.id = tc.id JOIN api_user u ON tl.courier_id = u.id`,

  joins: {

  },

  measures: {
    averageDistance: {
      type: `avg`,
      sql: `${distance} / 1000.0`,
    },
    totalDistance: {
      type: `sum`,
      sql: `${distance} / 1000.0`,
    }
  },

  dimensions: {
    id: {
      sql: `id`,
      type: `number`,
      primaryKey: true
    },

    date: {
      sql: `date`,
      type: `time`
    },

    username: {
      sql: `username`,
      type: `string`
    },

    distance: {
      sql: `distance`,
      type: `number`
    },

  },

  dataSource: `default`
});
