import {
  MODIFY_TASK_LIST_REQUEST,
  MODIFY_TASK_LIST_REQUEST_SUCCESS,
  TASK_LIST_UPDATED,
  UPDATE_TASK
} from "./actions";
import _ from "lodash";
import { taskListUtils, objectUtils } from '../../coopcycle-frontend-js/lastmile/redux'

const initialState = {
  items: new Map()
}

export default (state = initialState, action) => {
  switch (action.type) {
    case MODIFY_TASK_LIST_REQUEST: {
      let taskLists = Array.from(state.items.values())
      let taskList = _.find(taskLists, taskList => taskList.username === action.username)

      let newTaskList = {
        ...taskList,
        itemIds: taskListUtils.tasksToIds(action.tasks),
      }

      let newItems = objectUtils.copyMap(state.items)
      newItems.set(newTaskList['@id'], newTaskList)

      return {
        ...state,
        items: newItems,
      }
    }
    case MODIFY_TASK_LIST_REQUEST_SUCCESS: {
      let newTaskList = taskListUtils.replaceTasksWithIds(action.taskList)

      let newItems = objectUtils.copyMap(state.items)
      newItems.set(newTaskList['@id'], newTaskList)

      return {
        ...state,
        items: newItems,
      }
    }
    case TASK_LIST_UPDATED: {
      let taskList = state.items.get(action.taskList['@id'])

      if (taskList == null) {
        return state
      }

      let newTaskList = {
        ...taskList,
        distance: action.taskList.distance,
        duration: action.taskList.duration,
        polyline: action.taskList.polyline,
      }

      let newItems = objectUtils.copyMap(state.items)
      newItems.set(newTaskList['@id'], newTaskList)

      return {
        ...state,
        items: newItems,
      }
    }
    case UPDATE_TASK: {
      let newItems

      if (action.task.isAssigned) {
        newItems = taskListUtils.addAssignedTask(state, action.task)
      } else {
        newItems = taskListUtils.removeUnassignedTask(state, action.task)
      }

      return {
        ...state,
        items: newItems,
      }
    }
    default:
      return state
  }
}
