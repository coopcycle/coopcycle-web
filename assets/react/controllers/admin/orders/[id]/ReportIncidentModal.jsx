import React, { useMemo, useState } from 'react'
import _ from 'lodash'
import { Modal } from 'antd'
import ReportIncidentModalContent from '../../../../../../js/app/components/ReportIncidentModalContent'
import { useTranslation } from 'react-i18next'

export default function ReportIncidentModal(props) {
  const firstTask = useMemo(() => {
    const { items } = {
      items: '[]',
      ...props,
    }
    const _items = _(JSON.parse(items))
      .sortBy('position')
      .map(i => i.task)
      .value()
    return _items[0]
  }, [])

  const [isOpen, setIsOpen] = useState(false)

  const { t } = useTranslation()

  return (
    <>
      <a
        onClick={() => {
          setIsOpen(true)
        }}>
        <i className="fa fa-exclamation-triangle mr-1" />
        {t('ADMIN_DASHBOARD_REPORT_INCIDENT')}
      </a>
      <Modal
        title={t('NEW_INCIDENT')}
        open={isOpen}
        onCancel={() => setIsOpen(false)}
        footer={null}
        bodyStyle={{ minHeight: '300px' }}>
        <ReportIncidentModalContent task={firstTask} />
      </Modal>
    </>
  )
}
