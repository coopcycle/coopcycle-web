import React from 'react'
import { useSelector } from 'react-redux'
import { useTranslation } from 'react-i18next'
import { Tooltip } from 'antd'

import { selectPrecogEnabled, selectPrecogCountsByRank } from '../redux/selectors'

/**
 * How many carts are on their way, split at the point where money is involved.
 *
 * Two numbers rather than five: the rungs matter on the map, where they say
 * where to look, but in a status bar the only question worth answering at a
 * glance is how much of this is about to become real.
 */
export default () => {

  const enabled = useSelector(selectPrecogEnabled)
  const counts = useSelector(selectPrecogCountsByRank)
  const { t } = useTranslation()

  if (!enabled) {
    return null
  }

  const browsing = counts[0] + counts[1] + counts[2]
  const paying = counts[3] + counts[4]

  if (browsing + paying === 0) {
    return null
  }

  return (
    <Tooltip title={ t('ADMIN_DASHBOARD_PRECOG_TOOLTIP') }>
      <span className="precog-counter" data-testid="precog-counter">
        <span className="precog-counter__dot"></span>
        <span className="precog-counter__n">{ browsing }</span>
        { paying > 0 && (
          <>
            <span className="precog-counter__dot precog-counter__dot--warm"></span>
            <span className="precog-counter__n">{ paying }</span>
          </>
        ) }
      </span>
    </Tooltip>
  )
}
