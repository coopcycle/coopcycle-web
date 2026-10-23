import moment from 'moment'

import { defaultFilters } from '../settingsReducers'
import { selectHiddenTasksCount } from '../selectors'

const task = (id, props = {}) => ({
  '@id': `/api/tasks/${id}`,
  '@type': 'Task',
  id,
  type: 'DROPOFF',
  status: 'TODO',
  tags: [],
  orgName: 'test',
  isAssigned: true,
  assignedTo: 'admin',
  hasIncidents: false,
  after: '2024-01-09T00:00:00+01:00',
  before: '2024-01-09T23:59:59+01:00',
  doneAfter: '2024-01-09T00:00:00+01:00',
  doneBefore: '2024-01-09T23:59:59+01:00',
  packages: [],
  ...props,
})

const makeState = (tasks, filters = {}) => ({
  logistics: {
    date: moment('2024-01-09'),
    entities: {
      tasks: {
        ids: tasks.map(t => t['@id']),
        entities: Object.fromEntries(tasks.map(t => [t['@id'], t])),
      },
    },
  },
  config: {
    timezone: 'Europe/Paris',
  },
  settings: {
    filters: { ...defaultFilters, ...filters },
  },
})

describe('selectHiddenTasksCount', () => {
  it('returns 0 with the default filters', () => {
    const state = makeState([task(1), task(2), task(3)])

    expect(selectHiddenTasksCount(state)).toBe(0)
  })

  it('counts the tasks hidden by a filter', () => {
    // showCancelledTasks is false by default
    const state = makeState([
      task(1),
      task(2, { status: 'CANCELLED' }),
      task(3, { status: 'CANCELLED' }),
    ])

    expect(selectHiddenTasksCount(state)).toBe(2)
  })

  it('counts tasks hidden because they have an open incident', () => {
    const state = makeState(
      [task(1), task(2, { hasIncidents: true })],
      { showIncidentReportedTasks: false },
    )

    expect(selectHiddenTasksCount(state)).toBe(1)
  })

  it('counts every task when an "only" filter matches none of them', () => {
    const state = makeState(
      [task(1), task(2)],
      { showIncidentReportedTasks: true, onlyFilter: 'showIncidentReportedTasks' },
    )

    expect(selectHiddenTasksCount(state)).toBe(2)
  })
})
