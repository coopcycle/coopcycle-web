cube(`Delivery`, {
  sql: `SELECT d.*, tc.distance, tc.duration FROM public.delivery d JOIN public.task_collection tc ON d.id = tc.id`,
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
    distance: {
      sql: `ROUND(${CUBE}.distance / 1000::numeric, 2)`,
      type: `number`,
    },
  },
  dataSource: `default`
});

