import moment from 'moment'

export function createTaskList(username) {

  return {
    '@context': '/api/contexts/TaskList',
    '@id': null,
    '@type': 'TaskList',
    distance: 0,
    duration: 0,
    polyline: '',
    createdAt: moment().format(),
    updatedAt: moment().format(),
    username,
    items:[],
  }
}
