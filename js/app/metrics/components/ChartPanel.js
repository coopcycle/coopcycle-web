import React from 'react'
import classNames from 'classnames'
import FeaturePreviewTag from '../../components/FeaturePreviewTag'

const Panel = ({ title, children, className, featurePreview }) => {
  return (
    <div className="border">
      <h5 className="bg-light m-0 p-3">
        {title} {featurePreview && <FeaturePreviewTag />}
      </h5>
      <div className={classNames('metrics-chart-panel', 'p-3', className)}>
        {children}
      </div>
    </div>
  )
}

export default Panel
