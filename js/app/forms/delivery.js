import moment from 'moment'
import ClipboardJS from 'clipboard'
import { createStore } from 'redux'
import _ from 'lodash'
import axios from 'axios'
import { createSelector } from 'reselect'

import AddressBook from '../delivery/AddressBook'
import DateTimePicker from '../widgets/DateTimePicker'
import DateRangePicker from '../widgets/DateRangePicker'
import TagsInput from '../widgets/TagsInput'
import { validateForm } from '../utils/address'

const selectTasks = state => state.tasks

const selectLastDropoff = createSelector(
  selectTasks,
  (tasks) => {
    return _.findLast(tasks, t => t.type === 'DROPOFF')
  }
)

const collectionHolder = document.querySelector('#delivery_tasks')

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

function toPackages(name) {
  const packages = []
  $(`#${name}_packages_list`).children().each(function() {
    packages.push({
      type: $(this).find('select').val(),
      quantity: $(this).find('input[type="number"]').val()
    })
  })

  return packages
}

function hideRememberAddress(name, type) {
  const rememberAddr = document.querySelector(`#${name}_${type}_address_rememberAddress`)
  if (rememberAddr) {
    rememberAddr.closest('.checkbox').classList.add('invisible')
  }
}

function showRememberAddress(name, type) {
  const rememberAddr = document.querySelector(`#${name}_${type}_address_rememberAddress`)
  if (rememberAddr) {
    rememberAddr.closest('.checkbox').classList.remove('invisible')
  }
}

function createAddressWidget(name, type, cb) {

  new AddressBook(document.querySelector(`#${name}_${type}_address`), {
    existingAddressControl: document.querySelector(`#${name}_${type}_address_existingAddress`),
    newAddressControl: document.querySelector(`#${name}_${type}_address_newAddress_streetAddress`),
    isNewAddressControl: document.querySelector(`#${name}_${type}_address_isNewAddress`),
    duplicateAddressControl: document.querySelector(`#${name}_${type}_address_duplicateAddress`),
    // Fields containing address details
    nameControl: document.querySelector(`#${name}_${type}_address_name`),
    telephoneControl: document.querySelector(`#${name}_${type}_address_telephone`),
    contactNameControl: document.querySelector(`#${name}_${type}_address_contactName`),
    onReady: address => {
      cb(address)
      if (Object.prototype.hasOwnProperty.call(address, '@id')) {
        hideRememberAddress(name, type)
      }
    },
    onChange: address => {

      if (Object.prototype.hasOwnProperty.call(address, '@id')) {
        hideRememberAddress(name, type)
      } else {
        showRememberAddress(name, type)
      }

      store.dispatch({
        type: 'SET_ADDRESS',
        taskIndex: getTaskIndex(type),
        value: address
      })
    },
    onClear: () => {
      showRememberAddress(name, type)
      store.dispatch({
        type: 'CLEAR_ADDRESS',
        taskIndex: getTaskIndex(type),
      })
    }
  })
}

function getDatePickerValue(name, type) {
  const timeSlotEl = document.querySelector(`#${name}_${type}_timeSlot`)

  if (timeSlotEl) {
    return $(`#${name}_${type}_timeSlot`).val()
  }

  const defaultValue = $(`#${name}_${type}_doneBefore`).val() || selectLastDropoff(store.getState()).before

  return moment(defaultValue, 'YYYY-MM-DD HH:mm:ss').format()
}

function getDatePickerKey(name, type) {
  const timeSlotEl = document.querySelector(`#${name}_${type}_timeSlot`)
  if (timeSlotEl) {
    return 'timeSlot'
  }

  return 'before'
}

function getTaskType(name, type) {
  return document.querySelector(`#${name}_${type}_type`).value.toUpperCase()
}

function createDateRangePickerWidget(name, type) {
  const doneBeforePickerEl = document.querySelector(`#${name}_${type}_doneBefore`)
  const doneAfterPickerEl = document.querySelector(`#${name}_${type}_doneAfter`)

  const beforeDefaultValue = doneBeforePickerEl.value || selectLastDropoff(store.getState()).before
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

  new DateRangePicker(document.querySelector(`#${name}_${type}_doneBefore_widget`), {
    defaultValue,
    showTime: true,
    onChange: function({after, before}) {
      doneAfterPickerEl.value = after.format('YYYY-MM-DD HH:mm:ss')
      doneBeforePickerEl.value = before.format('YYYY-MM-DD HH:mm:ss')

      store.dispatch({
        type: 'SET_BEFORE',
        taskIndex: getTaskIndex(type),
        value: before.format()
      })

      store.dispatch({
        type: 'SET_AFTER',
        taskIndex: getTaskIndex(type),
        value: after.format()
      })
    }
  })
}

function createDatePickerWidget(name, type, isAdmin = false) {

  const datePickerEl = document.querySelector(`#${name}_${type}_doneBefore`)
  const timeSlotEl = document.querySelector(`#${name}_${type}_timeSlot`)

  if (timeSlotEl) {
    timeSlotEl.addEventListener('change', e => {
      store.dispatch({
        type: 'SET_TIME_SLOT',
        taskIndex: getTaskIndex(type),
        value: e.target.value
      })
    })
    return
  }

  if (isAdmin) {
    createDateRangePickerWidget(name, type)
    return
  }

  const defaultValue = datePickerEl.value || selectLastDropoff(store.getState()).before

  // When adding a new task, initialize hidden input value
  if (!datePickerEl.value) {
    datePickerEl.value = moment(defaultValue).format('YYYY-MM-DD HH:mm:ss')
  }

  new DateTimePicker(document.querySelector(`#${name}_${type}_doneBefore_widget`), {
    defaultValue,
    onChange: function(date) {
      datePickerEl.value = date.format('YYYY-MM-DD HH:mm:ss')
      store.dispatch({
        type: 'SET_BEFORE',
        taskIndex: getTaskIndex(type),
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

function createSwitchTimeSlotWidget(name, taskForm) {
  const switchTimeSlotEl = document.querySelector(`#${name}_${taskForm}_switchTimeSlot`)
  const timeSlotEl = document.querySelector(`#${name}_${taskForm}_timeSlot`)

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

        store.dispatch({
          type: 'SET_TIME_SLOT',
          taskIndex: getTaskIndex(taskForm),
          value: timeSlotEl.value
        })
      })
    })
  }
}

function createPackageForm(name, $list, cb) {

  var counter = $list.data('widget-counter') || $list.children().length
  var newWidget = $list.attr('data-prototype')

  newWidget = newWidget.replace(/__package__/g, counter)

  counter++
  $list.data('widget-counter', counter)

  var newElem = $(newWidget)
  newElem.find('input[type="number"]').val(1)
  newElem.find('input[type="number"]').on('change', () => {
    if (cb && typeof cb === 'function') {
      cb(toPackages(name))
    }
  })
  newElem.appendTo($list)
}

export function createPackagesWidget(name, packagesRequired, cb) {

  const isNew = document.querySelectorAll(`#${name}_packages .delivery__form__packages__list-item`).length === 0

  if (isNew && packagesRequired) {
    createPackageForm(
      name,
      $(`#${name}_packages_list`),
      cb
    )
  }

  $(`#${name}_packages_add`).click(function() {
    const selector = $(this).attr('data-target')
    createPackageForm(
      name,
      $(selector),
      cb
    )
    if (cb && typeof cb === 'function') {
      cb(toPackages(name))
    }
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
    if (cb && typeof cb === 'function') {
      cb(toPackages(name))
    }
  })

  $(`#${name}_packages`).on('change', 'select', function() {
    if (cb && typeof cb === 'function') {
      cb(toPackages(name))
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

function replaceTasks(state, index, key, value) {
  const newTasks = state.tasks.slice()
  newTasks[index] = {
    ...newTasks[index],
    [key]: value
  }

  return newTasks
}

function removeTasks(state, index) {
  const newTasks = state.tasks.slice()
  newTasks.splice(index, 1)

  return newTasks
}

const getTaskIndex = (key) => parseInt(key.replace('tasks_', ''), 10)

function reducer(state = {}, action) {
  switch (action.type) {
  case 'SET_ADDRESS':
    return {
      ...state,
      tasks: replaceTasks(state, action.taskIndex, 'address', action.value),
    }
  case 'SET_TIME_SLOT':
    return {
      ...state,
      tasks: replaceTasks(state, action.taskIndex, 'timeSlot', action.value),
    }
  case 'SET_BEFORE':
    return {
      ...state,
      tasks: replaceTasks(state, action.taskIndex, 'before', action.value),
    }
  case 'SET_AFTER':
      return {
        ...state,
        tasks: replaceTasks(state, action.taskIndex, 'after', action.value),
      }
  case 'SET_WEIGHT':
    return {
      ...state,
      tasks: replaceTasks(state, action.taskIndex, 'weight', action.value)
    }
  case 'SET_TASK_PACKAGES':
    return {
      ...state,
      tasks: replaceTasks(state, action.taskIndex, 'packages', action.packages)
    }
  case 'CLEAR_ADDRESS':
    return {
      ...state,
      tasks: state.tasks.map((task, index) => {
        if (index === action.taskIndex) {
          return _.omit({ ...task }, ['address'])
        }

        return task
      }),
    }
  case 'ADD_DROPOFF':
    return {
      ...state,
      tasks: state.tasks.concat([ action.value ]),
    }
  case 'REMOVE_DROPOFF':
    return {
      ...state,
      tasks: removeTasks(state, action.taskIndex)
    }
  default:
    return state
  }
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
  const taskForm = taskEl.getAttribute('id').replace(name + '_', '')
  const taskIndex = getTaskIndex(taskForm)

  const task = {
    type: getTaskType(name, taskForm),
    address: null,
    [ getDatePickerKey(name, taskForm) ]: getDatePickerValue(name, taskForm)
  }

  if (preloadedState) {
    preloadedState.tasks.push(task)
  } else {
    store.dispatch({
      type: 'ADD_DROPOFF',
      taskIndex,
      value: task
    })
  }

  createAddressWidget(name, taskForm, address => {
    if (preloadedState) {
      const index = preloadedState.tasks.indexOf(task)
      if (-1 !== index) {
        preloadedState.tasks[index].address = address
      }
    }
  })

  createDatePickerWidget(name, taskForm, userAdmin)

  const tagsEl = document.querySelector(`#${name}_${taskForm}_tagsAsString`)
  if (tagsEl) {
    loadTags().then(tags => {
      createTagsWidget(name, taskForm, tags)
    })
  }

  createSwitchTimeSlotWidget(name, taskForm)

  const deleteBtn = taskEl.querySelector('[data-delete="task"]')

  if (deleteBtn) {
    if (taskIndex === 1) {
      // No delete button for the 1rst dropoff,
      // we want at least one dropoff
      deleteBtn.remove()
    } else {
      deleteBtn.addEventListener('click', (e) => {
        e.preventDefault()
        taskEl.remove()
        store.dispatch({
          type: 'REMOVE_DROPOFF',
          taskIndex,
        })
        collectionHolder.dataset.index--
      })
    }
  }

  const packages = document.querySelector(`#${name}_${taskForm}_packages`)
  if (packages) {
    const packagesRequired = JSON.parse(packages.dataset.packagesRequired)
    createPackagesWidget(`${name}_${taskForm}`, packagesRequired, packages => store.dispatch({ type: 'SET_TASK_PACKAGES', taskIndex, packages }))
  }

  const weightEl = document.querySelector(`#${name}_${taskForm}_weight`)

  if (preloadedState) {
    const index = preloadedState.tasks.indexOf(task)
    if (-1 !== index) {
      preloadedState.tasks[index].weight = weightEl ? parseWeight(weightEl.value) : 0
    }
  }

  if (weightEl) {
    weightEl.addEventListener('input', _.debounce(e => {
      store.dispatch({
        type: 'SET_WEIGHT',
        value: parseWeight(e.target.value),
        taskIndex,
      })
    }, 350))
  }
}

export default function(name, options) {

  const el = document.querySelector(`form[name="${name}"]`)

  const form = new DeliveryForm()

  const onChange = options.onChange.bind(form)
  const onReady = options.onReady.bind(form)

  if (el) {


    // Intialize Redux store
    let preloadedState = {
      tasks: [],
      packages: []
    }

    if (el.dataset.store) {
      preloadedState = {
        ...preloadedState,
        store: el.dataset.store
      }
    }

    // tasks_0, tasks_1...
    const taskForms = Array.from(el.querySelectorAll('[data-form="task"]'))
    taskForms.forEach((taskEl) => initSubForm(name, taskEl, preloadedState, !!el.dataset.userAdmin))

    store = createStore(
      reducer, preloadedState,
      window.__REDUX_DEVTOOLS_EXTENSION__ && window.__REDUX_DEVTOOLS_EXTENSION__()
    )

    onReady(preloadedState)
    store.subscribe(() => onChange(store.getState()))

    new ClipboardJS('#copy', {
      text: function() {
        return document.getElementById('tracking_link').getAttribute('href')
      }
    })

    el.addEventListener('submit', (e) => {

      const hasInvalidInput = _.find(taskForms, taskEl => {

        const type = taskEl.getAttribute('id').replace(name + '_', '')

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

      if (!hasInvalidInput) {
        form.disable()
      }

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
      })
    }
  }

  return form
}
