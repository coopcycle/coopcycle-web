cube(`MessengerWithOrder`, {
  sql: `
  	SELECT
  		o.id,
  		t.type,
  		u.username
  	FROM ${User.sql()} u
  	JOIN ${Task.sql()} t ON t.assigned_to = u.id AND t.type = 'DROPOFF'
  	JOIN ${Delivery.sql()} d ON t.delivery_id = d.id
  	JOIN ${Order.sql()} o ON d.order_id = o.id
  `,

  dimensions: {
    id: {
      sql: `id`,
      type: `number`,
      primaryKey: true
    },
    type: {
      type: `string`,
      sql: `type`,
    },
    username: {
      type: `string`,
      sql: `username`,
    },
  },

  dataSource: `default`
});
