var Sequelize = require('sequelize');
var _ = require('lodash');
var unserialize = require('locutus/php/var/unserialize');

var sequelizeOptions = {
  timestamps: false,
  underscored: true,
  freezeTableName: true,
};

var positionGetter = function() {
  var geo = this.getDataValue('geo');
  if (geo) {
    return {
      latitude: geo.coordinates[0],
      longitude: geo.coordinates[1],
    };
  }
};

var rolesGetter = function() {
  var roles = this.getDataValue('roles');
  if (roles) {
    return unserialize(this.getDataValue('roles'));
  }
};

module.exports = function(sequelize) {

  var Db = {};

  Db.User = sequelize.define('user', {
    username: Sequelize.STRING,
    email: Sequelize.STRING,
    roles: Sequelize.STRING,
    username_canonical: Sequelize.STRING,
    email_canonical: Sequelize.STRING,
    password: Sequelize.STRING,
    enabled: Sequelize.BOOLEAN,
    createdAt: {
      field: 'created_at',
      type: Sequelize.DATE,
      defaultValue: new Date(),
    },
    updatedAt: {
      field: 'updated_at',
      type: Sequelize.DATE,
      defaultValue: new Date(),
    },
  }, _.extend(sequelizeOptions, {
    tableName: 'api_user',
    getterMethods: {
      roles : rolesGetter
    },
  }));

  Db.Customer = sequelize.define('customer', {
    email: Sequelize.STRING,
    email_canonical: Sequelize.STRING,
    subscribed_to_newsletter: {
      type: Sequelize.BOOLEAN,
      defaultValue: false
    },
    createdAt: {
      field: 'created_at',
      type: Sequelize.DATE
    },
    updatedAt: {
      field: 'updated_at',
      type: Sequelize.DATE
    },
  }, _.extend(sequelizeOptions, {
    tableName: 'sylius_customer',
  }));

  Db.UserAddress = sequelize.define('user_address', {}, _.extend(sequelizeOptions, {
    tableName: 'api_user_address'
  }));

  Db.UserRestaurant = sequelize.define('user_restaurant', {}, _.extend(sequelizeOptions, {
    tableName: 'api_user_restaurant'
  }));

  Db.Organization = sequelize.define('organization', {
    name: Sequelize.STRING,
  }, _.extend(sequelizeOptions, {
    tableName: 'organization'
  }));

  Db.Restaurant = sequelize.define('restaurant', {
    type: Sequelize.STRING,
    name: Sequelize.STRING,
    state: {
      type: Sequelize.STRING,
      defaultValue: 'normal'
    },
    stripeConnectRoles: {
      type: Sequelize.JSON,
      defaultValue: ['ROLE_ADMIN'],
      field: 'stripe_connect_roles',
    },
    stripePaymentMethods: {
      type: Sequelize.JSON,
      defaultValue: [],
      field: 'stripe_payment_methods',
    },
    shippingOptionsDays: {
      type: Sequelize.INTEGER,
      defaultValue: 2,
      field: 'shipping_options_days',
    },
    featured: {
      type: Sequelize.BOOLEAN,
      defaultValue: false,
      field: 'featured',
    },
    createdAt: {
      field: 'created_at',
      type: Sequelize.DATE
    },
    updatedAt: {
      field: 'updated_at',
      type: Sequelize.DATE
    },
  }, _.extend(sequelizeOptions, {
    tableName: 'restaurant'
  }));

  Db.Delivery = sequelize.define('delivery', {
    distance: Sequelize.INTEGER,
    duration: Sequelize.INTEGER,
    polyline: Sequelize.STRING,
  }, _.extend(sequelizeOptions, {
    tableName: 'delivery',
  }));

  Db.Address = sequelize.define('address', {
    name: Sequelize.STRING,
    addressLocality: {
      field: 'address_locality',
      type: Sequelize.STRING
    },
    postalCode: {
      field: 'postal_code',
      type: Sequelize.STRING
    },
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

  Db.TaskCollection = sequelize.define('task_collection', {
    type: Sequelize.STRING,
  }, _.extend(sequelizeOptions, {
    tableName: 'task_collection'
  }));

  Db.Restaurant.belongsTo(Db.Address);
  Db.Restaurant.belongsTo(Db.Organization);

  Db.User.belongsToMany(Db.Address, { through: Db.UserAddress, foreignKey: 'api_user_id' });
  Db.User.belongsToMany(Db.Restaurant, { through: Db.UserRestaurant, foreignKey: 'api_user_id' });

  Db.Customer.hasOne(Db.User)

  Db.Address.belongsToMany(Db.User, { through: Db.UserAddress });

  return Db;
};
