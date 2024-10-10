import React from 'react'
import moment from 'moment'
import ClipboardJS from 'clipboard'
import _ from 'lodash'
import axios from 'axios'
import { configureStore } from '@reduxjs/toolkit'
import { createSelector } from 'reselect'
import { createRoot } from 'react-dom/client'
import { Provider } from 'react-redux'
import { I18nextProvider } from 'react-i18next'

import AddressBook from '../delivery/AddressBook'
import DateTimePicker from '../widgets/DateTimePicker'
import DateRangePicker from '../widgets/DateRangePicker'
import { validateForm } from '../utils/address'
import i18n from '../i18n'
import { RecurrenceRules } from './components/RecurrenceRules'
import tasksSlice from './redux/tasksSlice'
import {
  recurrenceSlice,
  selectRecurrenceRule,
} from './redux/recurrenceSlice'
import { storeSlice } from './redux/storeSlice'
import { suggestionsSlice, showSuggestions, acceptSuggestions, rejectSuggestions } from './redux/suggestionsSlice'
import TagsSelect from '../components/TagsSelect'
import SuggestionModal from './components/SuggestionModal'

const selectTasks = state => state.tasks

const selectLastDropoff = createSelector(
  selectTasks,
  (tasks) => {
    return _.findLast(tasks, t => t.type === 'DROPOFF')
  }
)

const collectionHolder = document.querySelector('#delivery_tasks')

const domIndex = (el) => Array.prototype.indexOf.call(el.parentNode.children, el)

let reduxStore

class DeliveryForm {
  disable() {
    // do not use `disabled` attribute to disable the buttons as it will prevent the button value from being sent to the server
    $('button[type="submit"]').addClass('pointer-events-none')
    $('#loader').removeClass('hidden')
  }
  enable() {
    $('button[type="submit"]').removeClass('pointer-events-none')
    $('#loader').addClass('hidden')
  }
  showSuggestions(suggestions) {
    reduxStore.dispatch(showSuggestions(suggestions))
  }
}

function reorder(suggestedOrder) {

  // To reorder, we use the removeChild() function,
  // which removes an element and returns it but preserves event listeners.
  // Then, we re-add the elements in the expected order.

  const taskEls = []
  while (collectionHolder.firstElementChild) {
    taskEls.push(collectionHolder.removeChild(collectionHolder.firstElementChild));
  }

  suggestedOrder.forEach((oldIndex) => {
    collectionHolder.appendChild(taskEls[oldIndex])
  })

  collectionHolder.children.forEach((el, index) => {
    el.querySelector('[data-position]').value = '' + index
  })
}

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

function hideRememberAddress(el) {
  const rememberAddr = document.querySelector(`#${el.id}_address_rememberAddress`)
  if (rememberAddr) {
    rememberAddr.closest('.checkbox').classList.add('hidden')
  }
}

function showRememberAddress(el) {
  const rememberAddr = document.querySelector(`#${el.id}_address_rememberAddress`)
  if (rememberAddr) {
    rememberAddr.closest('.checkbox').classList.remove('hidden')
  }
}

function createAddressWidget(el, cb) {

  new AddressBook(document.querySelector(`#${el.id}_address`), {
    allowSearchSavedAddresses: true,
    existingAddressControl: document.querySelector(`#${el.id}_address_existingAddress`),
    newAddressControl: document.querySelector(`#${el.id}_address_newAddress_streetAddress`),
    isNewAddressControl: document.querySelector(`#${el.id}_address_isNewAddress`),
    duplicateAddressControl: document.querySelector(`#${el.id}_address_duplicateAddress`),
    // Fields containing address details
    nameControl: document.querySelector(`#${el.id}_address_name`),
    telephoneControl: document.querySelector(`#${el.id}_address_telephone`),
    contactNameControl: document.querySelector(`#${el.id}_address_contactName`),
    onReady: address => {
      cb(address)
      if (Object.prototype.hasOwnProperty.call(address, '@id')) {
        hideRememberAddress(el)
      }
    },
    onChange: address => {

      if (Object.prototype.hasOwnProperty.call(address, '@id')) {
        hideRememberAddress(el)
      } else {
        showRememberAddress(el)
      }

      reduxStore.dispatch({
        type: 'SET_ADDRESS',
        taskIndex: domIndex(el),
        value: address
      })
    },
    onClear: () => {
      showRememberAddress(el)
      reduxStore.dispatch({
        type: 'CLEAR_ADDRESS',
        taskIndex: domIndex(el),
      })
    }
  })
}

function getTimeWindowProps(el) {

  const timeSlotEl = document.querySelector(`#${el.id}_timeSlot`)
  if (timeSlotEl) {
    return {
      timeSlot: $(timeSlotEl).val()
    }
  }

  const before = $(`#${el.id}_doneBefore`).val() || selectLastDropoff(reduxStore.getState()).before

  const after = $(`#${el.id}_doneAfter`).val()
  if (!after) {
    return {
      before: moment(before, 'YYYY-MM-DD HH:mm:ss').format(),
    }
  }

  return {
    after: moment(after, 'YYYY-MM-DD HH:mm:ss').format(),
    before: moment(before, 'YYYY-MM-DD HH:mm:ss').format(),
  }
}

function getTaskType(el) {
  return document.querySelector(`#${el.id}_type`).value.toUpperCase()
}

function createDateRangePickerWidget(el) {
  const doneBeforePickerEl = document.querySelector(`#${el.id}_doneBefore`)
  const doneAfterPickerEl = document.querySelector(`#${el.id}_doneAfter`)

  const beforeDefaultValue = doneBeforePickerEl.value || selectLastDropoff(reduxStore.getState()).before
  const afterDefaultValue = doneAfterPickerEl.value || moment().set({ hour: 0, minute: 0, second: 0 }).format('YYYY-MM-DD HH:mm:ss')

  // When adding a new task, initialize hidden input value
  if (!doneBeforePickerEl.value) {
    doneBeforePickerEl.value = moment(beforeDefaultValue).format('YYYY-MM-DD HH:mm:ss')
  }

  if (!doneAfterPickerEl.value) {
    doneAfterPickerEl.value = moment(afterDefaultValue).format('YYYY-MM-DD HH:mm:ss')
  }

  const defaultValue = {
    after: afterDefaultValue,
    before: beforeDefaultValue,
  }

  new DateRangePicker(document.querySelector(`#${el.id}_doneBefore_widget`), {
    defaultValue,
    showTime: true,
    onChange: function({after, before}) {
      doneAfterPickerEl.value = after.format('YYYY-MM-DD HH:mm:ss')
      doneBeforePickerEl.value = before.format('YYYY-MM-DD HH:mm:ss')

      reduxStore.dispatch({
        type: 'SET_BEFORE',
        taskIndex: domIndex(el),
        value: before.format()
      })

      reduxStore.dispatch({
        type: 'SET_AFTER',
        taskIndex: domIndex(el),
        value: after.format()
      })
    }
  })
}

function createDatePickerWidget(el, isAdmin = false) {

  const datePickerEl = document.querySelector(`#${el.id}_doneBefore`)
  const timeSlotEl = document.querySelector(`#${el.id}_timeSlot`)

  if (timeSlotEl) {
    timeSlotEl.addEventListener('change', e => {
      reduxStore.dispatch({
        type: 'SET_TIME_SLOT',
        taskIndex: domIndex(el),
        value: e.target.value
      })
    })
    return
  }

  if (isAdmin) {
    createDateRangePickerWidget(el)
    return
  }

  const defaultValue = datePickerEl.value || selectLastDropoff(reduxStore.getState()).before

  // When adding a new task, initialize hidden input value
  if (!datePickerEl.value) {
    datePickerEl.value = moment(defaultValue).format('YYYY-MM-DD HH:mm:ss')
  }

  new DateTimePicker(document.querySelector(`#${el.id}_doneBefore_widget`), {
    defaultValue,
    onChange: function(date) {
      datePickerEl.value = date.format('YYYY-MM-DD HH:mm:ss')
      reduxStore.dispatch({
        type: 'SET_BEFORE',
        taskIndex: domIndex(el),
        value: date.format()
      })
    }
  })
}

function createTagsWidget(el, tags) {
  const initialValue = document.querySelector(`#${el.id}_tagsAsString`).value

  const root = createRoot(document.querySelector(`#${el.id}_tagsAsString_widget`))
  root.render(
    <TagsSelect
      defaultValue={initialValue ?? ''}
      isMulti
      onChange={tags => {
        let slugs = tags.map(tag => tag.slug)
        document.querySelector(`#${el.id}_tagsAsString`).value = slugs.join(' ')
      }}
      tags={tags}
    />,
  )
}

function createSwitchTimeSlotWidget(el) {
  const switchTimeSlotEl = document.querySelector(`#${el.id}_switchTimeSlot`)
  const timeSlotEl = document.querySelector(`#${el.id}_timeSlot`)

  if (switchTimeSlotEl && timeSlotEl) {
    switchTimeSlotEl.querySelectorAll('input[type="radio"]').forEach(rad => {
      rad.addEventListener('change', function(e) {

        const choices = JSON.parse(e.target.dataset.choices)

        timeSlotEl.innerHTML = ''
        choices.forEach(choice => {
          const opt = document.createElement('option')
          opt.value = choice.value
          opt.innerHTML = choice.label
          timeSlotEl.appendChild(opt)
        })

        reduxStore.dispatch({
          type: 'SET_TIME_SLOT',
          taskIndex: domIndex(el),
          value: timeSlotEl.value
        })
      })
    })
  }
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
    if (cb && typeof cb === 'function') {
      cb(toPackages(el))
    }
  })
  newElem.appendTo($list)
}

export function createPackagesWidget(el, packagesRequired, cb) {

  const isNew = document.querySelectorAll(`#${el.id}_packages .delivery__form__packages__list-item`).length === 0

  if (isNew && packagesRequired) {
    createPackageForm(
      el,
      $(`#${el.id}_packages_list`),
      cb
    )
  }

  $(`#${el.id}_packages_add`).click(function() {
    const selector = $(this).attr('data-target')
    createPackageForm(
      el,
      $(selector),
      cb
    )
    if (cb && typeof cb === 'function') {
      cb(toPackages(el))
    }
  })

  $(`#${el.id}_packages`).on('click', '[data-delete]', function() {
    const $target = $($(this).attr('data-target'))

    if ($target.length === 0) {
      return
    }

    const $list = $target.parent()

    if (packagesRequired && $list.children().length === 1) {
      return
    }

    $target.remove()
    if (cb && typeof cb === 'function') {
      cb(toPackages(el))
    }
  })

  $(`#${el.id}_packages`).on('change', 'select', function() {
    if (cb && typeof cb === 'function') {
      cb(toPackages(el))
    }
  })
}

function parseWeight(value) {

  const floatValue = parseFloat((value || '0.0'))
  if (isNaN(floatValue)) {
    return 0
  }

  return parseInt((floatValue * 1000), 10)
}

const loadTags = _.once(() => {

  return axios({
    method: 'get',
    url: window.Routing.generate('admin_tags', { format: 'json' }),
  })
  .then(response => response.data)
})

// https://stackoverflow.com/questions/494143/creating-a-new-dom-element-from-an-html-string-using-built-in-dom-methods-or-pro
function createElementFromHTML(htmlString) {
  var div = document.createElement('div');
  div.innerHTML = htmlString.trim();

  // Change this to div.childNodes to support multiple top-level nodes
  return div.firstChild;
}

function initSubForm(name, taskEl, preloadedState, userAdmin) {

  const task = {
    type: getTaskType(taskEl),
    address: null,
    ...getTimeWindowProps(taskEl),
  }

  if (preloadedState) {
    preloadedState.tasks.push(task)
  } else {
    reduxStore.dispatch({
      type: 'ADD_DROPOFF',
      taskIndex: domIndex(taskEl),
      value: task
    })
  }

  createAddressWidget(taskEl, address => {
    if (preloadedState) {
      const index = preloadedState.tasks.indexOf(task)
      if (-1 !== index) {
        preloadedState.tasks[index].address = address
      }
    }
  })

  createDatePickerWidget(taskEl, userAdmin)

  const tagsEl = document.querySelector(`#${taskEl.id}_tagsAsString`)
  if (tagsEl) {
    loadTags().then(tags => {
      createTagsWidget(taskEl, tags)
    })
  }

  createSwitchTimeSlotWidget(taskEl)

  const deleteBtn = taskEl.querySelector('[data-delete="task"]')

  if (deleteBtn) {

    // We want at least one dropoff
    if (collectionHolder.children.length === 2) {
      document.querySelectorAll('[data-delete="task"]').forEach(el => el.classList.add('d-none'))
    }

    deleteBtn.addEventListener('click', (e) => {
      e.preventDefault()
      reduxStore.dispatch({
        type: 'REMOVE_DROPOFF',
        taskIndex: domIndex(taskEl),
      })
      taskEl.remove()
      // We want at least one dropoff
      if (collectionHolder.children.length === 2) {
        document.querySelectorAll('[data-delete="task"]').forEach(el => el.classList.add('d-none'))
      }
      const indexes = Array
        .from(collectionHolder.children)
        .map(el => parseInt(el.id.replace(/^(.*_tasks_)([0-9]+)$/, '$2'), 10))
      collectionHolder.dataset.index = Math.max(...indexes) + 1
    })
  }

  const packages = document.querySelector(`#${taskEl.id}_packages`)
  if (packages) {
    const packagesRequired = JSON.parse(packages.dataset.packagesRequired)
    createPackagesWidget(taskEl, packagesRequired, packages => reduxStore.dispatch({ type: 'SET_TASK_PACKAGES', taskIndex: domIndex(taskEl), packages }))
  }

  const weightEl = document.querySelector(`#${taskEl.id}_weight`)

  if (preloadedState) {
    const index = preloadedState.tasks.indexOf(task)
    if (-1 !== index) {
      preloadedState.tasks[index].weight = weightEl ? parseWeight(weightEl.value) : 0
    }
  }

  if (weightEl) {
    weightEl.addEventListener('input', _.debounce(e => {
      reduxStore.dispatch({
        type: 'SET_WEIGHT',
        value: parseWeight(e.target.value),
        taskIndex: domIndex(taskEl),
      })
    }, 350))
  }
}

function createOnTasksChanged(onChange) {

  return ({ getState }) => (next) => (action) => {

    const prevState = getState()
    const result = next(action)
    const state = getState()

    if (prevState.tasks !== state.tasks) {
      onChange(state)
    }

    return result
  }
}

export default function(name, options) {

  const el = document.querySelector(`form[name="${name}"]`)

  const form = new DeliveryForm()

  const onChange = options.onChange.bind(form)
  const onReady = options.onReady.bind(form)
  const onSubmit = options.onSubmit.bind(form)

  const handleSuggestionsAfterSubmit = () => (next) => (action) => {

    const result = next(action)

    if (acceptSuggestions.match(action) && action.payload.length > 0) {
        // Reorder tasks in the DOM when suggestion is accepted
      reorder(action.payload[0].order)
    }

    if (acceptSuggestions.match(action) || rejectSuggestions.match(action)) {
      el.submit()
    }

    return result
  }

  if (el) {

    // Intialize Redux store
    let preloadedState = {
      tasks: [],
    }

    if (el.dataset.store) {
      preloadedState = {
        ...preloadedState,
        [storeSlice.name]: el.dataset.store
      }
    }

    if (el.dataset.subscription) {
      const subscription = JSON.parse(el.dataset.subscription)

      preloadedState = {
        ...preloadedState,
        [recurrenceSlice.name]: {
          ...recurrenceSlice.getInitialState(),
          rule: subscription.rule,
          isCancelled: subscription.isCancelled,
        }
      }

      if (subscription.isCancelled) {
        $('button[type="submit"]').addClass('display-none');
      }
    }

    // tasks_0, tasks_1...
    const taskForms = Array.from(el.querySelectorAll('[data-form="task"]'))
    taskForms.forEach((taskEl) => initSubForm(name, taskEl, preloadedState, !!el.dataset.userAdmin))

    reduxStore = configureStore({
      reducer: {
        [storeSlice.name]: storeSlice.reducer,
        "tasks": tasksSlice.reducer,
        [recurrenceSlice.name]: recurrenceSlice.reducer,
        [suggestionsSlice.name]: suggestionsSlice.reducer,
      },
      preloadedState,
      middleware: getDefaultMiddleware =>
        getDefaultMiddleware().concat([createOnTasksChanged(onChange), handleSuggestionsAfterSubmit]),
    })

    onReady(preloadedState)

    new ClipboardJS('#copy', {
      text: function() {
        return document.getElementById('tracking_link').getAttribute('href')
      }
    })

    el.addEventListener('submit', (e) => {

      e.preventDefault()

      const hasInvalidInput = _.find(taskForms, taskEl => {

        const type = taskEl.getAttribute('id').replace(name + '_', '')

        const isNewAddrInput = document.querySelector(`#${name}_${type}_address_isNewAddress`)
        if (!isNewAddrInput) {
          return false
        }

        const searchInput = document.querySelector(`#${name}_${type}_address input[type="search"][data-is-address-picker="true"]`);
        const latInput = document.querySelector(`#${name}_${type}_address [data-address-prop="latitude"]`)
        const lngInput = document.querySelector(`#${name}_${type}_address [data-address-prop="longitude"]`)
        const streetAddrInput = document.querySelector(`#${name}_${type}_address_newAddress_streetAddress`)

        const isValid = validateForm(e, searchInput, latInput, lngInput, streetAddrInput)

        return !isValid
      })

      if (!hasInvalidInput) {
        form.disable()
      }

      const recurrenceField = document.querySelector('#delivery_recurrence')
      if (recurrenceField) {
        const recurrenceRule = selectRecurrenceRule(reduxStore.getState())
        recurrenceField.value = JSON.stringify({
          rule: recurrenceRule
        })
      }

      onSubmit(el, reduxStore.getState())
      return false

    }, false)

    // https://symfony.com/doc/current/form/form_collections.html#allowing-new-tags-with-the-prototype
    const addTaskButton = el.querySelector('[data-add="dropoff"]')
    if (addTaskButton) {
      addTaskButton.addEventListener('click', () => {

        const newHtml = collectionHolder
          .dataset
          .prototype
          .replace(
            /__name__/g,
            collectionHolder.dataset.index
          );

        const item = createElementFromHTML(newHtml)

        collectionHolder.appendChild(item)

        initSubForm(name, item, null, !!el.dataset.userAdmin)

        collectionHolder.dataset.index++

        if (collectionHolder.children.length > 2) {
          document.querySelectorAll('[data-delete="task"]').forEach(el => el.classList.remove('d-none'))
        }
      })
    }

    const reactRoot = createRoot(document.getElementById('delivery-form-modal'))
    reactRoot.render(
      <Provider store={ reduxStore }>
        <SuggestionModal />
      </Provider>
    )
  }

  const recurrenceRulesContainer = document.querySelector('#delivery_form__recurrence__container')
  if (recurrenceRulesContainer) {
    const root = createRoot(recurrenceRulesContainer);
    root.render(
      <Provider store={ reduxStore }>
        <I18nextProvider i18n={ i18n }>
          <RecurrenceRules />
        </I18nextProvider>
      </Provider>
    )
  }

  return form
}
