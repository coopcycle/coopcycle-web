import {
  selectCouriers,
  selectCouriersWithExclude,
  selectTaskLists,
} from '../selectors'

describe('couriers available for dispatch', () => {
  const available = { id: 1, username: 'available', enabled: true }
  const disabled = { id: 2, username: 'disabled', enabled: false }
  const planned = { id: 3, username: 'planned', enabled: true }
  const former = { id: 4, username: 'former', enabled: false }
  const couriers = [available, disabled, former, planned]
  const disabledTaskList = {
    '@id': '/api/task_lists/1',
    username: former.username,
    items: ['/api/tasks/1'],
  }
  const plannedTaskList = {
    '@id': '/api/task_lists/2',
    username: planned.username,
    items: [],
  }
  const state = {
    config: { couriersList: couriers },
    logistics: {
      entities: {
        taskLists: {
          ids: [former.username, planned.username],
          entities: {
            [former.username]: disabledTaskList,
            [planned.username]: plannedTaskList,
          },
        },
      },
    },
  }

  it('excludes disabled couriers from assignment choices', () => {
    expect(selectCouriersWithExclude(state)).toEqual([available, planned])
  })

  it('also excludes couriers already on the planning board when adding a courier', () => {
    expect(selectCouriersWithExclude(state, true)).toEqual([available])
  })

  it('keeps the courier directory and historical task lists intact', () => {
    selectCouriersWithExclude(state, true)

    expect(selectCouriers(state)).toEqual([available, disabled, former, planned])
    expect(selectTaskLists(state)).toEqual([disabledTaskList, plannedTaskList])
  })
})
