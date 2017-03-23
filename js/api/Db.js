var Sequelize = require('sequelize');
var _ = require('underscore');
var unserialize = require('locutus/php/var/unserialize');

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
    roles: Sequelize.STRING,
  }, _.extend(sequelizeOptions, {
    tableName: 'api_user',
    getterMethods: {
      roles : function() {
        return unserialize(this.getDataValue('roles'));
      }
    },
  }));

  Db.Customer = sequelize.define('customer', {
    username: Sequelize.STRING,
    email: Sequelize.STRING,
    roles: Sequelize.STRING,
  }, _.extend(sequelizeOptions, {
    tableName: 'api_user',
    getterMethods: {
      roles : function() {
        return unserialize(this.getDataValue('roles'));
      }
    },
  }));

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
  }, _.extend(sequelizeOptions, {
    tableName: 'order_'
  }));

  Db.OrderItem = sequelize.define('order_item', {
    quantity: Sequelize.INTEGER
  }, _.extend(sequelizeOptions, {
    tableName: 'order_item'
  }));

  var positionGetter = function() {
    var geo = this.getDataValue('geo');

    return {
      latitude: geo.coordinates[0],
      longitude: geo.coordinates[1],
    };
  }

  Db.Restaurant = sequelize.define('restaurant', {
    name: Sequelize.STRING,
    streetAddress: {
      field: 'street_address',
      type: Sequelize.STRING
    },
    geo: Sequelize.GEOMETRY,
  }, _.extend(sequelizeOptions, {
    tableName: 'restaurant',
    getterMethods: {
      position : positionGetter
    },
  }));

  Db.Product = sequelize.define('product', {
    name: Sequelize.STRING,
  }, _.extend(sequelizeOptions, {
    tableName: 'product'
  }));

  Db.RestaurantProduct = sequelize.define('restaurant_product',{
    restaurantId: {
      field: 'restaurant_id',
      type: Sequelize.INTEGER,
      allowNull: false,
      primaryKey: true
    },
    productId: {
      field: 'product_id',
      type: Sequelize.INTEGER,
      allowNull: false,
      primaryKey: true
    }
  }, _.extend(sequelizeOptions, {
    tableName: 'restaurant_product'
  }));

  Db.DeliveryAddress = sequelize.define('deliveryAddress', {
    name: Sequelize.STRING,
    streetAddress: {
      field: 'street_address',
      type: Sequelize.STRING
    },
    geo: Sequelize.GEOMETRY
  }, _.extend(sequelizeOptions, {
    tableName: 'delivery_address',
    getterMethods: {
      position : positionGetter
    },
  }));

  Db.Restaurant.belongsToMany(Db.Product, { through: Db.RestaurantProduct });

  Db.Order.belongsTo(Db.Restaurant);
  Db.Order.belongsTo(Db.DeliveryAddress);
  Db.Order.belongsTo(Db.Courier);
  Db.Order.belongsTo(Db.Customer);

  Db.Order.belongsToMany(Db.Product, { through: Db.OrderItem });

  Db.DeliveryAddress.belongsTo(Db.Customer);
  Db.Customer.hasMany(Db.DeliveryAddress, { as: 'DeliveryAddresses' });

  return Db;
};