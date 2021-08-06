cube(`Store`, {
  sql: `SELECT * FROM public.store`,

  measures: {
    count: {
      type: `count`,
      drillMembers: [id]
    },
    cumulativeCount: {
      // Don't use "id" here, or it would sum the ids
      sql: `1`,
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
