module.exports = {
  resolveKey: function(prefix, key) {
    switch (typeof key) {
      case 'string' :
        if (!key.startsWith(prefix + ':')) {
          return prefix + ':' + key;
        }
        return key;
        break;
      case 'number' :
        return prefix + ':' + key;
        break;
      default :
        return key;
    }
  },
  keyAsInt: function(key) {
    return parseInt(key.split(':')[1], 10);
  }
};