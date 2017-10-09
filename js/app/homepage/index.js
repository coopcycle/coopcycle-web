var geohash = require('ngeohash');

var geohashValue = localStorage.getItem('search_geohash');
var addressValue = localStorage.getItem('search_address');

window.initMap = function() {

  var addressInput = document.getElementById('address-search');
  var geohashInput = document.querySelector('input[name="geohash"]');
  var submitButton = document.querySelector('button[type="submit"]');
  // var address = document.getElementById('address');

  var options = {
    types: ['address'],
    componentRestrictions: {
      country: "fr"
    }
  };
  var autocomplete = new google.maps.places.Autocomplete(addressInput, options);

  $('#address-search-form').on('submit', function(e) {
    if (geohashInput.value === '') {
      addressInput.focus();
      return false;
    }

    localStorage.setItem('search_geohash', geohashValue);
    localStorage.setItem('search_address', addressValue);

    return true;
  });

  autocomplete.addListener('place_changed', function() {

    var place = autocomplete.getPlace();

    if (!place.geometry) {
      window.alert("Autocomplete's returned place contains no geometry");
      return;
    }

    $(submitButton).removeClass('disabled');

    var lat = place.geometry.location.lat();
    var lng = place.geometry.location.lng();

    geohashInput.value = geohashValue = geohash.encode(lat, lng, 11);

    if (place.address_components) {
      addressValue = [
        (place.address_components[0] && place.address_components[0].short_name || ''),
        (place.address_components[1] && place.address_components[1].short_name || ''),
        (place.address_components[2] && place.address_components[2].short_name || '')
      ].join(' ');
    }

  });

  if (geohashValue && addressValue) {
    geohashInput.value = geohashValue;
    addressInput.value = addressValue;
    $(submitButton).removeClass('disabled');
  } else {
    setTimeout(function() { addressInput.focus() }, 750);
  }
};