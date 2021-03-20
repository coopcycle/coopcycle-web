import { createEntityAdapter } from '@reduxjs/toolkit'

export default createEntityAdapter({
  selectId: (task) => task['@id'],
  // sortComparer: (a, b) => a.title.localeCompare(b.title),
})
