import {
  groupLinkedTasks,
  removedTasks,
  withoutTasks,
  withLinkedTasks,
  timeframeToPercentage,
  nowToPercentage,
} from '../redux/utils'

describe('groupLinkedTasks', () => {

  it('should group when tasks are ordered', () => {

    const tasks = [
      {
        '@id': '/api/tasks/1',
        id : 1,
        next: '/api/tasks/2',
      }, {
        '@id': '/api/tasks/2',
        id : 2,
        previous: '/api/tasks/1',
      }, {
        '@id': '/api/tasks/3',
        id : 3,
      }
    ]

    const groups = groupLinkedTasks(tasks)

    expect(groups).toEqual({
      '/api/tasks/1': [ 1, 2 ],
      '/api/tasks/2': [ 1, 2 ],
    })
  })

  it('should group when tasks are not ordered', () => {

    const tasks = [
      {
        '@id': '/api/tasks/2',
        id : 2,
        previous: '/api/tasks/1',
      }, {
        '@id': '/api/tasks/1',
        id : 1,
        next: '/api/tasks/2',
      },  {
        '@id': '/api/tasks/3',
        id : 3,
      }
    ]

    const groups = groupLinkedTasks(tasks)

    expect(groups).toEqual({
      '/api/tasks/1': [ 1, 2 ],
      '/api/tasks/2': [ 1, 2 ],
    })
  })

  it('should group when there are more than 2 tasks', () => {

    const tasks = [
      {
        '@id': '/api/tasks/1',
        id : 1,
        next: '/api/tasks/2',
      }, {
        '@id': '/api/tasks/2',
        id : 2,
        previous: '/api/tasks/1',
        next: '/api/tasks/3',
      }, {
        '@id': '/api/tasks/3',
        id : 3,
        previous: '/api/tasks/2',
      }
    ]

    const groups = groupLinkedTasks(tasks)

    expect(groups).toEqual({
      '/api/tasks/1': [ 1, 2, 3 ],
      '/api/tasks/2': [ 1, 2, 3 ],
      '/api/tasks/3': [ 1, 2, 3 ],
    })
  })

  it('should group multiple', () => {

    const tasks = [
      {
        '@id': '/api/tasks/1',
        id : 1,
        next: '/api/tasks/2',
      }, {
        '@id': '/api/tasks/2',
        id : 2,
        previous: '/api/tasks/1',
      }, {
        '@id': '/api/tasks/3',
        id : 3,
      },{
        '@id': '/api/tasks/4',
        id : 4,
        next: '/api/tasks/5',
      }, {
        '@id': '/api/tasks/5',
        id : 5,
        previous: '/api/tasks/4',
      }
    ]

    const groups = groupLinkedTasks(tasks)

    expect(groups).toEqual({
      '/api/tasks/1': [ 1, 2 ],
      '/api/tasks/2': [ 1, 2 ],
      '/api/tasks/4': [ 4, 5 ],
      '/api/tasks/5': [ 4, 5 ],
    })
  })

})

describe('removedTasks', () => {

  it('should return expected results', () => {
    const tasks = [
      { '@id': '/api/tasks/1' },
      { '@id': '/api/tasks/2' },
      { '@id': '/api/tasks/3' },
      { '@id': '/api/tasks/4' },
      { '@id': '/api/tasks/5' }
    ]

    const actual = removedTasks(tasks, [
      { '@id': '/api/tasks/1'},
      { '@id': '/api/tasks/2'},
      { '@id': '/api/tasks/5'}
    ])

    expect(actual).toEqual([
      { '@id': '/api/tasks/3'},
      { '@id': '/api/tasks/4'},
    ])
  })
})

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
