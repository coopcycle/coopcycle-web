import _ from 'lodash';
import moment from 'moment';
import { tasksToIds } from './taskUtils'

export function replaceTasksWithIds(taskList) {
  let entity = {
    ...taskList,
  }

  entity.itemIds = tasksToIds(taskList.items)
  delete entity.items

  return entity
}

export function createTempTaskList(username, items = []) {

  return {
    '@context': '/api/contexts/TaskList',
    '@id': 'temp_' + username,
    '@type': 'TaskList',
    distance: 0,
    duration: 0,
    polyline: '',
    createdAt: moment().format(),
    updatedAt: moment().format(),
    username,
    items,
  }
}

export function assignedTasks(taskLists) {
  return _.flatMap(taskLists,taskList => taskList.items)
}
