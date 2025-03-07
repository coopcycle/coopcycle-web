import {
  withoutTasks,
  withOrderTasks,
  timeframeToPercentage,
  nowToPercentage,
  isInDateRange,
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
