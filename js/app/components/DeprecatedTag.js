import { Tag, Tooltip } from 'antd'
import React from 'react'
import { useTranslation } from 'react-i18next'

export default function DeprecatedTag({ tooltipText }) {
  const { t } = useTranslation()

  return (
    <Tooltip title={tooltipText}>
      <Tag
        icon={<i className="fa fa-trash mr-1" aria-hidden="true"></i>}
        color="default">
        {t('DEPRECATED')}
      </Tag>
    </Tooltip>
  )
}
