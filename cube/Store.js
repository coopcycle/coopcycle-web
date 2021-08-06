cube(`Store`, {
  sql: `SELECT * FROM public.store`,

  measures: {
    count: {
      type: `count`,
      drillMembers: [id]
    },
    cumulativeCount: {
      sql: `id`,
      type: `runningTotal`,
    },
  },

  dimensions: {
    id: {
      sql: `id`,
      type: `number`,
      primaryKey: true
    },

    createdAt: {
      sql: `DATE(created_at)`,
      type: `time`
    },
  },

  dataSource: `default`
});
