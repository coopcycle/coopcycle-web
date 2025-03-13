import React from 'react'
import { Tooltip } from 'antd'
import { QuestionCircleFilled } from '@ant-design/icons'

export default function HelpIcon({ tooltipText, className }) {
  return (
    <Tooltip title={tooltipText} className={className}>
      <QuestionCircleFilled className="color-main" />
    </Tooltip>
  )
}
