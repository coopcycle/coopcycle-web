cube(`TaskDoneEvent`, {
  sql: `SELECT * FROM public.task_event WHERE name='task:done'`,

  preAggregations: {
    // Pre-Aggregations definitions go here
    // Learn more here: https://cube.dev/docs/caching/pre-aggregations/getting-started
  },

  joins: {

  },

  measures: {
    count: {
      type: `count`,
      drillMembers: [id, name, createdAt]
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

    data: {
      sql: `data`,
      type: `string`
    },

    metadata: {
      sql: `metadata`,
      type: `string`
    },

    createdAt: {
      sql: `created_at`,
      type: `time`
    }
  },

  dataSource: `default`
});
