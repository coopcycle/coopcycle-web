var YAML = require('js-yaml');
var fs = require('fs');
var path = require('path');
var merge = require('deepmerge')
var _ = require('underscore')

var ConfigLoader = function(filename) {
  this.filename = filename;
  this.dirname = path.dirname(filename);
}

function loadAndMerge(filename, dirname, parent) {

  try {

    var data = YAML.load(fs.readFileSync(filename, 'utf8'));

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

  return _.mapObject(data, function(value, key) {
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

  parameters = replaceParameters(parameters, parameters);

  data = replaceParameters(data, parameters);

  data.parameters = parameters;

  return data;
}

module.exports = ConfigLoader;