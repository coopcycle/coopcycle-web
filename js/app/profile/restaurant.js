var inputMap = {
  postal_code: 'restaurant_postalCode',
  locality: 'restaurant_addressLocality'
};

var placeChangedListener;

window.initMap = function() {

  var addressInput = document.getElementById('restaurant_streetAddress');
  var latitudeInput = document.getElementById('restaurant_latitude');
  var longitudeInput = document.getElementById('restaurant_longitude');

  if (!addressInput) {
    return;
  }

  var options = {
    types: ['address'],
    componentRestrictions: {
      country: "fr"
    }
  };

  if (placeChangedListener) {
    google.maps.event.removeListener(placeChangedListener);
  }

  var autocomplete = new google.maps.places.Autocomplete(addressInput, options);

  placeChangedListener = autocomplete.addListener('place_changed', function() {
    var place = autocomplete.getPlace();
    latitudeInput.value = place.geometry.location.lat();
    longitudeInput.value = place.geometry.location.lng();
    for (var i = 0; i < place.address_components.length; i++) {
      var addressType = place.address_components[i].types[0];
      var value = place.address_components[i].long_name;
      if (inputMap[addressType]) {
        $('#' + inputMap[addressType]).val(value);
      }
    }
  });
}
