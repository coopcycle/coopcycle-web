var fs = require('fs');
var pm2 = require('pm2');
var Promise = require('promise');
var path = require('path');
var argv = require('minimist')(process.argv.slice(2));

if (!argv.c) {
  console.log('No config file specified');
  process.exit(1);
}

var configFile = argv.c;
var baseURL = argv.h || 'http://coopcycle.dev';

var buffer = fs.readFileSync(configFile);
var config = JSON.parse(buffer.toString('utf8'));

pm2.connect(function(err) {
  if (err) {
    console.error(err);
    process.exit(2);
  }

  console.log('Connected to PM2 daemon');

  var promises = [];
  config.forEach(function(botConfig) {
    console.log('Spawning bot "' + botConfig.username + '"');
    var promise = new Promise(function(resolve, reject) {
      pm2.start({
        name: 'coopcycle-bot-' + botConfig.username,
        cwd: path.normalize(__dirname + '/..'),
        script: './js/bot.js',
        args: [botConfig.username, botConfig.password, botConfig.gpx, baseURL],
      }, function(err, apps) {
        err ? reject() : resolve();
      });
    });
    promises.push(promise);
  });

  Promise.all(promises).then(function(values) {
    console.log('Done, disconnect from PM2');
    pm2.disconnect();
  });
});