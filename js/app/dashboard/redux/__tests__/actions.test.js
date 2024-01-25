import configureStore from 'redux-mock-store'
import thunk from 'redux-thunk'
import moment from 'moment'

import { updateTask, UPDATE_TASK, REMOVE_TASK }  from '../actions';
import { storeFixture } from './storeFixture'
import { ENABLE_DROP_IN_TOURS } from '../../../coopcycle-frontend-js/logistics/redux';
import { handleDragEnd } from '../handleDrag';


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


describe('handleDragEnd', () => {
  const store = mockStore(storeFixture)

  it ('should assign a tour to a tasklist', () => {
    const dispatch = jest.fn(),
      mockModifyTaskList = jest.fn()

    let result = {
      draggableId: 'tour:/api/tours/114',
      source: {droppableId: 'unassigned:'}, destination: {droppableId: 'assigned:admin'}
    }
  
    handleDragEnd(result, mockModifyTaskList)(dispatch, store.getState)

    expect(dispatch).toHaveBeenCalledWith(
      {type: ENABLE_DROP_IN_TOURS}
      )

    expect(mockModifyTaskList).toHaveBeenLastCalledWith(
      "admin",
      expect.arrayContaining([
        expect.objectContaining({"@id": '/api/tasks/733' }),
        expect.objectContaining({"@id": '/api/tasks/732'}),
        expect.objectContaining({"@id": '/api/tasks/729'}),
        expect.objectContaining({"@id": '/api/tasks/730'}),
        expect.objectContaining({"@id": '/api/tasks/731'}),
        expect.objectContaining({"@id": '/api/tasks/727'})
      ])
    )
      
  })

  it ('should reorder a tour inside a tasklist', () => {
    const dispatch = jest.fn(),
      mockModifyTaskList = jest.fn()

    let result = {
      draggableId: 'tour:/api/tours/114',
      source: {droppableId: 'unassigned:'}, destination: {droppableId: 'assigned:admin', index: 5}
    }
  
    handleDragEnd(result, mockModifyTaskList)(dispatch, store.getState)

    expect(mockModifyTaskList).toHaveBeenLastCalledWith(
      "admin",
      expect.arrayContaining([
        expect.objectContaining({"@id": '/api/tasks/729'}),
        expect.objectContaining({"@id": '/api/tasks/730'}),
        expect.objectContaining({"@id": '/api/tasks/731'}),
        expect.objectContaining({"@id": '/api/tasks/727'}),
        expect.objectContaining({"@id": '/api/tasks/733' }),
        expect.objectContaining({"@id": '/api/tasks/732'}),
      ])
    )
      
  })

  it ('should assign a group at the end of a tasklist', () => {
    const dispatch = jest.fn(),
      mockModifyTaskList = jest.fn()

    let result = {
      draggableId: 'group:23',
      source: {droppableId: 'unassigned:'}, destination: {droppableId: 'assigned:admin', index: 6}
    }
  
    handleDragEnd(result, mockModifyTaskList)(dispatch, store.getState)

    expect(dispatch).toHaveBeenCalledWith(
      {type: ENABLE_UNASSIGNED_TOUR_TASKS_DROPPABLE}
      )

    expect(mockModifyTaskList).toHaveBeenLastCalledWith(
      "admin",
      expect.arrayContaining([
        expect.objectContaining({"@id": '/api/tasks/729'}),
        expect.objectContaining({"@id": '/api/tasks/730'}),
        expect.objectContaining({"@id": '/api/tasks/731'}),
        expect.objectContaining({"@id": '/api/tasks/727'}),
        expect.objectContaining(
          {"@id": '/api/tasks/736', 'group': expect.objectContaining({"@id": "/api/task_groups/23"})})
      ])
    )
      
  })

})
