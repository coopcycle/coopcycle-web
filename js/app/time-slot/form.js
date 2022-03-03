import { debounce } from 'lodash'

import OpeningHoursInput from '../widgets/OpeningHoursInput'

var $previewLoader = $('#preview-loader')
var $form = $('form[name="time_slot"]')

const updatePreview = debounce(() => {
  $previewLoader.removeClass('invisible')
  $('#preview').find('select').prop('disabled', true)
  $.ajax({
    url : $('#preview').data('url'),
    type: $form.attr('method'),
    data : $form.serialize(),
    success: function(data) {
      $('#preview').html(data)
      $('#preview').find('select').prop('disabled', false)
      $previewLoader.addClass('invisible')
    }
  })
}, 500)

const ohEl = document.querySelector('#time_slot_openingHours')

new OpeningHoursInput(ohEl, {
  locale: $('html').attr('lang'),
  rowsWithErrors: JSON.parse(ohEl.dataset.errors),
  behavior: 'time_slot'
});

$('body').on('change', 'form[name="time_slot"] input,select', updatePreview);

updatePreview()
