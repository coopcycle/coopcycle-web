var assert = require('assert');
var WebSocket = require('ws');
var Promise = require('promise');

describe('Connect to WebSocket without JWT', function() {
  it('should return 401 Unauthorized', function() {
    return new Promise(function (resolve, reject) {
      var ws = new WebSocket('http://localhost:8000');
      ws.onopen = reject;
      ws.onerror = function(e) {
        assert.equal('unexpected server response (401)', e.message);
      }
      ws.onclose = resolve;
    });
  });
});