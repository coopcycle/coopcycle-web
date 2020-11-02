import {
  MODIFY_TASK_LIST_REQUEST,
  MODIFY_TASK_LIST_REQUEST_SUCCESS,
  TASK_LIST_UPDATED,
  UPDATE_TASK
} from "./actions";
import _ from "lodash";
import {taskListUtils as utils} from '../../coopcycle-frontend-js/lastmile/redux'

const initialState = {
  items: new Map()
}

export default (state = initialState, action) => {
  switch (action.type) {
    case MODIFY_TASK_LIST_REQUEST: {
      let entities = Array.from(state.items.values())
      let entity = _.find(entities, taskList => taskList.username === action.username)

      let newEntity = {
        ...entity,
        itemIds: utils.tasksToIds(action.tasks),
      }

      let newItems = new Map(state.items)
      newItems.set(newEntity[utils.taskListKey], newEntity)

      return {
        ...state,
        items: newItems,
      }
    }
    case MODIFY_TASK_LIST_REQUEST_SUCCESS: {
      let newEntity = utils.replaceTasksWithIds(action.taskList)

      let newItems = new Map(state.items)
      newItems.set(newEntity[utils.taskListKey], newEntity)

      return {
        ...state,
        items: newItems,
      }
    }
    case TASK_LIST_UPDATED: {
      let entity = state.items.get(action.taskList[utils.taskListKey])

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

      let newItems = new Map(state.items)
      newItems.set(newEntity[utils.taskListKey], newEntity)

      return {
        ...state,
        items: newItems,
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
        items: newItems,
      }
    }
    default:
      return state
  }
}
