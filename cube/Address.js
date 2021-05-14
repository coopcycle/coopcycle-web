cube(`Address`, {
  sql: `SELECT * FROM public.address`,

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

    geo: {
      type: `geo`,
      latitude: {
        sql: `ST_Y(${CUBE}.geo::geometry)`,
      },
      longitude: {
        sql: `ST_X(${CUBE}.geo::geometry)`
      }
    },

    addressLocality: {
      sql: `address_locality`,
      type: `string`
    },

    postalCode: {
      sql: `postal_code`,
      type: `string`
    }

  },

  dataSource: `default`
});
