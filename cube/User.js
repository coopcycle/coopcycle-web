cube(`User`, {
  sql_table: `api_user`,

  dimensions: {
    id: {
      sql: `id`,
      type: `number`,
      primaryKey: true
    },
    username: {
      type: `string`,
      sql: `username`,
    },
  },

  dataSource: `default`
});
