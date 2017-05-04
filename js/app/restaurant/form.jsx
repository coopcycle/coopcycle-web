import OpeningHours from './OpeningHours.jsx'
import React from 'react';
import { render } from 'react-dom';
import moment from 'moment';

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



var entriesCount = $('input[name^="restaurant[openingHours]"]').length

function onRowAdd() {
  // grab the prototype template
  var newWidget = $('#opening-hours').attr('data-prototype');
  // replace the "__name__" used in the id and name of the prototype
  // with a number that's unique to your emails
  // end name attribute looks like name="contact[emails][2]"
  newWidget = newWidget.replace(/__name__/g, entriesCount);
  entriesCount++;

  $('form[name="restaurant"]').append(newWidget)
}


let defaultValue = []
$('input[name^="restaurant[openingHours]"]').each((index, el) => {
  defaultValue.push($(el).val())
})

const openingHoursValue = $('#restaurant_openingHours').val()

render(<OpeningHours
  value={defaultValue}
  onChange={(key, value) => $('#restaurant_openingHours_' + key).val(value)} //$('#restaurant_openingHours').val(JSON.stringify(value))}
  onRowAdd={onRowAdd} />,
  document.getElementById('opening-hours')
);
