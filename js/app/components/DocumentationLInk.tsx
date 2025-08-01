import React from 'react'
import { useTranslation } from 'react-i18next'

type Props = {
  docsPath: string
  className?: string
}

export default function DocumentationLink({ docsPath, className }: Props) {
  const { t } = useTranslation()

  return (
    <a
      className={className}
      href={`https://docs.coopcycle.org${docsPath}`}
      target="_blank"
      rel="noopener noreferrer">
      {t('VIEW_DOCUMENTATION')} <i className="fa fa-external-link"></i>
    </a>
  )
}
