import _ from 'lodash'
import {
  MODIFY_TASK_LIST_REQUEST,
  MODIFY_TASK_LIST_REQUEST_SUCCESS,
  TASK_LISTS_UPDATED,
  UPDATE_TASK,
  REMOVE_TASK
} from './actions'
import {
  taskUtils,
  taskListUtils,
  taskListEntityUtils,
  taskListAdapter,
} from '../../coopcycle-frontend-js/logistics/redux'

const initialState = taskListAdapter.getInitialState()
const selectors = taskListAdapter.getSelectors((state) => state)

export default (state = initialState, action) => {
  switch (action.type) {
    case MODIFY_TASK_LIST_REQUEST:

      let entity = selectors.selectById(state, action.username)

      if (!entity) {

        return state
      }

      let newEntity = {
        ...entity,
        itemIds: taskUtils.tasksToIds(action.tasks),
      }

      return taskListAdapter.upsertOne(state, newEntity)

    case MODIFY_TASK_LIST_REQUEST_SUCCESS:

      return taskListAdapter.upsertOne(state, taskListUtils.replaceTasksWithIds(action.taskList))

    case TASK_LISTS_UPDATED: {

      const taskLists = selectors.selectEntities(state)

      const matchingLists = _.filter(
        action.taskLists,
        updated => !!_.find(taskLists, taskList => taskList.username === updated.username && taskList.date === updated.date)
      )

      if (matchingLists.length === 0) {

        return state
      }

      return taskListAdapter.upsertMany(state, _.mapValues(taskLists, current => {
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
      }))
    }
    case UPDATE_TASK: {
      let newItems

      if (action.task.isAssigned) {
        newItems = taskListEntityUtils.addAssignedTask(selectors.selectEntities(state), action.task)
      } else {
        newItems = taskListEntityUtils.removeUnassignedTask(selectors.selectEntities(state), action.task)
      }

      return taskListAdapter.upsertMany(state, newItems)
    }
    case REMOVE_TASK:
      return taskListAdapter.upsertMany(state,
        taskListEntityUtils.removeUnassignedTask(selectors.selectEntities(state), action.task))
  }

  return state
}
