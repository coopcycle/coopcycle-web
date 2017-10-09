import OpeningHours from './OpeningHours.jsx'
import React from 'react';
import { render } from 'react-dom';
import moment from 'moment';
import _ from 'underscore';
import autocomplete from '../autocomplete.jsx'

window.initMap = () => autocomplete('restaurant_address');

var entriesCount = $('input[name^="restaurant[openingHours]"]').length;

function onRowAdd() {
  // grab the prototype template
  var newWidget = $('#restaurant_openingHours').attr('data-prototype');
  // replace the "__name__" used in the id and name of the prototype
  // with a number that's unique to your emails
  // end name attribute looks like name="contact[emails][2]"
  newWidget = newWidget.replace(/__name__/g, entriesCount);
  entriesCount++;

  $('#restaurant_openingHours').append(newWidget)
}

function onRowRemove(index) {
  $('#restaurant_openingHours_' + index).remove();
  $('#opening-hours-text > p').get(index).remove()
}

let defaultValue = [];
$('input[name^="restaurant[openingHours]"]').each((index, el) => {
  defaultValue.push($(el).val())
});

var $deliveryService = $('#restaurant_deliveryService_type');
$deliveryService.change(function() {
  // ... retrieve the corresponding form.
  var $form = $(this).closest('form');
  // Simulate form data, but only include the selected sport value.
  var data = {};
  data[$deliveryService.attr('name')] = $deliveryService.val();
  // Submit data via AJAX to the form's action path.
  $.ajax({
    url : $form.attr('action'),
    type: $form.attr('method'),
    data : data,
    success: function(html) {
      // Replace current position field ...
      $('#restaurant_deliveryService_options').replaceWith(
        // ... with the returned one from the AJAX response.
        $(html).find('#restaurant_deliveryService_options')
      );
    }
  });
});


const openingHoursValue = $('#restaurant_openingHours').val();

render(<OpeningHours
  value={defaultValue}
  onChange={(rows) => {
    $('#opening-hours-text').empty();
    _.each(rows, (value, key) => {
      $('#restaurant_openingHours_' + key).val(value);
      const $text = $('<p>').addClass('help-block').text(value);
      $('#opening-hours-text').append($text)
    })
  }}
  onRowAdd={onRowAdd}
  onRowRemove={onRowRemove} />,
  document.getElementById('opening-hours')
);
