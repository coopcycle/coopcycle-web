import { createEntityAdapter } from '@reduxjs/toolkit'

export const taskAdapter = createEntityAdapter({
  selectId: (o) => o['@id'],
  // sortComparer: (a, b) => a.title.localeCompare(b.title),
})

export const taskListAdapter = createEntityAdapter({
  selectId: (o) => o.username,
  sortComparer: (a, b) => a.username.localeCompare(b.username),
})

export const tourAdapter = createEntityAdapter({
  selectId: (o) => o['@id'],
})

export const organizationAdapter = createEntityAdapter({
  selectId: (o) => o['@id'],
})

export const vehicleAdapter = createEntityAdapter({
  selectId: (o) => o['@id'],
})

export const trailerAdapter = createEntityAdapter({
  selectId: (o) => o['@id'],
})

export const warehouseAdapter = createEntityAdapter({
  selectId: (o) => o['@id'],
})
