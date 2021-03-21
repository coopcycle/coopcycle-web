import _ from 'lodash';
import { createTempTaskList, replaceTasksWithIds } from './taskListUtils'

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
    return _.includes(taskList.itemIds, task['@id'])
  })
}

export function addAssignedTask(taskListsById, task) {
  let newItems = Object.assign({}, taskListsById)

  let currentTaskList = findTaskListByTask(taskListsById, task)
  let targetTaskList = findTaskListByUsername(taskListsById, task.assignedTo)

  if (currentTaskList != null) {
    if (targetTaskList.username !== currentTaskList.username) {
      //unassign
      newItems[currentTaskList['@id']] = {
        ...currentTaskList,
        itemIds: removeTaskId(currentTaskList.itemIds, task['@id'])
      }
    }
  }

  //assign
  if (targetTaskList != null) {
    newItems[targetTaskList['@id']] = {
      ...targetTaskList,
      itemIds: addTaskIdIfMissing(targetTaskList.itemIds, task['@id'])
    }

  } else {
    let newTaskList = createTempTaskList(task.assignedTo, [task])
    newTaskList = replaceTasksWithIds(newTaskList)

    newItems[newTaskList['@id']] = newTaskList
  }

  return newItems
}

export function removeUnassignedTask(taskListsById, task) {
  let newItems = Object.assign({}, taskListsById)

  let taskList = findTaskListByTask(taskListsById, task)

  if (taskList != null) {
    //unassign
    newItems[taskList['@id']] = {
      ...taskList,
      itemIds: removeTaskId(taskList.itemIds, task['@id'])
    }
  }

  return newItems
}
