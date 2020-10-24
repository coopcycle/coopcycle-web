import {MODIFY_TASK_LIST_REQUEST, MODIFY_TASK_LIST_REQUEST_SUCCESS, TASK_LIST_UPDATED, UPDATE_TASK} from "./actions";
import _ from "lodash";
import { taskListUtils, objectUtils } from '../../coopcycle-frontend-js/logistics/redux'
import {createTaskList} from "./utils";

const initialState = {
  items: new Map()
}

const replaceOrAddTaskId = (taskIds, taskId) => {

  const taskIdIndex = _.findIndex(taskIds, t => t === taskId)

  if (-1 !== taskIdIndex) {

    const newTaskIds = taskIds.slice(0)
    newTaskIds.splice(taskIdIndex, 1, Object.assign({}, taskIds[taskIdIndex], taskId))

    return newTaskIds
  }

  return taskIds.concat([ taskId ])
}

const removeTaskId = (taskIds, taskId) => _.filter(taskIds, t => t !== taskId)

export default (state = initialState, action) => {
  switch (action.type) {
    case MODIFY_TASK_LIST_REQUEST: {
      let taskList = _.find(state.items.values(), taskList => taskList.username === action.username)

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
      let newItems = objectUtils.copyMap(state.items)

      let taskList = _.find(state.items.values(), taskList => {
        return _.includes(taskList.itemIds, action.task['@id'])
      })

      if (action.task.isAssigned) {
        let targetTaskList = _.find(state.items.values(), taskList => taskList.username === action.task.assignedTo)

        if (taskList != null) {
          if (targetTaskList['@id'] !== taskList['@id']) {
            //unassign
            let newTaskList = {
              ...taskList,
              itemIds: removeTaskId(taskList.itemIds, action.task['@id'])
            }

            newItems.set(taskList['@id'], newTaskList)
          }
        }

        //assign
        if (targetTaskList != null) {
          let newTaskList = {
            ...taskList,
            itemIds: replaceOrAddTaskId(targetTaskList.itemIds, action.task['@id'])
          }

          newItems.set(targetTaskList['@id'], newTaskList)

        } else {
          let newTaskList = createTaskList(action.task.assignedTo, [action.task])
          newTaskList = taskListUtils.replaceTasksWithIds(newTaskList)

          newItems.set(newTaskList['@id'], newTaskList)
        }

      } else {
        if (taskList != null) {
          //unassign
          let newTaskList = {
            ...taskList,
            itemIds: removeTaskId(taskList.itemIds, action.task['@id'])
          }

          newItems.set(taskList['@id'], newTaskList)
        }
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
