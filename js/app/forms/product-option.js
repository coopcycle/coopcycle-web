import { debounce } from 'lodash'
import React from 'react'
import { render } from 'react-dom'

import { OptionGroup } from '../cart/components/ProductOptionsModalContent'

var $previewLoader = $('#preview-loader')
var $form = $('form[name="product_option"]')

const updatePreview = debounce(() => {
  $previewLoader.removeClass('hidden')
  $.ajax({
    url : $('#preview').data('url'),
    type: $form.attr('method'),
    data : $form.serialize(),
    success: function(data) {
      render(<OptionGroup
        index={ 0 }
        option={ data }
        onChange={ () => {} } />, document.getElementById('preview'), () => $previewLoader.addClass('hidden'))
    }
  })
}, 500)

if ($('#product_option_strategy').val() !== 'option_value') {
  $('#product_option_values').find('[data-shown="option_value"]').hide();
}

$('#product_option_strategy').on('change', function() {
  var value = $(this).val();
  if (value === 'option_value') {
    $('#product_option_values').find('[data-shown="option_value"]').show();
  } else {
    $('#product_option_values').find('[data-shown="option_value"]').hide();
  }
});

$(document).on('click', '[data-delete-row]', function() {
  var target = $(this).data('target');
  $(target).remove();
  updatePreview()
});

$('#add-option-value').on('click', function(e) {

  e.preventDefault();

  var prototype = $('#product_option_values').data('prototype');
  var index = $('#product_option_values').children().length;

  var form = prototype.replace(/__name__/g, index);
  var $form = $(form);

  if ($('#product_option_strategy').val() !== 'option_value') {
    $form.find('[data-shown="option_value"]').hide();
  }

  $form.find("input[name$='[price]']").val(0)

  $form.find('[data-delete-row]').prop('disabled', false)

  $('#product_option_values').append($form);

});

const updateUpperField = (isInfinity) => {
  $('#product_option_valuesRange_upper').prop('disabled', isInfinity);
  $('#product_option_valuesRange_upper').css('color', isInfinity ? 'transparent' : 'inherit');

}

$('#product_option_valuesRange_infinity').on('change', function() {
  updateUpperField($(this).is(':checked'));
});

$('#product_option_additional').on('change', function() {
  var $valuesRange = $('#product_option_valuesRange');
  if ($(this).is(':checked')) {
    $valuesRange.show();
  } else {
    $valuesRange.hide();
  }
});

if (!$('#product_option_additional').is(':checked')) {
  $('#product_option_valuesRange').hide();
}

updateUpperField($('#product_option_valuesRange_infinity').is(':checked'));

$('body').on('change', 'form[name="product_option"] input,select', updatePreview);
// $form.find('input,select').on('change', updatePreview);

updatePreview();
