cube(`CityZone`, {
  sql: `SELECT * FROM public.city_zone`,
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
    polygon: {
      sql: `ST_AsGeoJSON(polygon)`,
      type: 'string',
    },
  },
  dataSource: `default`
});

