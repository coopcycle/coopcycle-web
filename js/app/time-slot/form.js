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

var $collectionHolder;

var $addTagButton = $('#simple-add');
var $newLinkLi = $('<div></div>') /*.append($addTagButton);*/

function addChoiceForm($collectionHolder, $newLinkLi) {
    var prototype = $collectionHolder.data('prototype');

    var index = $collectionHolder.data('index');

    var newForm = prototype;

    newForm = newForm.replace(/__name__/g, index);

    $collectionHolder.data('index', index + 1);

    var $newFormLi = $('<div></div>').append(newForm);
    $newLinkLi.before($newFormLi);
}

$('input[data-name="mode"]').on('click', function() {
  var mode = $(this).attr('data-mode');
  if (mode === 'advanced') {
    $('#simple').addClass('hidden');
    $('input[data-mode="simple"]').prop('checked', false);
    $('input[data-mode="simple"]').closest('btn').removeClass('active');

    $('#advanced').removeClass('hidden');
    $('input[data-mode="advanced"]').closest('btn').addClass('active');
  } else {
    $('#advanced').addClass('hidden');
    $('input[data-mode="advanced"]').prop('checked', false);
    $('input[data-mode="advanced"]').closest('btn').removeClass('active');

    $('#simple').removeClass('hidden');
    $('input[data-mode="simple"]').closest('btn').addClass('active');
  }
});

$collectionHolder = $('#time_slot_choices');

$collectionHolder.append($newLinkLi);

$collectionHolder.data('index', $collectionHolder.find(':input').length);

$addTagButton.on('click', function() {
    addChoiceForm($collectionHolder, $newLinkLi);
});

const ohEl = document.querySelector('#time_slot_openingHours')

new OpeningHoursInput(ohEl, {
  locale: $('html').attr('lang'),
  rowsWithErrors: JSON.parse(ohEl.dataset.errors),
  behavior: 'time_slot'
});

$('body').on('change', 'form[name="time_slot"] input,select', updatePreview);

updatePreview()
