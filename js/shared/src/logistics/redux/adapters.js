import { createEntityAdapter } from '@reduxjs/toolkit'

export const taskAdapter = createEntityAdapter({
  selectId: (o) => o['@id'],
})

export const taskListAdapter = createEntityAdapter({
  selectId: (o) => o['@id'],
  sortComparer: (a, b) => a.username.localeCompare(b.username),
})
