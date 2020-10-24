import {MODIFY_TASK_LIST_REQUEST, MODIFY_TASK_LIST_REQUEST_SUCCESS, UPDATE_TASK} from "./actions";
import { taskUtils } from '../../coopcycle-frontend-js/logistics/redux'

const initialState = {
  items: new Map()
}

export default (state = initialState, action) => {
  switch (action.type) {
    case MODIFY_TASK_LIST_REQUEST: {
      let newItems = taskUtils.upsertTasks(state.items, action.tasks)

      return {
        ...state,
        items: newItems,
      }
    }
    case MODIFY_TASK_LIST_REQUEST_SUCCESS: {
      let newItems = taskUtils.upsertTasks(state.items, action.taskList.items)

      return {
        ...state,
        items: newItems,
      }
    }
    case UPDATE_TASK: {
      let newItems = taskUtils.upsertTasks(state.items, [action.task])

      return {
        ...state,
        items: newItems,
      }
    }
    default:
      return state
  }
}
