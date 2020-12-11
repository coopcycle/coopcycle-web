import {
  MODIFY_TASK_LIST_REQUEST,
  MODIFY_TASK_LIST_REQUEST_SUCCESS,
  TASK_LIST_UPDATED,
  UPDATE_TASK
} from "./actions";
import {
  taskUtils,
  taskListUtils,
  taskListEntityUtils,
} from '../../coopcycle-frontend-js/logistics/redux'

const initialState = {
  byId: {}
}

export default (state = initialState, action) => {
  switch (action.type) {
    case MODIFY_TASK_LIST_REQUEST: {
      let entity = taskListEntityUtils.findTaskListByUsername(state.byId, action.username)

      if (entity === undefined) {
        // eslint-disable-next-line no-console
        console.assert(false, `entity is undefined: username: ${action.username}`)

        return state
      }

      let newEntity = {
        ...entity,
        itemIds: taskUtils.tasksToIds(action.tasks),
      }

      let newItems = taskListEntityUtils.addOrReplaceTaskList(state.byId, newEntity)

      return {
        ...state,
        byId: newItems,
      }
    }
    case MODIFY_TASK_LIST_REQUEST_SUCCESS: {
      let newEntity = taskListUtils.replaceTasksWithIds(action.taskList)
      let newItems = taskListEntityUtils.addOrReplaceTaskList(state.byId, newEntity)

      return {
        ...state,
        byId: newItems,
      }
    }
    case TASK_LIST_UPDATED: {
      let entityByUsername = taskListEntityUtils.findTaskListByUsername(state.byId, action.taskList['username'])

      if (entityByUsername === undefined) {
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
        ...entityByUsername,
        itemIds,
        distance: action.taskList.distance,
        duration: action.taskList.duration,
        polyline: action.taskList.polyline,
      }

      if (entityByUsername['@id'] != action.taskList['@id']) {
        newEntity['@id'] = action.taskList['@id']
      }

      let newItems = taskListEntityUtils.addOrReplaceTaskList(state.byId, newEntity)

      return {
        ...state,
        byId: newItems,
      }
    }
    case UPDATE_TASK: {
      let newItems

      if (action.task.isAssigned) {
        newItems = taskListEntityUtils.addAssignedTask(state.byId, action.task)
      } else {
        newItems = taskListEntityUtils.removeUnassignedTask(state.byId, action.task)
      }

      return {
        ...state,
        byId: newItems,
      }
    }
    default:
      return state
  }
}
