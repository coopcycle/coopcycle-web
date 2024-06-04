import _ from 'lodash'
import {
  UPDATE_TASK,
  DELETE_GROUP_SUCCESS,
  REMOVE_TASK,
  CREATE_GROUP_SUCCESS,
  REMOVE_TASKS_FROM_GROUP_SUCCESS,
  ADD_TASKS_TO_GROUP_SUCCESS,
} from './actions'
import { taskAdapter } from '../../coopcycle-frontend-js/logistics/redux'

const initialState = taskAdapter.getInitialState()
const selectors = taskAdapter.getSelectors((state) => state)

export default (state = initialState, action) => {
  switch (action.type) {
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
  }

  return state
}
