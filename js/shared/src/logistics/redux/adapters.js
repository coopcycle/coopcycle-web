import { createEntityAdapter } from '@reduxjs/toolkit'

export const taskAdapter = createEntityAdapter({
  selectId: (o) => o['@id'],
  // sortComparer: (a, b) => a.title.localeCompare(b.title),
})

export const taskListAdapter = createEntityAdapter({
  selectId: (o) => o.username,
  sortComparer: (a, b) => a.username.localeCompare(b.username),
})
