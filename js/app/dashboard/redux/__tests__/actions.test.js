import configureStore from 'redux-mock-store'
import thunk from 'redux-thunk'
import moment from 'moment'

import { updateTask, UPDATE_TASK, REMOVE_TASK }  from '../actions';



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
