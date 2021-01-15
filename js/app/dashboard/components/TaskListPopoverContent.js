import React from 'react'

export default ({ onClickCancel, onClickConfirm, t }) => {

  return (
    <div>
      <div className="text-center">
        <small>{ t('ADMIN_DASHBOARD_UNASSIGN_ALL_TASKS') }</small>
      </div>
      <div style={{ display: 'flex', justifyContent: 'space-between' }}>
        <button className="btn btn-xs btn-default" onClick={ onClickCancel }>{ t('ADMIN_DASHBOARD_CANCEL') }</button>
        <button className="btn btn-xs btn-danger" onClick={ onClickConfirm }>{ t('CROPPIE_CONFIRM') }</button>
      </div>
    </div>
  )
}
