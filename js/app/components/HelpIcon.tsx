import React from 'react'
import { Popover } from 'antd'
import { QuestionCircleFilled } from '@ant-design/icons'

import './help-icon.scss'
import DocumentationLink from './DocumentationLInk'

function Content({ tooltipText, docsPath }) {
  return (
    <div className="help-icon-content">
      {tooltipText}
      {Boolean(docsPath) && (
        <div>
          <DocumentationLink docsPath={docsPath} />
        </div>
      )}
    </div>
  )
}

type Props = {
  className?: string
  tooltipText: string
  docsPath?: string
}

export default function HelpIcon({ className, tooltipText, docsPath }: Props) {
  return (
    <Popover
      content={<Content tooltipText={tooltipText} docsPath={docsPath} />}
      className={className}
      placement="right">
      <QuestionCircleFilled className="color-main" />
    </Popover>
  )
}
