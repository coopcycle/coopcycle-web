cube(`Task`, {
  sql: `SELECT id, type, done_after AS after, done_before AS before, status FROM public.task`,

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

    status: {
      sql: `status`,
      type: `string`
    },

  },

  dataSource: `default`
});
