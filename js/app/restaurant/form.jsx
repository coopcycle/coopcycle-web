import OpeningHours from './OpeningHours.jsx'
import React from 'react'
import { render } from 'react-dom'
import _ from 'underscore'
import autocomplete from '../autocomplete.jsx'
import { Switch } from 'antd'

window.initMap = () => autocomplete('restaurant_address')

var entriesCount = $('input[name^="restaurant[openingHours]"]').length

function onRowAdd() {
  // grab the prototype template
  var newWidget = $('#restaurant_openingHours').attr('data-prototype')
  // replace the "__name__" used in the id and name of the prototype
  // with a number that's unique to your emails
  // end name attribute looks like name="contact[emails][2]"
  newWidget = newWidget.replace(/__name__/g, entriesCount);
  entriesCount++;

  $('#restaurant_openingHours').append(newWidget)
}

function onRowRemove(index) {
  $('#restaurant_openingHours_' + index).remove()
  $('#opening-hours-text > p').get(index).remove()
}

let defaultValue = [];
$('input[name^="restaurant[openingHours]"]').each((index, el) => {
  defaultValue.push($(el).val())
});

var $deliveryService = $('#restaurant_deliveryService_type')
$deliveryService.change(function() {
  // ... retrieve the corresponding form.
  var $form = $(this).closest('form')
  // Simulate form data, but only include the selected sport value.
  var data = {}
  data[$deliveryService.attr('name')] = $deliveryService.val()
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


function renderSwitch($input) {

  const $parent = $input.closest('div.checkbox').parent()

  const $switch = $('<div class="display-inline-block">')
  const $hidden = $('<input>')

  $switch.addClass('switch')

  $hidden
    .attr('type', 'hidden')
    .attr('name', $input.attr('name'))
    .attr('value', $input.attr('value'))

  $parent.prepend($switch)
  $parent.prepend($hidden)

  const checked = $input.is(':checked'),
        disabled = !window.AppData.__isActivable

  $input.closest('div.checkbox').remove()

  render(
    <Switch defaultChecked={ checked }
            checkedChildren={ window.AppData.__i18n['Enabled'] } unCheckedChildren={ window.AppData.__i18n['Disabled'] }
            onChange={(checked) => {
              if (checked) {
                $parent.append($hidden)
              } else {
                $hidden.remove()
              }
            }}
            disabled={disabled}
        />,
    $switch.get(0)
  );

}


$(function() {
  render(<OpeningHours
      value={defaultValue}
      onChange={(rows) => {
        $('#opening-hours-text').empty()
        _.each(rows, (value, key) => {
          $('#restaurant_openingHours_' + key).val(value)
          const $text = $('<p>').addClass('help-block').text(value)
          $('#opening-hours-text').append($text)
        })
      }}
      onRowAdd={onRowAdd}
      onRowRemove={onRowRemove} />,
    document.getElementById('opening-hours')
  );

  let $form = $('form[name="restaurant"]');

  // Render Switch on page load
  $form.find('.switch').each((index, el) => renderSwitch($(el)))

})
