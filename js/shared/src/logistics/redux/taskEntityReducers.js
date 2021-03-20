import {
  CREATE_TASK_LIST_SUCCESS
} from './actions';
import { addOrReplaceTasks } from './taskUtils'

const initialState = {
  byId: {}
}

export default (state = initialState, action) => {
  switch (action.type) {
    case CREATE_TASK_LIST_SUCCESS: {
      let taskList = action.payload
      let newItems = addOrReplaceTasks(state.byId, taskList.items)

      return {
        ...state,
        byId: newItems,
      }
    }
    default:
      return state
  }
}
