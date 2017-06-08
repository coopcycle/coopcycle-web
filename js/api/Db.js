var Sequelize = require('sequelize');
var _ = require('underscore');
var unserialize = require('locutus/php/var/unserialize');

var sequelizeOptions = {
  timestamps: false,
  underscored: true,
  freezeTableName: true,
}

var positionGetter = function() {
  var geo = this.getDataValue('geo');
  if (geo) {
    return {
      latitude: geo.coordinates[0],
      longitude: geo.coordinates[1],
    };
  }
}

var rolesGetter = function() {
  var roles = this.getDataValue('roles');
  if (roles) {
    return unserialize(this.getDataValue('roles'));
  }
}

module.exports = function(sequelize) {

  var Db = {}

  Db.User = sequelize.define('user', {
    username: Sequelize.STRING,
    email: Sequelize.STRING,
    roles: Sequelize.STRING,
    username_canonical: Sequelize.STRING,
    email_canonical: Sequelize.STRING,
    password: Sequelize.STRING,
    enabled: Sequelize.BOOLEAN,
  }, _.extend(sequelizeOptions, {
    tableName: 'api_user',
    getterMethods: {
      roles : rolesGetter
    },
  }));

  Db.UserAddress = sequelize.define('user_address', {}, _.extend(sequelizeOptions, {
    tableName: 'api_user_address'
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

  Db.Restaurant = sequelize.define('restaurant', {
    name: Sequelize.STRING
  }, _.extend(sequelizeOptions, {
    tableName: 'restaurant'
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

  Db.Delivery = sequelize.define('delivery', {
    distance: Sequelize.INTEGER,
    duration: Sequelize.INTEGER,
    date: Sequelize.DATE
  }, _.extend(sequelizeOptions, {
    tableName: 'delivery',
  }));

  Db.Address = sequelize.define('address', {
    name: Sequelize.STRING,
    streetAddress: {
      field: 'street_address',
      type: Sequelize.STRING
    },
    geo: Sequelize.GEOMETRY
  }, _.extend(sequelizeOptions, {
    tableName: 'address',
    getterMethods: {
      position : positionGetter
    },
  }));

  Db.Restaurant.belongsToMany(Db.Product, { through: Db.RestaurantProduct });
  Db.Restaurant.belongsTo(Db.Address);

  Db.Delivery.belongsTo(Db.Address, { as: 'originAddress', foreignKey : 'origin_address_id' });
  Db.Delivery.belongsTo(Db.Address, { as: 'deliveryAddress', foreignKey : 'delivery_address_id' });

  Db.Order.belongsToMany(Db.Product, { through: Db.OrderItem });
  Db.Order.belongsTo(Db.Restaurant);
  Db.Order.belongsTo(Db.User, {as: 'customer', foreignKey : 'customer_id' });
  Db.Order.belongsTo(Db.User, {as: 'courier', foreignKey : 'courier_id' });
  Db.Order.hasOne(Db.Delivery);

  Db.User.belongsToMany(Db.Address, { through: Db.UserAddress, foreignKey : 'api_user_id' });
  Db.Address.belongsToMany(Db.User, { through: Db.UserAddress });

  return Db;
};
