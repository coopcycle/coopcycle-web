cube(`TaskFailedEvent`, {
  sql: `SELECT * FROM public.task_event WHERE name='task:failed'`,

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
    },

  },

  dataSource: `default`
});
