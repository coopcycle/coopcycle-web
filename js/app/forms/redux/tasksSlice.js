import _ from 'lodash'

function replaceTasks(state, index, key, value) {
  const newTasks = state.slice()
  newTasks[index] = {
    ...newTasks[index],
    [key]: value,
  }

  return newTasks
}

function removeTasks(state, index) {
  const newTasks = state.slice()
  newTasks.splice(index, 1)

  return newTasks
}

function reducer(state = {}, action) {
  switch (action.type) {
    case 'SET_ADDRESS':
      return replaceTasks(state, action.taskIndex, 'address', action.value)
    case 'SET_TIME_SLOT':
      return replaceTasks(state, action.taskIndex, 'timeSlot', action.value)
    case 'SET_BEFORE':
      return replaceTasks(state, action.taskIndex, 'before', action.value)
    case 'SET_AFTER':
      return replaceTasks(state, action.taskIndex, 'after', action.value)
    case 'SET_WEIGHT':
      return replaceTasks(state, action.taskIndex, 'weight', action.value)
    case 'SET_TASK_PACKAGES':
      return replaceTasks(state, action.taskIndex, 'packages', action.packages)
    case 'CLEAR_ADDRESS':
      return state.map((task, index) => {
        if (index === action.taskIndex) {
          return _.omit({ ...task }, ['address'])
        }

        return task
      })
    case 'ADD_DROPOFF':
      return state.concat([action.value])
    case 'REMOVE_DROPOFF':
      return removeTasks(state, action.taskIndex)
    case 'suggestions/acceptSuggestions':

      const newTasks = []
      action.payload[0].order.forEach((oldIndex, newIndex) => {
        newTasks.splice(newIndex, 0, state[oldIndex])
      })

      return newTasks
    default:
      return state
  }
}

export default {
  reducer,
}
