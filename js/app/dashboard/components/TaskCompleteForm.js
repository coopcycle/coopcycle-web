import React from 'react'
import { useTranslation } from 'react-i18next'
import { Formik } from 'formik'

import TaskModalHeader from './TaskModalHeader'

const TaskCompleteForm = ({ loading, completeTaskErrorMessage, onSubmit, onCloseClick }) => {

  const { t } = useTranslation()

  const initialValues = {
    notes: '',
    success: true,
  }

  return (
    <Formik
      initialValues={ initialValues }
      onSubmit={ onSubmit }
    >
      {({
        values,
        handleChange,
        handleBlur,
        handleSubmit,
        setFieldValue,
      }) => (
        <form name="task_complete" onSubmit={ handleSubmit }>
          <TaskModalHeader task={ values }
            onCloseClick={ onCloseClick } />
          <div className="modal-body">
            <div className={ completeTaskErrorMessage ? 'form-group form-group-sm has-error' : 'form-group form-group-sm' }>
              <label className="control-label required">{ t('ADMIN_DASHBOARD_COMPLETE_FORM_COMMENTS_LABEL') }</label>
              <textarea name="notes" rows="2"
                placeholder={ t('ADMIN_DASHBOARD_COMPLETE_FORM_COMMENTS_PLACEHOLDER') }
                className="form-control"
                onChange={ handleChange }
                onBlur={ handleBlur }
                value={ values.notes }></textarea>
              { completeTaskErrorMessage && (
                <span className="help-block">{ completeTaskErrorMessage }</span>
              )}
            </div>
          </div>
          <div className="modal-footer">
            <button
              type="button"
              className="btn btn-transparent pull-left"
              disabled={ loading }
              onClick={ () => {
                // https://github.com/formium/formik/issues/214
                setFieldValue('success', false, false)
                handleSubmit()
              }}
            >
              { loading && (
                <span><i className="fa fa-spinner fa-spin"></i> </span>
              )}
              <span className="text-danger">{ t('ADMIN_DASHBOARD_COMPLETE_FORM_FAILURE') }</span>
            </button>
            <button
              type="button"
              className="btn btn-success"
              disabled={ loading }
              onClick={ () => {
                // https://github.com/formium/formik/issues/214
                setFieldValue('success', true, false)
                handleSubmit()
              }}
            >
              { loading && (
                <span><i className="fa fa-spinner fa-spin"></i> </span>
              )}
              <span>{ t('ADMIN_DASHBOARD_COMPLETE_FORM_SUCCESS') }</span>
            </button>
          </div>
        </form>
      )}
    </Formik>
  )
}

export default TaskCompleteForm
