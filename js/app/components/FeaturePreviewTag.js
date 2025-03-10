import { Tag, Tooltip } from 'antd'
import React from 'react'
import { useTranslation } from 'react-i18next'

export default function FeaturePreviewTag() {
  const { t } = useTranslation()

  return (
    <Tooltip title={t('FEATURE_PREVIEW_HELP')}>
      <Tag
        icon={<i className="fa fa-flask" aria-hidden="true"></i>}
        color="processing">
        {t('FEATURE_PREVIEW')}
      </Tag>
    </Tooltip>
  )
}
