import configureStore from 'redux-mock-store'
import thunk from 'redux-thunk'
import moment from 'moment'

import { updateTask, UPDATE_TASK, REMOVE_TASK }  from '../actions';
import { storeFixture } from './storeFixture'
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

  it ('should assign a tour at the beginning of a tasklist', () => {
    const dispatch = jest.fn(),
      mockModifyTaskList = jest.fn()

    let result = {
      draggableId: 'tour:/api/tours/114',
      source: {droppableId: 'unassigned:'}, destination: {droppableId: 'assigned:admin', index: 0},
    }
  
    handleDragEnd(result, mockModifyTaskList)(dispatch, store.getState)


    expect(mockModifyTaskList).toHaveBeenLastCalledWith(
      "admin",
      expect.arrayContaining([
        expect.objectContaining({"@id": '/api/tasks/733' }),
        expect.objectContaining({"@id": '/api/tasks/732'}),
        expect.objectContaining({"@id": '/api/tasks/729'}),
        expect.objectContaining({"@id": '/api/tasks/730'}),
        expect.objectContaining({"@id": '/api/tasks/731'}),
        expect.objectContaining({"@id": '/api/tasks/727'})
      ]),
      null
    )
      
  })

  it ('should reorder a tour inside a tasklist', () => {
    const dispatch = jest.fn(),
      mockModifyTaskList = jest.fn()

    let result = {
      draggableId: 'tour:/api/tours/114',
      source: {droppableId: 'unassigned:'},
      destination: {droppableId: 'assigned:admin', index: 1}
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
      ]),
      null
    )
      
  })

  it ('should assign a group at the end of a tasklist',  () => {
    const dispatch = jest.fn(),
      mockModifyTaskList = jest.fn()

    let result = {
      draggableId: 'group:23',
      source: {droppableId: 'unassigned:'}, destination: {droppableId: 'assigned:admin', index: 1}
    }
  
    handleDragEnd(result, mockModifyTaskList)(dispatch, store.getState)

    expect(mockModifyTaskList).toHaveBeenLastCalledWith(
      "admin",
      expect.arrayContaining([
        expect.objectContaining({"@id": '/api/tasks/729'}),
        expect.objectContaining({"@id": '/api/tasks/730'}),
        expect.objectContaining({"@id": '/api/tasks/731'}),
        expect.objectContaining({"@id": '/api/tasks/727'}),
        expect.objectContaining(
          {"@id": '/api/tasks/736', 'group': expect.objectContaining({"@id": "/api/task_groups/23"})})
      ]),
      null
    )
      
  })

  it ('should assign a task in a tour which is already assigned', async () => {
    const dispatch = jest.fn(),
      mockModifyTaskList = jest.fn(),
      mockModifyTour = jest.fn()

    dispatch.mockReturnValue().mockResolvedValueOnce({}).mockReturnValue()

    let result = {
      draggableId: '/api/tasks/734',
      source: {droppableId: 'unassigned'}, destination: {droppableId: 'tour:/api/tours/111', index: 1}
    }
  
    handleDragEnd(result, mockModifyTaskList, mockModifyTour)(dispatch, store.getState)

    expect(mockModifyTaskList).toHaveBeenCalledWith(
      "admin",
      expect.arrayContaining([
        expect.objectContaining({"@id": '/api/tasks/729'}),
        expect.objectContaining({"@id": '/api/tasks/734'}),
        expect.objectContaining({"@id": '/api/tasks/735'}),
        expect.objectContaining({"@id": '/api/tasks/730'}),
        expect.objectContaining({"@id": '/api/tasks/731'}),
        expect.objectContaining({"@id": '/api/tasks/727'}),
      ]),
      expect.any(Function)

    )
    
    // kind of hackish way to wait for mockModifyTaskList.then(...) to reolve before testing the call to modifyTour
    await Promise.resolve();

    expect(mockModifyTour).toHaveBeenLastCalledWith(
      expect.objectContaining({'@id': '/api/tours/111'}),
      expect.arrayContaining([
        expect.objectContaining({"@id": '/api/tasks/729'}),
        expect.objectContaining({"@id": '/api/tasks/734'}),
        expect.objectContaining({"@id": '/api/tasks/735'}),
        expect.objectContaining({"@id": '/api/tasks/730'}),
        expect.objectContaining({"@id": '/api/tasks/731'}),
        expect.objectContaining({"@id": '/api/tasks/727'}),
      ]),
      true
    )
      
  })

})
