import _ from 'lodash'
import {
  MODIFY_TASK_LIST_REQUEST,
  MODIFY_TASK_LIST_REQUEST_SUCCESS,
  UPDATE_TASK,
  DELETE_GROUP_SUCCESS,
  REMOVE_TASK,
  CREATE_GROUP_SUCCESS,
  REMOVE_TASKS_FROM_GROUP_SUCCESS,
  ADD_TASKS_TO_GROUP_SUCCESS,
  MODIFY_TOUR_REQUEST,
} from './actions'
import { taskAdapter } from '../../coopcycle-frontend-js/logistics/redux'

const initialState = taskAdapter.getInitialState()
const selectors = taskAdapter.getSelectors((state) => state)

export default (state = initialState, action) => {
  switch (action.type) {
    case MODIFY_TASK_LIST_REQUEST:
      return taskAdapter.upsertMany(state, action.tasks)

    case MODIFY_TASK_LIST_REQUEST_SUCCESS:
      const entities = action.taskList.items.map(item => ({
        '@id': item.task,
        position: item.position
      }))
      return taskAdapter.upsertMany(state, entities)

    case UPDATE_TASK:
      return taskAdapter.upsertOne(state, action.task)

    case DELETE_GROUP_SUCCESS:
      const tasksMatchingGroup = _.filter(
        selectors.selectAll(state),
        t => t.group && t.group['@id'] === action.group
      )

      if (tasksMatchingGroup.length === 0) {
        return state
      }

      return taskAdapter.removeMany(state, tasksMatchingGroup.map(t => t['@id']))

    case REMOVE_TASK:
      return taskAdapter.removeOne(state, action.task['@id'])

    case CREATE_GROUP_SUCCESS:

      const tasksMatchingCreatedGroup = _.filter(
        selectors.selectAll(state),
        t => _.includes(action.taskGroup.tasks, t['@id'])
      )

      if (tasksMatchingCreatedGroup.length === 0) {
        return state
      }

      return taskAdapter.upsertMany(state, tasksMatchingCreatedGroup.map(t => ({
        ...t,
        group: _.pickBy({
          ...action.taskGroup,
          tags: [],
        }, (value, key) => key !== 'tasks')
      })))

    case REMOVE_TASKS_FROM_GROUP_SUCCESS:

      return taskAdapter.upsertMany(state, action.tasks.map(t => ({
        ...t,
        group: null
      })))

    case ADD_TASKS_TO_GROUP_SUCCESS:

      return taskAdapter.upsertMany(state, action.tasks.map(t => ({
        ...t,
        group: _.pickBy({
          ...action.taskGroup,
          tags: [],
        }, (value, key) => key !== 'tasks')
      })))

    case MODIFY_TOUR_REQUEST:

      const newT = action.tasks.map((t, index) => ({
        '@id': t['@id'],
        tour: {
          '@id': '/api/tours/' + action.tourId,
          name: action.tourName,
          position: index
        }
      }))

      return taskAdapter.upsertMany(state, newT)
  }

  return state
}
