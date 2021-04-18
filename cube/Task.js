cube(`Task`, {
  sql: `SELECT id, done_before AS before FROM public.task`,

  joins: {

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

    date: {
      sql: `before`,
      type: `time`
    },

  },

  dataSource: `default`
});
