cube(`Hub`, {
  sql: `SELECT * FROM public.hub`,

  joins: {

  },

  measures: {
    count: {
      type: `count`,
      drillMembers: [id]
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
