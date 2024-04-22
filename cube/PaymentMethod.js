cube(`PaymentMethod`, {
  sql_table: `public.sylius_payment_method`,
  joins: {
    /*
    Order: {
      relationship: `many_to_one`,
      sql: `${CUBE}.order_id = ${Order}.id`
    },
    /*
    Refund: {
      relationship: `one_to_many`,
      sql: `${CUBE}.id = ${Refund}.payment_id`
    },
    */
    Payment: {
      relationship: `many_to_one`,
      sql: `${CUBE}.id = ${Payment}.method_id`
    },
  },
  dimensions: {
    id: {
      sql: `id`,
      type: `number`,
      primaryKey: true
    },
    code: {
      sql: `code`,
      type: `string`,
    },
  },
  dataSource: `default`
});
