import React from 'react'

import { toTextArgs } from '../utils/rrule'

const AsText = ({ rrule }) => {

  return (
    <span>
      { rrule.toText(...toTextArgs()) }
    </span>
  )
}

export default AsText
