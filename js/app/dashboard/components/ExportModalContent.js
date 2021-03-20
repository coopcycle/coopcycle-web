import React, { useState } from 'react'
import { DatePicker } from 'antd'
import { useTranslation } from 'react-i18next'

const ModalContent = ({ onClickClose, onClickSubmit }) => {

  const { t } = useTranslation()
  const [ range, setRange ] = useState(null)

  return (
    <React.Fragment>
      <div className="modal-header">
        <button type="button" className="close" onClick={ onClickClose } aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 className="modal-title" id="user-modal-label">{ t('ADMIN_DASHBOARD_NAV_EXPORT') }</h4>
      </div>
      <div className="modal-body">
        <DatePicker.RangePicker
          onChange={ (dates) => setRange(dates.map(d => d.format('YYYY-MM-DD'))) } />
      </div>
      <div className="modal-footer">
        <button type="button" className="btn btn-default"
          onClick={ onClickClose }>{ t('ADMIN_DASHBOARD_CANCEL') }</button>
        <button type="submit" className="btn btn-primary"
          disabled={ range === null }
          onClick={ () => onClickSubmit(...range) }>{ t('ADMIN_DASHBOARD_NAV_EXPORT') }</button>
      </div>
    </React.Fragment>
  )
}

export default ModalContent
