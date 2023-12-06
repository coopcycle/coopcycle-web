cube(`Delivery`, {
  sql_table: `public.delivery`,
  joins: {
    Task: {
      relationship: `one_to_many`,
      sql: `${CUBE}.id = ${Task}.delivery_id`
    },
    Store: {
      relationship: `one_to_one`,
      sql: `${CUBE}.store_id = ${Store}.id`
    },
  },
  dimensions: {
    id: {
      sql: `id`,
      type: `number`,
      primaryKey: true
    },
  },
  dataSource: `default`
});

