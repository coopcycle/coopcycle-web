import thunk from 'redux-thunk'
import moment from 'moment'
import configureMockStore from 'redux-mock-store'

import { updateTask, UPDATE_TASK, REMOVE_TASK, selectTask, selectTasksByIds, toggleTask, removeTasksFromTour }  from '../actions';
import { storeFixture } from './storeFixture';
import { selectSelectedTasks } from '../selectors';
import { createStoreFromPreloadedState } from '../store';
import { taskAdapter, tourAdapter } from '../../../coopcycle-frontend-js/logistics/redux';



// https://github.com/dmitry-zaets/redux-mock-store#asynchronous-actions
const middlewares = [ thunk]
const mockStore = configureMockStore(middlewares)

describe('updateTask', () => {

  it('should update task (legacy props)', () => {

    const store = mockStore({
      logistics: {
        date: moment('2020-02-27'),
      }
    })

    const dispatch = jest.fn()

    const task = {
      '@id': '/api/tasks/1',
      'doneAfter': '2020-02-27T09:00:00',
      'doneBefore': '2020-02-27T12:00:00',
    }

    updateTask(task)(dispatch, store.getState)

    expect(dispatch).toHaveBeenCalledTimes(1)
    expect(dispatch).toHaveBeenCalledWith({ type: UPDATE_TASK, task })
  })

  it('should remove task when date is out of range (legacy props)', () => {

    const store = mockStore({
      logistics: {
        date: moment('2020-02-27'),
      }
    })

    const dispatch = jest.fn()

    const task = {
      '@id': '/api/tasks/1',
      'doneAfter': '2020-02-28T00:00:00',
      'doneBefore': '2020-02-28T23:59:59',
    }

    updateTask(task)(dispatch, store.getState)

    expect(dispatch).toHaveBeenCalledTimes(1)
    expect(dispatch).toHaveBeenCalledWith({ type: REMOVE_TASK, task })
  })

})

describe('removeTaskFromTour', () => {

  it('should unassign+remove the task from tour', async () => {
    const store = mockStore(storeFixture)

    const dispatch = jest.fn(),
      mockModifyTour = jest.fn()

    const tour = {
      '@id': '/api/tours/111',
      name: 'tour 1',
      itemIds: [
        '/api/tasks/729',
        '/api/tasks/730',
        '/api/tasks/731',
        '/api/tasks/727'
      ],
      items: [
      {'@id': '/api/tasks/729', isAssigned: true},
      {'@id': '/api/tasks/730', isAssigned: true},
      {'@id': '/api/tasks/731', isAssigned: true},
      {'@id': '/api/tasks/727', isAssigned: true},

      ]
    }
    const task = {'@id': '/api/tasks/730', isAssigned: true}

    removeTasksFromTour(tour, task, mockModifyTour)(dispatch, store.getState)

    expect(mockModifyTour).toHaveBeenCalledTimes(1)
    expect(mockModifyTour).toHaveBeenLastCalledWith(
      expect.objectContaining({'@id': '/api/tours/111'}),
      expect.arrayContaining([
        expect.objectContaining({"@id": '/api/tasks/729'}),
        expect.objectContaining({"@id": '/api/tasks/731'}),
        expect.objectContaining({"@id": '/api/tasks/727'}),
      ])
    )
  })
})

let store
let task1 = {
  '@id': '/api/tasks/1',
}
let task2 = {
  '@id': '/api/tasks/2',
}
let task3 = {
  '@id': '/api/tasks/3',
  isAssigned: true,
  assignedTo: 'bob'
}
let task4 = {
  '@id': '/api/tasks/4',
  isAssigned: true,
  assignedTo: 'lila'
}

let allTasks = [task1, task2, task3, task4]

let tour1 = {
  '@id': '/api/tours/1',
  itemIds: ['/api/tasks/5']
}

let allTours = [tour1]


describe('selecting-tasks', () => {
  /*
    We test on the action level because it is where the validation code lives
  */

  beforeEach(() => {
    store = createStoreFromPreloadedState({
        logistics: {
          entities:
            {
              tasks: taskAdapter.upsertMany( taskAdapter.getInitialState(), allTasks),
              tours: tourAdapter.upsertMany( tourAdapter.getInitialState(), allTours)
          }
        },
        selectedTasks: [],
    })
  })

  it('should select a single task', () => {

    store.dispatch(selectTask(task1))

    expect(store.getState().selectedTasks).toStrictEqual([task1['@id']])
    expect(selectSelectedTasks(store.getState())).toStrictEqual([task1])
  })

  it('should select several tasks by id', () => {

    store.dispatch(selectTasksByIds([task1, task2].map(t => t['@id'])))

    expect(store.getState().selectedTasks).toStrictEqual([task1, task2].map(t => t['@id']))
    expect(selectSelectedTasks(store.getState())).toStrictEqual([task1, task2])
  })

  it('should toggle several tasks', () => {

    store.dispatch(toggleTask(task1, true))
    store.dispatch(toggleTask(task2, true))

    expect(store.getState().selectedTasks).toStrictEqual([task1, task2].map(t => t['@id']))
    expect(selectSelectedTasks(store.getState())).toStrictEqual([task1, task2])

    store.dispatch(toggleTask(task2, true))

    expect(store.getState().selectedTasks).toStrictEqual([task1].map(t => t['@id']))
  })

})