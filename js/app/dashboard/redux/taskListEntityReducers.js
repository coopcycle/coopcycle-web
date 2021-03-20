import _ from 'lodash'
import {
  MODIFY_TASK_LIST_REQUEST,
  MODIFY_TASK_LIST_REQUEST_SUCCESS,
  TASK_LIST_UPDATED,
  TASK_LISTS_UPDATED,
  UPDATE_TASK,
  REMOVE_TASK
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

      if (!entity) {

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

      if (!Object.prototype.hasOwnProperty.call(state.byId, action.taskList['@id'])) {
        return state
      }

      return {
        ...state,
        byId: {
          ...state.byId,
          [ action.taskList['@id'] ]: {
            ...state.byId[ action.taskList['@id'] ],
            distance: action.taskList.distance,
            duration: action.taskList.duration,
            polyline: action.taskList.polyline,
          }
        },
      }
    }
    case TASK_LISTS_UPDATED: {
      const matchingLists = _.filter(
        action.taskLists,
        updated => Object.prototype.hasOwnProperty.call(state.byId, updated['@id'])
      )

      if (matchingLists.length === 0) {

        return state
      }

      return {
        ...state,
        byId: _.mapValues(state.byId, current => {
          const matchingList = _.find(matchingLists, o => o['@id'] === current['@id'])

          if (!matchingList) {

            return current
          }

          return {
            ...current,
            distance: matchingList.distance,
            duration: matchingList.duration,
            polyline: matchingList.polyline,
          }
        }),
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
    case REMOVE_TASK:

      return {
        ...state,
        byId: taskListEntityUtils.removeUnassignedTask(state.byId, action.task)
      }
    default:
      return state
  }
}
