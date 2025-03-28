import React from 'react'
import { useTranslation } from 'react-i18next'
import { Popover } from 'antd'
import { QuestionCircleFilled } from '@ant-design/icons'

import './help-icon.scss'

function Content({ tooltipText, docsPath }) {
  const { t } = useTranslation()

  return (
    <div className="help-icon-content">
      {tooltipText}
      {Boolean(docsPath) && (
        <div>
          <a
            href={`https://docs.coopcycle.org${docsPath}`}
            target="_blank"
            rel="noopener noreferrer">
            {t('VIEW_DOCUMENTATION')} <i className="fa fa-external-link"></i>
          </a>
        </div>
      )}
    </div>
  )
}

export default function HelpIcon({ className, tooltipText, docsPath }) {
  return (
    <Popover
      content={<Content tooltipText={tooltipText} docsPath={docsPath} />}
      className={className}
      placement="right">
      <QuestionCircleFilled className="color-main" />
    </Popover>
  )
}
