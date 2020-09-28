import moment from 'moment'
import ClipboardJS from 'clipboard'
import { createStore } from 'redux'
import _ from 'lodash'

import AddressBook from '../delivery/AddressBook'
import DateTimePicker from '../widgets/DateTimePicker'
import TagsInput from '../widgets/TagsInput'
import { validateForm } from '../utils/address'

class DeliveryForm {
  disable() {
    $('#delivery-submit').attr('disabled', true)
    $('#loader').removeClass('hidden')
  }
  enable() {
    $('#delivery-submit').attr('disabled', false)
    $('#loader').addClass('hidden')
  }
}

let store

function setPackages(name) {
  const packages = []
  $(`#${name}_packages_list`).children().each(function() {
    packages.push({
      type: $(this).find('select').val(),
      quantity: $(this).find('input[type="number"]').val()
    })
  })

  store.dispatch({
    type: 'SET_PACKAGES',
    packages
  })
}

function createAddressWidget(name, type, cb) {

  const telephone = document.querySelector(`#${name}_${type}_telephone`)
  const recipient = document.querySelector(`#${name}_${type}_recipient`)

  const isTelephoneRequired = telephone && telephone.hasAttribute('required')
  const isRecipientRequired = recipient && recipient.hasAttribute('required')

  new AddressBook(document.querySelector(`#${name}_${type}_address`), {
    existingAddressControl: document.querySelector(`#${name}_${type}_address_existingAddress`),
    newAddressControl: document.querySelector(`#${name}_${type}_address_newAddress_streetAddress`),
    isNewAddressControl: document.querySelector(`#${name}_${type}_address_isNewAddress`),
    onReady: address => {
      cb(address)
    },
    onChange: address => {

      if (Object.prototype.hasOwnProperty.call(address, '@id')) {
        if (telephone) {
          telephone.value = ''
          telephone.removeAttribute('required')
          telephone.closest('.form-group').classList.add('hidden')
        }
        if (recipient) {
          recipient.value = ''
          recipient.removeAttribute('required')
          recipient.closest('.form-group').classList.add('hidden')
        }
      } else {
        if (telephone) {
          telephone.setAttribute('required', isTelephoneRequired)
          telephone.closest('.form-group').classList.remove('hidden')
        }
        if (recipient) {
          recipient.setAttribute('required', isRecipientRequired)
          recipient.closest('.form-group').classList.remove('hidden')
        }
      }

      store.dispatch({
        type: 'SET_ADDRESS',
        taskType: type,
        address
      })
    }
  })
}

function getDatePickerValue(name, type) {
  const timeSlotEl = document.querySelector(`#${name}_${type}_timeSlot`)

  if (timeSlotEl) {
    return $(`#${name}_${type}_timeSlot`).val()
  }

  return moment($(`#${name}_${type}_doneBefore`).val(), 'YYYY-MM-DD HH:mm:ss').format()
}

function getDatePickerKey(name, type) {
  const timeSlotEl = document.querySelector(`#${name}_${type}_timeSlot`)
  if (timeSlotEl) {
    return 'timeSlot'
  }

  return 'before'
}

function createDatePickerWidget(name, type) {

  const datePickerEl = document.querySelector(`#${name}_${type}_doneBefore`)
  const timeSlotEl = document.querySelector(`#${name}_${type}_timeSlot`)

  if (timeSlotEl) {
    timeSlotEl.addEventListener('change', e => {
      store.dispatch({
        type: 'SET_TIME_SLOT',
        taskType: type,
        value: e.target.value
      })
    })
    return
  }

  new DateTimePicker(document.querySelector(`#${name}_${type}_doneBefore_widget`), {
    defaultValue: datePickerEl.value,
    onChange: function(date) {
      datePickerEl.value = date.format('YYYY-MM-DD HH:mm:ss')
      store.dispatch({
        type: 'SET_BEFORE',
        taskType: type,
        value: date.format()
      })
    }
  })
}

function createTagsWidget(name, type, tags) {
  new TagsInput(document.querySelector(`#${name}_${type}_tagsAsString_widget`), {
    tags,
    defaultValue: [],
    onChange: function(tags) {
      var slugs = tags.map(tag => tag.slug)
      document.querySelector(`#${name}_${type}_tagsAsString`).value = slugs.join(' ')
    }
  })
}

function createPackageForm(name, $list) {

  var counter = $list.data('widget-counter') || $list.children().length
  var newWidget = $list.attr('data-prototype')

  newWidget = newWidget.replace(/__name__/g, counter)

  counter++
  $list.data('widget-counter', counter)

  var newElem = $(newWidget)
  newElem.find('input[type="number"]').val(1)
  newElem.find('input[type="number"]').on('change', () => setPackages(name))
  newElem.appendTo($list)
}


function createPackagesWidget(name, packagesRequired) {

  if (packagesRequired) {
    createPackageForm(
      name,
      $(`#${name}_packages_list`)
    )
  }

  $(`#${name}_packages_add`).click(function() {
    const selector = $(this).attr('data-target')
    createPackageForm(
      name,
      $(selector)
    )
    setPackages(name)
  })

  $(`#${name}_packages`).on('click', '[data-delete]', function() {
    const $target = $($(this).attr('data-target'))

    if ($target.length === 0) {
      return
    }

    const $list = $target.parent()

    if (packagesRequired && $list.children().length === 1) {
      return
    }

    $target.remove()
    setPackages(name)
  })

  $(`#${name}_packages`).on('change', 'select', function() {
    setPackages(name)
  })
}

function parseWeight(value) {

  const floatValue = parseFloat((value || '0.0'))
  if (isNaN(floatValue)) {
    return 0
  }

  return parseInt((floatValue * 1000), 10)
}

function reducer(state = {}, action) {
  switch (action.type) {
  case 'SET_ADDRESS':
    return {
      ...state,
      [ action.taskType ]: {
        ...state[action.taskType],
        address: action.address
      }
    }
  case 'SET_TIME_SLOT':
    return {
      ...state,
      [ action.taskType ]: {
        ...state[action.taskType],
        timeSlot: action.value
      }
    }
  case 'SET_BEFORE':
    return {
      ...state,
      [ action.taskType ]: {
        ...state[action.taskType],
        before: action.value
      }
    }
  case 'SET_WEIGHT':
    return {
      ...state,
      weight: action.value
    }
  case 'SET_PACKAGES':
    return {
      ...state,
      packages: action.packages
    }
  default:
    return state
  }
}

export default function(name, options) {

  const el = document.querySelector(`form[name="${name}"]`)

  const form = new DeliveryForm()

  const onChange = options.onChange.bind(form)
  const onReady = options.onReady.bind(form)

  if (el) {

    const weightEl = document.querySelector(`#${name}_weight`)

    // Intialize Redux store
    let storeId
    if (el.dataset.store) {
      storeId = el.dataset.store
    }
    let preloadedState = {
      store: `/api/stores/${storeId}`, // FIXME The data attribute should contain the IRI
      weight: weightEl ? parseWeight(weightEl.value) : 0,
      pickup: {
        address: null,
        [ getDatePickerKey(name, 'pickup') ]: getDatePickerValue(name, 'pickup')
      },
      dropoff: {
        address: null,
        [ getDatePickerKey(name, 'dropoff') ]: getDatePickerValue(name, 'dropoff')
      },
      packages: []
    }

    createAddressWidget(name, 'pickup', address => preloadedState.pickup.address = address)
    createDatePickerWidget(name, 'pickup')

    createAddressWidget(name, 'dropoff', address => preloadedState.dropoff.address = address)
    createDatePickerWidget(name, 'dropoff')

    store = createStore(reducer, preloadedState)

    onReady(preloadedState)
    store.subscribe(() => onChange(store.getState()))

    if (weightEl) {
      weightEl.addEventListener('input', _.debounce(e => {
        store.dispatch({
          type: 'SET_WEIGHT',
          value: parseWeight(e.target.value)
        })
      }, 350))
    }

    const pickupTagsEl = document.querySelector(`#${name}_pickup_tagsAsString`)
    const dropoffTagsEl = document.querySelector(`#${name}_dropoff_tagsAsString`)

    if (pickupTagsEl && dropoffTagsEl) {
      $.getJSON(window.Routing.generate('admin_tags', { format: 'json' }), function(tags) {
        createTagsWidget(name, 'pickup', tags)
        createTagsWidget(name, 'dropoff', tags)
      })
    }

    new ClipboardJS('#copy', {
      text: function() {
        return document.getElementById('tracking_link').getAttribute('href')
      }
    })

    const packages = document.querySelector(`#${name}_packages`)

    if (packages) {
      const packagesRequired = JSON.parse(packages.dataset.packagesRequired)
      createPackagesWidget(name, packagesRequired)
    }

    el.addEventListener('submit', (e) => {

      _.find(['pickup', 'dropoff'], type => {

        const isNewAddrInput = document.querySelector(`#${name}_${type}_address_isNewAddress`)
        if (!isNewAddrInput) {
          return false
        }

        const searchInput = document.querySelector(`#${name}_${type}_address input[type="search"]`);
        const latInput = document.querySelector(`#${name}_${type}_address [data-address-prop="latitude"]`)
        const lngInput = document.querySelector(`#${name}_${type}_address [data-address-prop="longitude"]`)
        const streetAddrInput = document.querySelector(`#${name}_${type}_address_newAddress_streetAddress`)

        const isValid = validateForm(e, searchInput, latInput, lngInput, streetAddrInput)

        return !isValid
      })

    }, false)

  }

  return form
}
