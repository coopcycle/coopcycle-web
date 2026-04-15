import {
  withoutTasks,
  withOrderTasks,
  timeframeToPercentage,
  nowToPercentage,
  isInDateRange,
  isTaskVisible,
} from '../utils'
import { moment } from '../../../coopcycle-frontend-js';

describe('withoutTasks', () => {

  it('should return expected results', () => {
    const tasks = [
      { '@id': '/api/tasks/1' },
      { '@id': '/api/tasks/2' },
      { '@id': '/api/tasks/3' },
      { '@id': '/api/tasks/4' },
      { '@id': '/api/tasks/5' }
    ]

    const actual = withoutTasks(tasks, [
      { '@id': '/api/tasks/3'},
      { '@id': '/api/tasks/4'}
    ])

    expect(actual).toEqual([
      { '@id': '/api/tasks/1'},
      { '@id': '/api/tasks/2'},
      { '@id': '/api/tasks/5'}
    ])
  })
})

describe('withOrderTasks', () => {

  const allTasks = [
    {
      '@id': '/api/tasks/1',
      next: '/api/tasks/2',
    }, {
      '@id': '/api/tasks/2',
      previous: '/api/tasks/1',
    }, {
      '@id': '/api/tasks/3',
    }, {
      '@id': '/api/tasks/4',
      next: '/api/tasks/5',
    }, {
      '@id': '/api/tasks/5',
      previous: '/api/tasks/4',
    },
    // Without next
    {
      '@id': '/api/tasks/6',
    }, {
      '@id': '/api/tasks/7',
      previous: '/api/tasks/6',
    }, {
      '@id': '/api/tasks/8',
      previous: '/api/tasks/7',
    },
    {
      '@id': '/api/tasks/10',
      previous: '/api/tasks/8',
    },
    // Not linked
    {
      '@id': '/api/tasks/9',
    }
  ]

  const taskIdToTourIdMap = new Map()
  taskIdToTourIdMap.set('/api/tasks/10', '/api/tours/1')

  it('should return expected results with one task', () => {

    const actual = withOrderTasks([{ '@id': '/api/tasks/4', next: '/api/tasks/5' }], allTasks, taskIdToTourIdMap)

    expect(actual).toEqual([
      {
        '@id': '/api/tasks/4',
        next: '/api/tasks/5'
      }, {
        '@id': '/api/tasks/5',
        previous: '/api/tasks/4',
      }
    ])
  })

  it('should return expected results with multiple tasks', () => {

    const actual = withOrderTasks(
      [
        { '@id': '/api/tasks/4', next: '/api/tasks/5' },
        { '@id': '/api/tasks/2', previous: '/api/tasks/1' }
      ],
      allTasks,
      taskIdToTourIdMap)

    expect(actual).toEqual([
      {
        '@id': '/api/tasks/4',
        next: '/api/tasks/5'
      }, {
        '@id': '/api/tasks/5',
        previous: '/api/tasks/4',
      },
      {
        '@id': '/api/tasks/1',
        next: '/api/tasks/2'
      }, {
        '@id': '/api/tasks/2',
        previous: '/api/tasks/1',
      }
    ])
  })

  it('should return not twice the tasks if two tasks linked together as function arguments', () => {

    const actual = withOrderTasks([
      { '@id': '/api/tasks/4', next: '/api/tasks/5' },
      { '@id': '/api/tasks/5', previous: '/api/tasks/4' }
    ], allTasks, taskIdToTourIdMap)

    expect(actual).toEqual([
      {
        '@id': '/api/tasks/4',
        next: '/api/tasks/5'
      }, {
        '@id': '/api/tasks/5',
        previous: '/api/tasks/4',
      }
    ])
  })

  it('should find the linked tasks', () => {

    const actual = withOrderTasks([
      {
        '@id': '/api/tasks/6',
      },
      { '@id': '/api/tasks/9',},
      {
        '@id': '/api/tasks/8',
        previous: '/api/tasks/7',
      },
    ], allTasks, taskIdToTourIdMap)

    expect(actual).toEqual([
      {
        '@id': '/api/tasks/6',
      },
      {
        '@id': '/api/tasks/7',
        previous: '/api/tasks/6',
      },
      { '@id': '/api/tasks/9',},
      {
        '@id': '/api/tasks/8',
        previous: '/api/tasks/7',
      },
    ])
  })

  it('should return expected results with multiple tasks (without next)', () => {

    const actual = withOrderTasks({
      '@id': '/api/tasks/6'
    }, allTasks, taskIdToTourIdMap)

    expect(actual).toEqual([
      {
        '@id': '/api/tasks/6',
      }, {
        '@id': '/api/tasks/7',
        previous: '/api/tasks/6',
      }, {
        '@id': '/api/tasks/8',
        previous: '/api/tasks/7',
      }
    ])
  })

  it('should return expected results with unlinked task', () => {

    const actual = withOrderTasks({
      '@id': '/api/tasks/9'
    }, allTasks, taskIdToTourIdMap)

    expect(actual).toEqual([
      {
        '@id': '/api/tasks/9',
      }
    ])
  })

  it('should not return linked tasks in a different tour', () => {

    const actual = withOrderTasks({
      '@id': '/api/tasks/8', previous: '/api/tasks/7'
    }, allTasks, taskIdToTourIdMap)

    expect(actual).toEqual([
      {
        '@id': '/api/tasks/6',
      }, {
        '@id': '/api/tasks/7',
        previous: '/api/tasks/6',
      }, {
        '@id': '/api/tasks/8',
        previous: '/api/tasks/7',
      },
    ])
  })

  it('should keep the original order if tasks of orders are, not regroup the tasks of the same order together', () => {

    const actual = withOrderTasks([
      {
        '@id': '/api/tasks/1',
        next: '/api/tasks/2',
      }, {
        '@id': '/api/tasks/4',
        next: '/api/tasks/5',
      },
      {
        '@id': '/api/tasks/2',
        previous: '/api/tasks/1',
      },
      {
        '@id': '/api/tasks/5',
        previous: '/api/tasks/4',
      },
    ], allTasks, taskIdToTourIdMap)

    expect(actual).toEqual([
      {
        '@id': '/api/tasks/1',
        next: '/api/tasks/2',
      }, {
        '@id': '/api/tasks/4',
        next: '/api/tasks/5',
      },
      {
        '@id': '/api/tasks/2',
        previous: '/api/tasks/1',
      },
      {
        '@id': '/api/tasks/5',
        previous: '/api/tasks/4',
      },
    ])
  })

})

describe('timeframeToPercentage', () => {

  it('should return expected results', () => {

    expect(timeframeToPercentage(['2020-02-27 12:00:00', '2020-02-27 18:00:00'], '2020-02-27')).toEqual([
      0.5,
      0.75
    ])
    expect(timeframeToPercentage(['2020-02-27 09:00:00', '2020-02-27 21:00:00'], '2020-02-27')).toEqual([
      0.375,
      0.875
    ])
    expect(timeframeToPercentage(['2020-02-27 09:00:00', '2020-02-28 12:00:00'], '2020-02-27')).toEqual([
      0.375,
      1.0
    ])
    expect(timeframeToPercentage(['2020-02-27 09:00:00', '2020-02-28 12:00:00'], '2020-02-28')).toEqual([
      0.0,
      0.5
    ])
  })
})

describe('nowToPercentage', () => {

  it('should return expected results', () => {
    expect(nowToPercentage('2020-02-27 00:00:00')).toEqual(0.0)
    expect(nowToPercentage('2020-02-27 12:00:00')).toEqual(0.5)
    expect(nowToPercentage('2020-02-27 18:00:00')).toEqual(0.75)
  })
})

describe('isInDateRange', () => {
  it('should return false (task out of range)', () => {
    let task = {
      '@id': 1,
      status: 'TODO',
      isAssigned: false,
      doneAfter: '2019-11-21 09:00:00',
      doneBefore: '2019-11-21 13:00:00',
    }

    const date = moment('2019-11-20')

    expect(isInDateRange(task, date)).toEqual(false)
  })

  it('should return true (task inside the range)', () => {
    const task = {
      '@id': 1,
      status: 'TODO',
      isAssigned: false,
      doneAfter: '2019-11-19 09:00:00',
      doneBefore: '2019-11-21 19:00:00',
    }

    const date = moment('2019-11-20')

    expect(isInDateRange(task, date)).toEqual(true)
  })
})

describe('isTaskVisible', () => {

  const baseTask = {
    '@id': '/api/tasks/1',
    status: 'TODO',
    isAssigned: false,
    tags: [],
    orgName: null,
    hasIncidents: false,
    assignedTo: null,
    doneAfter: '2024-01-01 09:00:00',
    doneBefore: '2024-01-01 10:00:00',
  }

  const baseFilters = {
    showFinishedTasks: true,
    showCancelledTasks: false,
    showIncidentReportedTasks: true,
    alwaysShowUnassignedTasks: false,
    tags: [],
    excludedTags: [],
    includedOrgs: [],
    excludedOrgs: [],
    hiddenCouriers: [],
    timeRange: [0, 24],
    onlyFilter: null,
    unassignedTasksFilters: {
      includedTags: [],
      excludedTags: [],
      includedOrgs: [],
      excludedOrgs: [],
    },
  }

  describe('status filters take precedence over alwaysShowUnassignedTasks', () => {

    it('shows unassigned TODO task when alwaysShowUnassignedTasks is true', () => {
      const filters = { ...baseFilters, alwaysShowUnassignedTasks: true }
      expect(isTaskVisible(baseTask, filters)).toBe(true)
    })

    it('hides unassigned DONE task when showFinishedTasks is false, even with alwaysShowUnassignedTasks', () => {
      const task = { ...baseTask, status: 'DONE' }
      const filters = { ...baseFilters, showFinishedTasks: false, alwaysShowUnassignedTasks: true }
      expect(isTaskVisible(task, filters)).toBe(false)
    })

    it('shows unassigned DONE task when showFinishedTasks is true and alwaysShowUnassignedTasks is true', () => {
      const task = { ...baseTask, status: 'DONE' }
      const filters = { ...baseFilters, showFinishedTasks: true, alwaysShowUnassignedTasks: true }
      expect(isTaskVisible(task, filters)).toBe(true)
    })

    it('hides unassigned FAILED task when showFinishedTasks is false, even with alwaysShowUnassignedTasks', () => {
      const task = { ...baseTask, status: 'FAILED' }
      const filters = { ...baseFilters, showFinishedTasks: false, alwaysShowUnassignedTasks: true }
      expect(isTaskVisible(task, filters)).toBe(false)
    })

    it('hides unassigned CANCELLED task when showCancelledTasks is false, even with alwaysShowUnassignedTasks', () => {
      const task = { ...baseTask, status: 'CANCELLED' }
      const filters = { ...baseFilters, showCancelledTasks: false, alwaysShowUnassignedTasks: true }
      expect(isTaskVisible(task, filters)).toBe(false)
    })

    it('shows unassigned CANCELLED task when showCancelledTasks is true and alwaysShowUnassignedTasks is true', () => {
      const task = { ...baseTask, status: 'CANCELLED' }
      const filters = { ...baseFilters, showCancelledTasks: true, alwaysShowUnassignedTasks: true }
      expect(isTaskVisible(task, filters)).toBe(true)
    })

    it('hides unassigned task with incident when showIncidentReportedTasks is false, even with alwaysShowUnassignedTasks', () => {
      const task = { ...baseTask, hasIncidents: true }
      const filters = { ...baseFilters, showIncidentReportedTasks: false, alwaysShowUnassignedTasks: true }
      expect(isTaskVisible(task, filters)).toBe(false)
    })

  })

  describe('alwaysShowUnassignedTasks bypasses unassigned-specific filters', () => {

    it('hides unassigned task not matching includedTags when alwaysShowUnassignedTasks is false', () => {
      const task = { ...baseTask, tags: [] }
      const filters = {
        ...baseFilters,
        alwaysShowUnassignedTasks: false,
        unassignedTasksFilters: { ...baseFilters.unassignedTasksFilters, includedTags: ['urgent'] },
      }
      expect(isTaskVisible(task, filters)).toBe(false)
    })

    it('shows unassigned task not matching includedTags when alwaysShowUnassignedTasks is true', () => {
      const task = { ...baseTask, tags: [] }
      const filters = {
        ...baseFilters,
        alwaysShowUnassignedTasks: true,
        unassignedTasksFilters: { ...baseFilters.unassignedTasksFilters, includedTags: ['urgent'] },
      }
      expect(isTaskVisible(task, filters)).toBe(true)
    })

    it('hides unassigned task matching excludedTags when alwaysShowUnassignedTasks is false', () => {
      const task = { ...baseTask, tags: [{ slug: 'fragile' }] }
      const filters = {
        ...baseFilters,
        alwaysShowUnassignedTasks: false,
        unassignedTasksFilters: { ...baseFilters.unassignedTasksFilters, excludedTags: ['fragile'] },
      }
      expect(isTaskVisible(task, filters)).toBe(false)
    })

    it('shows unassigned task matching excludedTags when alwaysShowUnassignedTasks is true', () => {
      const task = { ...baseTask, tags: [{ slug: 'fragile' }] }
      const filters = {
        ...baseFilters,
        alwaysShowUnassignedTasks: true,
        unassignedTasksFilters: { ...baseFilters.unassignedTasksFilters, excludedTags: ['fragile'] },
      }
      expect(isTaskVisible(task, filters)).toBe(true)
    })

    it('hides unassigned task not matching includedOrgs when alwaysShowUnassignedTasks is false', () => {
      const task = { ...baseTask, orgName: 'Acme' }
      const filters = {
        ...baseFilters,
        alwaysShowUnassignedTasks: false,
        unassignedTasksFilters: { ...baseFilters.unassignedTasksFilters, includedOrgs: ['OtherOrg'] },
      }
      expect(isTaskVisible(task, filters)).toBe(false)
    })

    it('shows unassigned task not matching includedOrgs when alwaysShowUnassignedTasks is true', () => {
      const task = { ...baseTask, orgName: 'Acme' }
      const filters = {
        ...baseFilters,
        alwaysShowUnassignedTasks: true,
        unassignedTasksFilters: { ...baseFilters.unassignedTasksFilters, includedOrgs: ['OtherOrg'] },
      }
      expect(isTaskVisible(task, filters)).toBe(true)
    })

  })

  describe('alwaysShowUnassignedTasks bypasses hiddenCouriers for unassigned tasks', () => {

    // When hiddenCouriers is set, unassigned tasks are normally hidden too.
    // alwaysShowUnassignedTasks overrides this because it returns early before
    // the hiddenCouriers check is reached.

    it('hides unassigned task when hiddenCouriers is set and alwaysShowUnassignedTasks is false', () => {
      const filters = { ...baseFilters, hiddenCouriers: ['bob'] }
      expect(isTaskVisible(baseTask, filters)).toBe(false)
    })

    it('shows unassigned task when hiddenCouriers is set but alwaysShowUnassignedTasks is true', () => {
      const filters = { ...baseFilters, hiddenCouriers: ['bob'], alwaysShowUnassignedTasks: true }
      expect(isTaskVisible(baseTask, filters)).toBe(true)
    })

  })

  describe('onlyFilter overrides all other filters', () => {

    it('shows only cancelled tasks when onlyFilter is showCancelledTasks', () => {
      const cancelled = { ...baseTask, status: 'CANCELLED' }
      const notCancelled = { ...baseTask, status: 'TODO' }
      const filters = { ...baseFilters, onlyFilter: 'showCancelledTasks' }
      expect(isTaskVisible(cancelled, filters)).toBe(true)
      expect(isTaskVisible(notCancelled, filters)).toBe(false)
    })

    it('shows only incident-reported tasks when onlyFilter is showIncidentReportedTasks', () => {
      const withIncident = { ...baseTask, hasIncidents: true }
      const withoutIncident = { ...baseTask, hasIncidents: false }
      const filters = { ...baseFilters, onlyFilter: 'showIncidentReportedTasks' }
      expect(isTaskVisible(withIncident, filters)).toBe(true)
      expect(isTaskVisible(withoutIncident, filters)).toBe(false)
    })

    it('hides everything for an unknown onlyFilter value', () => {
      const filters = { ...baseFilters, onlyFilter: 'unknownFilter' }
      expect(isTaskVisible(baseTask, filters)).toBe(false)
    })

  })

  describe('assigned tasks are not affected by alwaysShowUnassignedTasks', () => {

    it('shows assigned task regardless of alwaysShowUnassignedTasks value', () => {
      const task = { ...baseTask, isAssigned: true, assignedTo: 'alice' }
      const filtersOff = { ...baseFilters, alwaysShowUnassignedTasks: false }
      const filtersOn  = { ...baseFilters, alwaysShowUnassignedTasks: true }
      expect(isTaskVisible(task, filtersOff)).toBe(true)
      expect(isTaskVisible(task, filtersOn)).toBe(true)
    })

    it('hides assigned task whose courier is in hiddenCouriers', () => {
      const task = { ...baseTask, isAssigned: true, assignedTo: 'alice' }
      const filters = { ...baseFilters, hiddenCouriers: ['alice'] }
      expect(isTaskVisible(task, filters)).toBe(false)
    })

    it('shows assigned task whose courier is not in hiddenCouriers', () => {
      const task = { ...baseTask, isAssigned: true, assignedTo: 'alice' }
      const filters = { ...baseFilters, hiddenCouriers: ['bob'] }
      expect(isTaskVisible(task, filters)).toBe(true)
    })

  })

})
