import _ from 'lodash';
import { createTempTaskList } from './taskListUtils'

function addTaskIdIfMissing(taskIds, taskId) {

  const taskIdIndex = _.findIndex(taskIds, t => t === taskId)

  if (-1 !== taskIdIndex) {
    return taskIds
  } else {
    return taskIds.concat([ taskId ])
  }
}

function removeTaskId(taskIds, taskId) {
  return _.filter(taskIds, t => t !== taskId)
}

export function findTaskListByUsername(taskListsById, username) {
  return _.find(Object.values(taskListsById), t => t.username == username)
}

export function findTaskListByTask(taskListsById, task) {
  return _.find(Object.values(taskListsById), taskList => {
    return _.includes(taskList.items, task['@id'])
  })
}

export function addAssignedTask(taskListsById, task) {
  const taskListsToUpdate = []

  let currentTaskList = findTaskListByTask(taskListsById, task)
  let targetTaskList = findTaskListByUsername(taskListsById, task.assignedTo)

  if (currentTaskList) {
    if (targetTaskList.username !== currentTaskList.username) {
      //unassign
      taskListsToUpdate.push({
        ...currentTaskList,
        items: removeTaskId(currentTaskList.items, task['@id'])
      })
    } else {
      return []
    }
  }

  //assign
  if (targetTaskList) {
    taskListsToUpdate.push({
      ...targetTaskList,
      items: addTaskIdIfMissing(targetTaskList.items, task['@id'])
    })
  } else {
    let newTaskList = createTempTaskList(task.assignedTo, [task['@id']])
    taskListsToUpdate.push(newTaskList)
  }

  return taskListsToUpdate
}

export function removeUnassignedTask(taskListsById, task) {
  const taskListsToUpdate = []

  let taskList = findTaskListByTask(taskListsById, task)

  if (taskList) {
    //unassign
    taskListsToUpdate.push({
      ...taskList,
      items: removeTaskId(taskList.items, task['@id'])
    })
  }

  return taskListsToUpdate
}
