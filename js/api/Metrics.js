const lynx = require('lynx')

const Metrics = function Metrics(options) {
  this.namespace = options.namespace
  this.metrics = new lynx(options.host, options.port)
}

Metrics.prototype.withPrefix = function(key) {
  return this.namespace + '.' + key
}

Metrics.prototype.increment = function(key, rate) {
  this.metrics.increment(this.withPrefix(key), rate)
}

Metrics.prototype.decrement = function(key, rate) {
  this.metrics.decrement(this.withPrefix(key), rate)
}

Metrics.prototype.gauge = function(key, value, rate) {
  this.metrics.gauge(this.withPrefix(key), value, rate)
}

module.exports = Metrics
