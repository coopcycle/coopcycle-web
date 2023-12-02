cube(`Tagging`, {
  sql: `SELECT * FROM public.tagging`,

  preAggregations: {
    // Pre-Aggregations definitions go here
    // Learn more here: https://cube.dev/docs/caching/pre-aggregations/getting-started
  },

  joins: {
    Tag: {
      relationship: `hasOne`,
      sql: `${CUBE.tagId} = ${Tag.id}`,
    },
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

    tagId: {
      sql: `tag_id`,
      type: `number`
    },

    resourceId: {
      sql: `resource_id`,
      type: `number`
    },

    resourceClass: {
      sql: `resource_class`,
      type: `string`
    }
  },

  dataSource: `default`
});
