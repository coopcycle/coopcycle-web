import _ from 'lodash'
import {
  MODIFY_TASK_LIST_REQUEST,
  MODIFY_TASK_LIST_REQUEST_SUCCESS,
  UPDATE_TASK,
  DELETE_GROUP_SUCCESS,
  REMOVE_TASK,
} from './actions'
import { taskAdapter } from '../../coopcycle-frontend-js/logistics/redux'

const initialState = taskAdapter.getInitialState()
const selectors = taskAdapter.getSelectors((state) => state)

export default (state = initialState, action) => {
  switch (action.type) {
    case MODIFY_TASK_LIST_REQUEST:
      return taskAdapter.upsertMany(state, action.tasks)

    case MODIFY_TASK_LIST_REQUEST_SUCCESS:
      return taskAdapter.upsertMany(state, action.taskList.items)

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
  }

  return state
}
