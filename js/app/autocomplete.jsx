const options = {
  types: ['address'],
  componentRestrictions: {
    country: window.AppData.countryIso || "fr"
  }
};

module.exports = (form, onPlaceChanged) => {

  if (!window.google) {
    return;
  }

  var addressInput = document.getElementById(form + '_streetAddress');
  var latitudeInput = document.getElementById(form + '_latitude');
  var longitudeInput = document.getElementById(form + '_longitude');

  if (!addressInput) {
    return;
  }

  const inputMap = {
    postal_code: form + '_postalCode',
    locality: form + '_addressLocality'
  };

  google.maps.event.clearListeners(addressInput, 'place_changed');
  const autocomplete = new google.maps.places.Autocomplete(addressInput, options);

  autocomplete.addListener('place_changed', function() {
    const place = autocomplete.getPlace();
    if (!place.geometry) {
      return;
    }

    latitudeInput.value = place.geometry.location.lat();
    longitudeInput.value = place.geometry.location.lng();
    for (var i = 0; i < place.address_components.length; i++) {
      var addressType = place.address_components[i].types[0];
      var value = place.address_components[i].long_name;
      if (inputMap[addressType]) {
        $('#' + inputMap[addressType]).val(value);
      }
    }

    if (typeof onPlaceChanged !== 'undefined' && typeof onPlaceChanged === 'function') {
      onPlaceChanged(place);
    }
  });
};
