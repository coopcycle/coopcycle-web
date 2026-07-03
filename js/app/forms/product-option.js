import { debounce } from 'lodash'
import React from 'react'
import { createRoot } from 'react-dom/client'
import { Cascader } from 'antd';
import _ from 'lodash';

import { OptionGroup } from '../restaurant/components/ProductDetails/ProductOptionGroup'
import Search from '../widgets/Search'

var $previewLoader = $('#preview-loader')
var $form = $('form[name="product_option"]')

const previewRoot = createRoot(document.getElementById('preview'))

const updatePreview = debounce(() => {
  $previewLoader.removeClass('hidden')
  $.ajax({
    url : $('#preview').data('url'),
    type: $form.attr('method'),
    data : $form.serialize(),
    success: function(data) {
      previewRoot.render(<OptionGroup
        index={ 0 }
        option={ data }
        onChange={ () => {} } />)
      setTimeout(() => $previewLoader.addClass('hidden'), 0);
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

function createProductSearch(el) {
  const placeholder = el.dataset.placeholder;
  const initialValue = el.dataset.searchInitialValue;
  const productInput = document.querySelector(el.dataset.product);
  const productOptionValueInput = document.querySelector(`${el.dataset.productOptionValue} > input[type="text"]`);

  new Search(el, {
    url: document.querySelector('form[name="product_option"]').dataset.searchProductsUrl,
    placeholder: placeholder,
    initialValue: initialValue,
    onSuggestionSelected: function (suggestion) {
      productInput.value = suggestion.id;
      // If the option value is empty, use the product name
      if (!productOptionValueInput.value) {
        productOptionValueInput.value = suggestion.name;
      }
    }
  });
}

function createProductOptionSearch(el) {

  const httpClient = new window._auth.httpClient();

  const targetEl = document.querySelector(el.dataset.target)

  const optionValueIds = Array.from(targetEl.querySelectorAll('input[type="hidden"]')).map((input) => input.value);
  const excludeProductOptionId = el.dataset.exclude
  const placeholder = el.dataset.placeholder

  const productOptions = JSON.parse(document.querySelector('form[name="product_option"]').dataset.productOptions)

  // Don't allow self referencing
  const otherProductOptions = _.filter(productOptions, (opt) => opt['@id'] !== excludeProductOptionId);
  const cascaderOptions = otherProductOptions.map((opt) => {
    return {
      value: opt['@id'],
      label: opt.name,
      disableCheckbox: true,
      children: opt.values.map((optVal) => ({
        value: optVal['@id'],
        label: optVal.value,
      }))
    }
  })

  const defaultValue = optionValueIds.map(optionValueId => {
    const productOption = _.find(productOptions, (opt) => {
      const optionValueIds = opt.values.map((optVal) => optVal['@id'])
      return optionValueIds.includes(optionValueId);
    })

    return [ productOption['@id'], optionValueId ]
  })

  createRoot(el).render(<Cascader
    multiple={true}
    defaultValue={defaultValue}
    options={cascaderOptions}
    onChange={(values) => {
      // Clear the element
      targetEl.innerHTML = '';
      values.forEach((value, index) => {

        const [, optionValueId] = value

        const container = document.createElement('div')
        container.innerHTML = targetEl.dataset.prototype.replace(/__name__/g, index);

        const input = container.querySelector(':first-child');
        input.value = optionValueId

        targetEl.appendChild(input)
      })
    }}
    placeholder={placeholder} />)
}

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

  createProductSearch($form.find('[data-search="product"]')[0])
  createProductOptionSearch($form.find('[data-search="product-option"]')[0])

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

document.querySelectorAll('[data-search="product"]').forEach((el) => createProductSearch(el))
document.querySelectorAll('[data-search="product-option"]').forEach((el) => createProductOptionSearch(el))

updateUpperField($('#product_option_valuesRange_infinity').is(':checked'));

$('body').on('change', 'form[name="product_option"] input,select', updatePreview);
// $form.find('input,select').on('change', updatePreview);

updatePreview();
