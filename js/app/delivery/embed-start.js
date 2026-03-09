import moment from 'moment'
import DateTimePicker from '../widgets/DateTimePicker'

import './embed-start.scss'

function toPackages(el) {
  const packages = []
  $(`#${el.id}_packages_list`).children().each(function() {
    packages.push({
      type: $(this).find('select').val(),
      quantity: $(this).find('input[type="number"]').val()
    })
  })
  return packages
}

function createPackageForm(el, $list, cb) {
  var counter = $list.data('widget-counter') || $list.children().length
  var newWidget = $list.attr('data-prototype')
  newWidget = newWidget.replace(/__package__/g, counter)
  counter++
  $list.data('widget-counter', counter)
  var newElem = $(newWidget)
  newElem.find('input[type="number"]').val(1)
  newElem.find('input[type="number"]').on('change', () => {
    if (cb && typeof cb === 'function') cb(toPackages(el))
  })
  newElem.appendTo($list)
}

function createPackagesWidget(el, packagesRequired, cb) {
  const isNew = document.querySelectorAll(`#${el.id}_packages .delivery__form__packages__list-item`).length === 0
  if (isNew && packagesRequired) {
    createPackageForm(el, $(`#${el.id}_packages_list`), cb)
  }
  $(`#${el.id}_packages_add`).click(function() {
    const selector = $(this).attr('data-target')
    createPackageForm(el, $(selector), cb)
    if (cb && typeof cb === 'function') cb(toPackages(el))
  })
  $(`#${el.id}_packages`).on('click', '[data-delete]', function() {
    const $target = $($(this).attr('data-target'))
    if ($target.length === 0) return
    const $list = $target.parent()
    if (packagesRequired && $list.children().length === 1) return
    $target.remove()
    if (cb && typeof cb === 'function') cb(toPackages(el))
  })
  $(`#${el.id}_packages`).on('change', 'select', function() {
    if (cb && typeof cb === 'function') cb(toPackages(el))
  })
}

const getDateTimePickerContainer = trigger => trigger.parentNode

const taskForms = Array
  .from(document.querySelectorAll('[data-form="task"]'))

taskForms.forEach(function(el) {

  const doneBeforeEl = document.querySelector(`#${el.id}_doneBefore`)

  if (doneBeforeEl) {
    new DateTimePicker(document.querySelector(`#${el.id}_doneBefore_widget`), {
      defaultValue: doneBeforeEl.value || moment().format('YYYY-MM-DD HH:mm:ss'),
      getDatePickerContainer: getDateTimePickerContainer,
      getTimePickerContainer: getDateTimePickerContainer,
      onChange: function(date) {
        if (date) {
          document.querySelector(`#${el.id}_doneBefore`).value = date.format('YYYY-MM-DD HH:mm:ss')
        }
      }
    })
  }

  const packages = document.querySelector(`#${el.id}_packages`)

  if (packages) {
    const packagesRequired = JSON.parse(packages.dataset.packagesRequired)
    createPackagesWidget(el, packagesRequired)
  }

})

function setBillingAddressRequired(required) {
  if (required) {
    $('#delivery_billingAddress_streetAddress').prop('required', true)
  } else {
    $('#delivery_billingAddress_streetAddress').prop('required', false)
    $('#delivery_billingAddress_streetAddress').removeAttr('required')
  }
}

$('#billing-address').on('hidden.bs.collapse', function () {
  setBillingAddressRequired(false)
})

$('#billing-address').on('shown.bs.collapse', function () {
  setBillingAddressRequired(true)
})

setBillingAddressRequired(false)
