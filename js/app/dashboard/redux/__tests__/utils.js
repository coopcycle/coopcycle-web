import {
  withoutTasks,
  withLinkedTasks,
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

describe('withLinkedTasks', () => {

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
    }
  ]

  it('should return expected results with one task', () => {

    const actual = withLinkedTasks([
      { '@id': '/api/tasks/4', next: '/api/tasks/5' }
    ], allTasks)

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

    const actual = withLinkedTasks([
      { '@id': '/api/tasks/4', next: '/api/tasks/5' },
      { '@id': '/api/tasks/2', previous: '/api/tasks/1' }
    ], allTasks)

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
