import { createEntityAdapter } from '@reduxjs/toolkit'

export const taskAdapter = createEntityAdapter({
  selectId: (task) => task['@id'],
  // sortComparer: (a, b) => a.title.localeCompare(b.title),
})

export const taskListAdapter = createEntityAdapter({
  selectId: (task) => task['@id'],
  // sortComparer: (a, b) => a.title.localeCompare(b.title),
})
