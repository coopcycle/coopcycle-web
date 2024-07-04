import {
  MODIFY_TASK_LIST_REQUEST,
  MODIFY_TASK_LIST_REQUEST_SUCCESS,
  TASK_LISTS_UPDATED,
  // UPDATE_TASK,
  REMOVE_TASK
} from './actions'
import {
  taskListEntityUtils,
  taskListAdapter,
} from '../../coopcycle-frontend-js/logistics/redux'

const initialState = taskListAdapter.getInitialState()
const selectors = taskListAdapter.getSelectors((state) => state)

/**
 * @param {Object} state - Initial state
 * @param {Array.string} items - Items to be assigned, list of tasks and tours to be assigned
 */
export default (state = initialState, action) => {
  switch (action.type) {
    case MODIFY_TASK_LIST_REQUEST:

      let entity = selectors.selectById(state, action.username)

      if (!entity) {

        return state
      }

      let newEntity = {
        ...entity,
        items: action.items,
      }

      return taskListAdapter.upsertOne(state, newEntity)

    case MODIFY_TASK_LIST_REQUEST_SUCCESS:
      return taskListAdapter.upsertOne(state, action.taskList)

    case TASK_LISTS_UPDATED: {
      return taskListAdapter.upsertOne(state, action.taskList)
    }

    case REMOVE_TASK:
      return taskListAdapter.upsertMany(state,
        taskListEntityUtils.removeUnassignedTask(selectors.selectEntities(state), action.task))
  }

  return state
}
