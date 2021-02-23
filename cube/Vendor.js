cube(`Vendor`, {
  sql: `SELECT * FROM public.vendor`,

  joins: {
    Restaurant: {
      relationship: `hasOne`,
      sql: `${Restaurant}.id = ${Vendor}.restaurant_id`
    },
    Hub: {
      relationship: `hasOne`,
      sql: `${Hub}.id = ${Vendor}.hub_id`
    }
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

    type: {
      sql: `CASE WHEN hub_id IS NOT NULL THEN 'hub' ELSE 'restaurant' END`,
      type: `string`
    },

    name: {
      sql: `COALESCE(${Hub.name}, ${Restaurant.name})`,
      type: `string`
    }

  },

  dataSource: `default`
});
