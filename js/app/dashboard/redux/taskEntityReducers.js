import _ from 'lodash'
import {
  UPDATE_TASK,
  DELETE_GROUP_SUCCESS,
  REMOVE_TASK,
  CREATE_GROUP_SUCCESS,
  REMOVE_TASKS_FROM_GROUP_SUCCESS,
  ADD_TASKS_TO_GROUP_SUCCESS,
  MODIFY_TASK_LIST_REQUEST,
  MODIFY_TASK_LIST_REQUEST_FAILURE,
} from './actions'
import { taskAdapter } from '../../coopcycle-frontend-js/logistics/redux'

const initialState = taskAdapter.getInitialState()
const selectors = taskAdapter.getSelectors((state) => state)

/**
 * Task lists are modified optimistically, i.e. before the API confirms the change.
 * The assignment lives both on the task list (items) and on the tasks themselves
 * (isAssigned/assignedTo), so we have to keep the tasks in sync too. Otherwise the
 * tasks keep looking unassigned until the WebSocket event arrives, and filters
 * relying on those props (hidden couriers, unassigned tasks filters) hide them.
 * @see js/app/dashboard/redux/utils.js isTaskVisible()
 */
const applyAssignment = (state, username, assignedTaskIds, unassignedTaskIds) => {
  const updates = []

  const pushUpdate = (taskId, changes) => {
    const task = selectors.selectById(state, taskId)
    if (task) {
      updates.push({ ...task, ...changes })
    }
  }

  unassignedTaskIds.forEach(taskId => pushUpdate(taskId, { isAssigned: false, assignedTo: null }))
  assignedTaskIds.forEach(taskId => pushUpdate(taskId, { isAssigned: true, assignedTo: username }))

  if (updates.length === 0) {
    return state
  }

  return taskAdapter.upsertMany(state, updates)
}

export default (state = initialState, action) => {
  switch (action.type) {
    case UPDATE_TASK:
      return taskAdapter.upsertOne(state, action.task)

    case MODIFY_TASK_LIST_REQUEST:
    case MODIFY_TASK_LIST_REQUEST_FAILURE:
      return applyAssignment(
        state,
        action.username,
        action.assignedTaskIds ?? [],
        action.unassignedTaskIds ?? []
      )

    case DELETE_GROUP_SUCCESS:
      const tasksMatchingGroup = _.filter(
        selectors.selectAll(state),
        t => t.group && t.group['@id'] === action.group
      )

      if (tasksMatchingGroup.length === 0) {
        return state
      }

      return taskAdapter.removeMany(state, tasksMatchingGroup.map(t => t['@id']))

    case REMOVE_TASK:
      return taskAdapter.removeOne(state, action.task['@id'])

    case CREATE_GROUP_SUCCESS:

      const tasksMatchingCreatedGroup = _.filter(
        selectors.selectAll(state),
        t => _.includes(action.taskGroup.tasks, t['@id'])
      )

      if (tasksMatchingCreatedGroup.length === 0) {
        return state
      }

      return taskAdapter.upsertMany(state, tasksMatchingCreatedGroup.map(t => ({
        ...t,
        group: _.pickBy({
          ...action.taskGroup,
          tags: [],
        }, (value, key) => key !== 'tasks')
      })))

    case REMOVE_TASKS_FROM_GROUP_SUCCESS:

      return taskAdapter.upsertMany(state, action.tasks.map(t => ({
        ...t,
        group: null
      })))

    case ADD_TASKS_TO_GROUP_SUCCESS:

      return taskAdapter.upsertMany(state, action.tasks.map(t => ({
        ...t,
        group: _.pickBy({
          ...action.taskGroup,
          tags: [],
        }, (value, key) => key !== 'tasks')
      })))
  }

  return state
}
