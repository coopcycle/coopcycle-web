import {
  CREATE_TASK_LIST_SUCCESS
} from './actions';
import taskAdapter from './taskAdapter'

const initialState = taskAdapter.getInitialState()

export default (state = initialState, action) => {
  switch (action.type) {
    case CREATE_TASK_LIST_SUCCESS:
      return taskAdapter.upsertMany(state, action.payload.taskList.items)
  }

  return state
}
