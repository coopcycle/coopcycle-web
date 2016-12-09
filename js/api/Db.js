var Sequelize = require('sequelize');
var _ = require('underscore');

var sequelizeOptions = {
  timestamps: false,
  underscored: true,
  freezeTableName: true,
}

module.exports = function(sequelize) {

  var Db = {}

  Db.Courier = sequelize.define('courier', {
    username: Sequelize.STRING,
    email: Sequelize.STRING,
  }, _.extend(sequelizeOptions, {tableName: 'api_user'}));

  Db.Order = sequelize.define('order', {
    status: Sequelize.STRING,
    createdAt: {
      field: 'created_at',
      type: Sequelize.DATE
    },
    updatedAt: {
      field: 'updated_at',
      type: Sequelize.DATE
    },
  }, _.extend(sequelizeOptions, {tableName: 'order_'}));

  Db.Restaurant = sequelize.define('restaurant', {
    name: Sequelize.STRING,
    streetAddress: {
      field: 'street_address',
      type: Sequelize.DATE
    },
    geo: Sequelize.GEOMETRY
  }, _.extend(sequelizeOptions, {tableName: 'restaurant'}));

  Db.DeliveryAddress = sequelize.define('delivery_address', {
    name: Sequelize.STRING,
    streetAddress: {
      field: 'street_address',
      type: Sequelize.DATE
    },
    geo: Sequelize.GEOMETRY
  }, _.extend(sequelizeOptions, {tableName: 'delivery_address'}));

  Db.Order.belongsTo(Db.Restaurant);
  Db.Order.belongsTo(Db.DeliveryAddress);
  Db.Order.belongsTo(Db.Courier);

  return Db;
};