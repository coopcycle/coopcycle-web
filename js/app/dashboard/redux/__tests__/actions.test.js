import configureStore from 'redux-mock-store'
import thunk from 'redux-thunk'
import moment from 'moment'

import { updateTask, UPDATE_TASK, REMOVE_TASK, removeTaskFromTour }  from '../actions';
import { storeFixture } from './storeFixture';



// https://github.com/dmitry-zaets/redux-mock-store#asynchronous-actions
const middlewares = [ thunk]
const mockStore = configureStore(middlewares)

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
      mockUnassignTasks = jest.fn(),
      mockModifyTour = jest.fn()

    dispatch.mockReturnValue().mockResolvedValueOnce({}).mockReturnValue()

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
      {'@id': '/api/tasks/729', isAssigned: true, tour: { '@id': '/api/tours/111',  name: 'tour 1', position: 0 }},
      {'@id': '/api/tasks/730', isAssigned: true, tour: { '@id': '/api/tours/111',  name: 'tour 1', position: 1 }},
      {'@id': '/api/tasks/731', isAssigned: true, tour: { '@id': '/api/tours/111',  name: 'tour 1', position: 2 }},
      {'@id': '/api/tasks/727', isAssigned: true, tour: { '@id': '/api/tours/111',  name: 'tour 1', position: 3 }},

      ]
    }
    const task = {'@id': '/api/tasks/730', isAssigned: true, tour: { '@id': '/api/tours/111',  name: 'tour 1', position: 1 }}

    removeTaskFromTour(tour, task, 'admin',  mockUnassignTasks, mockModifyTour)(dispatch, store.getState)
    
    expect(mockUnassignTasks).toHaveBeenCalledTimes(1)
    expect(mockUnassignTasks).toHaveBeenCalledWith(
      "admin",
      expect.arrayContaining([
        expect.objectContaining({"@id": '/api/tasks/730'}),
      ]),
      expect.any(Function)

    )
    
    // kind of hackish way to wait for mockModifyTaskList.then(...) to resolve before testing the call to modifyTour
    await Promise.resolve();
    
    expect(mockModifyTour).toHaveBeenCalledTimes(1)
    expect(mockModifyTour).toHaveBeenLastCalledWith(
      expect.objectContaining({'@id': '/api/tours/111'}),
      expect.arrayContaining([
        expect.objectContaining({"@id": '/api/tasks/729'}),
        expect.objectContaining({"@id": '/api/tasks/731'}),
        expect.objectContaining({"@id": '/api/tasks/727'}),
      ]),
      true
    )
  })
})
