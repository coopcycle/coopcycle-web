var YAML = require('js-yaml');
var fs = require('fs');
var path = require('path');
var merge = require('deepmerge');
var _ = require('lodash');

var ConfigLoader = function(filename) {
  this.filename = filename;
  this.dirname = path.dirname(filename);
};

/**
 * Adds a custom type to support tagged services
 * @see https://symfony.com/blog/new-in-symfony-3-4-simpler-injection-of-tagged-services
 * @see https://github.com/nodeca/js-yaml/wiki/Custom-types
 */
var TaggedYamlType = new YAML.Type('!tagged', {
  kind: 'scalar',
  construct: function (data) {
    return data;
  },
});

var SYMFONY_SCHEMA = YAML.Schema.create([ TaggedYamlType ]);

function loadAndMerge(filename, dirname, parent) {

  try {

    var data = YAML.load(fs.readFileSync(filename, 'utf8'), { schema: SYMFONY_SCHEMA });

    var imports = data.imports && Array.isArray(data.imports) ? data.imports : [];
    delete data.imports;

    if (parent) {
      data = merge(data, parent);
    }

    imports.forEach((item) => {
      if (item.resource) {
        data = merge(data, loadAndMerge(dirname + '/' + item.resource, dirname, data));
      }
    });

  } catch (e) {
    throw e;
  }

  return data;
}

function replaceParameters(data, parameters) {

  return _.mapValues(data, function(value, key) {
    if (typeof value === 'string') {
      if (-1 !== value.indexOf('%')) {
        _.each(parameters, function(paramValue, paramKey) {
          var varName = '%' + paramKey + '%';
          if (value.includes(varName)) {
            value = value.replace(varName, paramValue);
          }
        });

        return value;
      }
    } else {
      return replaceParameters(value, parameters);
    }

    return value;
  });
}

ConfigLoader.prototype.load = function() {

  var data = loadAndMerge(this.filename, this.dirname);

  var parameters = data.parameters;
  delete data.parameters;

  parameters['kernel.root_dir'] = path.resolve(this.dirname, '../');
  parameters = replaceParameters(parameters, parameters);

  data = replaceParameters(data, parameters);

  data.parameters = parameters;

  return data;
};

module.exports = ConfigLoader;
