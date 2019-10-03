import React from 'react'
import { render } from 'react-dom'
import moment from 'moment'
import ClipboardJS from 'clipboard'
import { createStore } from 'redux'
import _ from 'lodash'

import AddressBook from '../delivery/AddressBook'
import DateTimePicker from '../widgets/DateTimePicker'
import TagsInput from '../widgets/TagsInput'

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

function createAddressWidget(name, type, cb) {

  new AddressBook(document.querySelector(`#${name}_${type}_address`), {
    existingAddressControl: document.querySelector(`#${name}_${type}_address_existingAddress`),
    newAddressControl: document.querySelector(`#${name}_${type}_address_newAddress_streetAddress`),
    isNewAddressControl: document.querySelector(`#${name}_${type}_address_isNewAddress`),
    moreOptionsContainer: document.querySelector(`#${name}_${type}_address_more_options`),
    onReady: address => {
      cb(address)
    },
    onChange: address => {
      store.dispatch({
        type: 'SET_ADDRESS',
        taskType: type,
        address
      })
    }
  })
}

function getDatePickerValue(name, type) {
  const datePickerEl = document.querySelector(`#${name}_${type}_doneBefore`)
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

function parseWeight(value) {
  const intValue = parseInt((value || 0), 10)
  if (NaN === intValue) {
    return 0
  }

  return intValue
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
      weight: parseWeight(weightEl.value),
      pickup: {
        address: null,
        [ getDatePickerKey(name, 'pickup') ]: getDatePickerValue(name, 'pickup')
      },
      dropoff: {
        address: null,
        [ getDatePickerKey(name, 'dropoff') ]: getDatePickerValue(name, 'dropoff')
      }
    }

    createAddressWidget(name, 'pickup', address => preloadedState.pickup.address = address)
    createDatePickerWidget(name, 'pickup')

    createAddressWidget(name, 'dropoff', address => preloadedState.dropoff.address = address)
    createDatePickerWidget(name, 'dropoff')

    store = createStore(reducer, preloadedState)

    onReady(preloadedState)
    const unsubscribe = store.subscribe(() => onChange(store.getState()))

    weightEl.addEventListener('input', _.debounce(e => {
      store.dispatch({
        type: 'SET_WEIGHT',
        value: parseWeight(e.target.value)
      })
    }, 350))

    const pickupTagsEl = document.querySelector(`#${name}_pickup_tagsAsString`)
    const dropoffTagsEl = document.querySelector(`#${name}_dropoff_tagsAsString`)

    if (pickupTagsEl && dropoffTagsEl) {
      $.getJSON(window.Routing.generate('admin_tags', { format: 'json' }), function(tags) {
        createTagsWidget(name, 'pickup', tags)
        createTagsWidget(name, 'dropoff', tags)
      })
    }

    new ClipboardJS('#copy', {
      text: function(trigger) {
        return document.getElementById('tracking_link').getAttribute('href')
      }
    })

    const packages = document.querySelector(`#${name}_packages`)

    if (packages) {

      const addPackageBtn = document.querySelector(`#${name}_packages_add`)

      $(`#${name}_packages_add`).click(function(e) {

        var list = $($(this).attr('data-target'))

        var counter = list.data('widget-counter') || list.children().length
        var newWidget = list.attr('data-prototype')

        newWidget = newWidget.replace(/__name__/g, counter)

        counter++
        list.data('widget-counter', counter)

        var newElem = $(newWidget)
        newElem.find('input[type="number"]').val(1)
        newElem.appendTo(list)
      })

      $(`#${name}_packages`).on('click', '[data-delete]', function(e) {
        const $target = $($(this).attr('data-target'))
        if ($target.length > 0) {
          $target.remove()
        }
      })
    }

  }

  return form
}
