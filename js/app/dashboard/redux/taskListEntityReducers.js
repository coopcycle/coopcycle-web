import {
  MODIFY_TASK_LIST_REQUEST,
  MODIFY_TASK_LIST_REQUEST_SUCCESS,
  TASK_LIST_UPDATED,
  UPDATE_TASK
} from "./actions";
import {taskListUtils as utils} from '../../coopcycle-frontend-js/lastmile/redux'

const initialState = {
  byUsername: {}
}

export default (state = initialState, action) => {
  switch (action.type) {
    case MODIFY_TASK_LIST_REQUEST: {
      let entity = state.byUsername[action.username]

      console.assert(entity != null, `entity is null: username: ${action.username}`)

      let newEntity = {
        ...entity,
        itemIds: utils.tasksToIds(action.tasks),
      }

      let newItems = utils.upsertTaskList(state.byUsername, newEntity)

      return {
        ...state,
        byUsername: newItems,
      }
    }
    case MODIFY_TASK_LIST_REQUEST_SUCCESS: {
      let newEntity = utils.replaceTasksWithIds(action.taskList)

      let newItems = utils.upsertTaskList(state.byUsername, newEntity)

      return {
        ...state,
        byUsername: newItems,
      }
    }
    case TASK_LIST_UPDATED: {
      let entity = state.byUsername[action.taskList[utils.taskListKey]]

      if (entity == null) {
        return state
      }

      // items: [
      //   {
      //     task: '/api/tasks/21',
      //     position: 0
      //   },
      //   {
      //     task: '/api/tasks/18',
      //     position: 1
      //   }
      // ],
      let taskCollectionItems = action.taskList.items
      let itemIds = taskCollectionItems.map(item => item.task)

      let newEntity = {
        ...entity,
        itemIds,
        distance: action.taskList.distance,
        duration: action.taskList.duration,
        polyline: action.taskList.polyline,
      }

      let newItems = utils.upsertTaskList(state.byUsername, newEntity)

      return {
        ...state,
        byUsername: newItems,
      }
    }
    case UPDATE_TASK: {
      let newItems

      if (action.task.isAssigned) {
        newItems = utils.addAssignedTask(state, action.task)
      } else {
        newItems = utils.removeUnassignedTask(state, action.task)
      }

      return {
        ...state,
        byUsername: newItems,
      }
    }
    default:
      return state
  }
}
