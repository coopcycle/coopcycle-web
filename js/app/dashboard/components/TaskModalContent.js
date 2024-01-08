import React from 'react'
import _ from 'lodash'
import moment from 'moment'
import {connect} from 'react-redux'
import {withTranslation} from 'react-i18next'
import {DatePicker, Radio, Timeline} from 'antd';
import {Formik} from 'formik'
import {isValidPhoneNumber} from 'react-phone-number-input'

import AddressAutosuggest from '../../components/AddressAutosuggest'
import TagsSelect from '../../components/TagsSelect'
import CourierSelect from './CourierSelect'
import PhoneNumberInput from './PhoneNumberInput'
import TaskModalHeader from './TaskModalHeader'
import TaskCompleteForm from './TaskCompleteForm'
import {timePickerProps} from '../../utils/antd'

import {
  cancelTask,
  closeNewTaskModal,
  completeTask,
  createTask,
  duplicateTask,
  loadTaskEvents,
  openTaskRescheduleModal,
  restoreTask,
  startTask
} from '../redux/actions'
import {selectCurrentTask, selectCurrentTaskEvents} from '../redux/selectors'
import {selectSelectedDate} from '../../coopcycle-frontend-js/logistics/redux'
import {phoneNumberExample} from '../utils'

const itemColor = event => {
  switch (event.name) {
  case 'task:done':
    return 'green'
  case 'task:failed':
  case 'task:cancelled':
    return 'red'
  case 'task:rescheduled':
  case 'task:incident-reported':
    return 'orange'
  default:
    return 'blue'
  }
}

const isTaskTypeEditable = task => {
  if (task && (!!task.previous || !!task.next)) {
    return false
  }

  return true
}

class TaskModalContent extends React.Component {

  constructor (props) {
    super(props)
    this.state = {
      complete: false,
    }
  }

  renderCompleteForm() {

    return (
      <TaskCompleteForm
        loading={ this.props.loading }
        completeTaskErrorMessage={ this.props.completeTaskErrorMessage }
        onCloseClick={ this.onCloseClick.bind(this) }
        onSubmit={ (values) => {
          // TODO Use setSubmitting
          this.props.completeTask(this.props.task, values.notes, values.success)
        }} />
    )
  }

  onStartClick(task, e) {
    if (e) {
      e.preventDefault()
    }
    this.props.startTask(task)
  }

  onCompleteClick(e) {
    e.preventDefault()
    this.setState({ complete: true })
  }

  onCloseClick(e) {
    e.preventDefault()
    this.props.closeNewTaskModal()
  }

  onCancelClick(task) {
    if (window.confirm(this.props.t('ADMIN_DASHBOARD_CANCEL_TASK_CONFIRM', { id: task.id }))) {
      this.props.cancelTask(task)
    }
  }

  onRestoreClick(task) {
    if (window.confirm(this.props.t('ADMIN_DASHBOARD_RESTORE_TASK_CONFIRM', { id: task.id }))) {
      this.props.restoreTask(task)
    }
  }

  _validate(values) {
    let errors = {}

    if (!Object.prototype.hasOwnProperty.call(values.address, 'geo')) {
      errors.address = {
        ...errors.address,
        streetAddress: this.props.t('ADMIN_DASHBOARD_TASK_FORM_ADDRESS_ERROR')
      }
    }

    if (values.address.telephone && values.address.telephone.trim().length > 0) {
      if (!isValidPhoneNumber(values.address.telephone)) {
        errors.address = {
          ...errors.address,
          telephone: this.props.t('ADMIN_DASHBOARD_TASK_FORM_TELEPHONE_ERROR')
        }
      }
    }

    return errors
  }

  _onSubmit(values) {

    // TODO Use setSubmitting

    const { task } = this.props

    if (!!task && Object.prototype.hasOwnProperty.call(task, '@id') && Object.prototype.hasOwnProperty.call(task.address, '@id')) {
      values = {
        ...values,
        address: {
          ...values.address,
          '@id': task.address['@id']
        }
      }
    }

    // FIXME The name is bad. It creates or updates a task
    this.props.createTask(values)
  }

  renderFooter(task) {

    return (
      <div className="modal-footer">
        { (!!task && task.status === 'TODO') && (
          <button type="button" className="btn btn-danger pull-left"
            onClick={ () => this.onCancelClick(task) }
            disabled={ this.props.loading }>
            <i className="fa fa-trash"></i> <span>{ this.props.t('ADMIN_DASHBOARD_CANCEL_TASK') }</span>
          </button>
        )}
        { (!!task && task.status === 'CANCELLED') && (
          <button type="button" className="btn btn-success pull-left"
            onClick={ () => this.onRestoreClick(task) }
            disabled={ this.props.loading }>
            <i className="fa fa-rotate-left"></i> <span>{ this.props.t('ADMIN_DASHBOARD_RESTORE') }</span>
          </button>
        )}
        { (!!task && Object.prototype.hasOwnProperty.call(task, '@id')) && (
          <button type="button" className="btn pull-left"
            onClick={ () => this.props.duplicateTask(task) }
            disabled={ this.props.loading }>
            <span className="text-success">{ this.props.t('ADMIN_DASHBOARD_DUPLICATE_TASK') }</span>
          </button>
        )}
        { (!!task && (task.status === 'CANCELLED' || task.status === 'FAILED')) && (
          <button type="button" className="btn btn-warning pull-left"
                  onClick={ () => this.props.openTaskRescheduleModal() }
                  disabled={ this.props.loading }>
            <i className="fa fa-repeat"></i> <span>{ this.props.t('ADMIN_DASHBOARD_RESCHEDULE') }</span>
          </button>
        )}
        { (!!task && task.isAssigned && (task.status === 'TODO' || task.status === 'DOING')) && (
          <div className="btn-group dropup mr-3">
            <button type="button" className="btn btn-default">
              { this.props.t('ADMIN_DASHBOARD_MODIFY_TASK') }
            </button>
            <button type="button" className="btn btn-default dropdown-toggle"
              data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
              <span className="caret"></span>
              <span className="sr-only">Toggle Dropdown</span>
            </button>
            <ul className="dropdown-menu">
              { task.status === 'TODO' && (
              <li>
                <a href="#" onClick={ e => this.onStartClick(task, e) }>
                  <i className="fa fa-play mr-2"></i><span>{ this.props.t('ADMIN_DASHBOARD_START_TASK') }</span>
                </a>
              </li>
              )}
              <li>
                <a href="#" onClick={ this.onCompleteClick.bind(this) }>
                  <i className="fa fa-check mr-2"></i><span>{ this.props.t('ADMIN_DASHBOARD_COMPLETE_FORM_SUCCESS') }</span>
                </a>
              </li>
            </ul>
          </div>
        )}
        <button type="submit" className="btn btn-primary" disabled={ this.props.loading }>
          { this.props.loading && (
            <span><i className="fa fa-spinner fa-spin"></i> </span>
          )}
          <span>{ this.props.t('ADMIN_DASHBOARD_TASK_FORM_SAVE') }</span>
        </button>
      </div>
    )
  }

  renderTimelineContent() {
    if (this.props.isLoadingEvents) {
      return (
        <div className="text-center">
          <i className="fa fa-spinner fa-spin"></i>
        </div>
      )
    }

    const { events } = this.props

    events.sort((a, b) => {
      return moment(a.createdAt).isBefore(moment(b.createdAt)) ? -1 : 1
    })

    return (
      <Timeline>
        { events.map(event => (
          <Timeline.Item key={ event.createdAt + '-' + event.name } color={ itemColor(event) }>
            <p>{ moment(event.createdAt).format('lll') } { event.name }</p>
            { event.data.reason && (
              <p style={{fontFamily: 'monospace'}}>{ event.data.reason }</p>
            ) }
            { event.data.notes && (
              <p><i className="fa fa-comment" aria-hidden="true"></i> { event.data.notes }</p>
            ) }
          </Timeline.Item>
        )) }
      </Timeline>
    )
  }

  renderTimeline(task) {

    let anchorProps = {}
    if (this.props.events.length === 0) {
      anchorProps = {
        ...anchorProps,
        onClick: e => {
          e.preventDefault()
          this.props.loadTaskEvents(task)
        }
      }
    }

    return (
      <div>
        <div className="text-center">
          <a className="help-block" role="button" data-toggle="collapse" href="#task_history" aria-expanded="false" { ...anchorProps }>
            <small>{ this.props.t('ADMIN_DASHBOARD_TASK_FORM_SHOW_HISTORY') }</small>
          </a>
        </div>
        <div className="collapse" id="task_history" aria-expanded="false">
          { this.renderTimelineContent() }
        </div>
      </div>
    )
  }

  renderForm() {
    let initialValues = {
      type: 'DROPOFF',
      address: {
        streetAddress: ''
      },
      after: moment(this.props.date).set({ hour: 0, minute: 0, second: 0 }).format(),
      before: moment(this.props.date).set({ hour: 23, minute: 59, second: 59 }).format(),
      comments: '',
      tags: [],
      assignedTo: null,
      packages: [],
      weight: null,
    }

    if (!!this.props.task) {
      initialValues = {
        ...this.props.task,
        after: this.props.task.doneAfter,
        before: this.props.task.doneBefore,
      }
    }

    return (
      <Formik
        initialValues={ initialValues }
        validate={ this._validate.bind(this) }
        onSubmit={ this._onSubmit.bind(this) }
        validateOnBlur={ false }
        validateOnChange={ false }
      >
        {({
          values,
          errors,
          touched,
          handleChange,
          handleBlur,
          handleSubmit,
          setFieldValue,
          setFieldTouched,
          /* and other goodies */
        }) => (
          <form name="task" onSubmit={ handleSubmit } autoComplete="off">
            <TaskModalHeader task={ values }
              onCloseClick={ this.onCloseClick.bind(this) } />
            <div className="modal-body">
              <div className="form-group text-center">
                <Radio.Group name="type" defaultValue={ values.type } onChange={ (e) => setFieldValue('type', e.target.value) } size="large"
                  disabled={ !this.props.isTaskTypeEditable }>
                  <Radio.Button value="PICKUP">Pickup</Radio.Button>
                  <Radio.Button value="DROPOFF">Dropoff</Radio.Button>
                </Radio.Group>
              </div>
              { Object.prototype.hasOwnProperty.call(values, '@id') && this.renderTimeline(values) }
              <div className={ errors.address && touched.address && errors.address.streetAddress && touched.address.streetAddress ? 'form-group form-group-sm has-error' : 'form-group form-group-sm' }>
                <label className="control-label required">{ this.props.t('ADMIN_DASHBOARD_TASK_FORM_ADDRESS_STREET_ADDRESS_LABEL') }</label>
                <AddressAutosuggest
                  autofocus={ !Object.prototype.hasOwnProperty.call(values, '@id') }
                  address={ values.address }
                  onAddressSelected={ (value, address) => {
                    const cleanAddress =
                      _.omit(address, ['isPrecise', 'latitude', 'longitude', 'addressRegion', 'geohash', 'needsGeocoding'])

                    address = {
                      ...values.address,
                      ...cleanAddress
                    }

                    setFieldValue('address', address)
                  } } />
                { errors.address && touched.address && errors.address.streetAddress && touched.address.streetAddress && (
                  <small className="help-block">{ errors.address.streetAddress }</small>
                ) }
              </div>
              <a className="help-block" role="button" data-toggle="collapse" href="#address_options" aria-expanded="false">
                <small><i className="fa fa-plus"></i> { this.props.t('ADMIN_DASHBOARD_TASK_FORM_ADDRESS_MORE_OPTIONS') }</small>
              </a>
              <div className="collapse" id="address_options" aria-expanded="false">
                <div className="form-group form-group-sm">
                  <label className="control-label" htmlFor="address_name">{ this.props.t('ADMIN_DASHBOARD_TASK_FORM_ADDRESS_NAME_LABEL') }</label>
                  <input type="text" id="address_name" name="address.name" placeholder={ this.props.t('ADMIN_DASHBOARD_TASK_FORM_ADDRESS_NAME_PLACEHOLDER') } className="form-control"
                    autoComplete="off"
                    onChange={ handleChange }
                    onBlur={ handleBlur }
                    value={ values.address.name || '' } />
                </div>
                <div className="form-group form-group-sm">
                  <label className="control-label" htmlFor="address_contactName">{ this.props.t('ADMIN_DASHBOARD_TASK_FORM_ADDRESS_CONTACT_NAME_LABEL') }</label>
                  <input type="text" id="address_contactName" name="address.contactName" placeholder={ this.props.t('ADMIN_DASHBOARD_TASK_FORM_ADDRESS_CONTACT_NAME_PLACEHOLDER') } className="form-control"
                    autoComplete="off"
                    onChange={ handleChange }
                    onBlur={ handleBlur }
                    value={ values.address.contactName || '' } />
                </div>
                <div className={ errors.address && touched.address && errors.address.telephone && touched.address.telephone ? 'form-group form-group-sm has-error' : 'form-group form-group-sm' }>
                  <label className="control-label" htmlFor="address_telephone">{ this.props.t('ADMIN_DASHBOARD_TASK_FORM_ADDRESS_TELEPHONE_LABEL') }</label>
                  <PhoneNumberInput
                    value={ values.address.telephone ? values.address.telephone : '' }
                    onChange={ value => {
                      setFieldValue('address.telephone', value)
                      setFieldTouched('address.telephone')
                    }} />
                  { errors.address && touched.address && errors.address.telephone && touched.address.telephone && (
                    <small className="help-block">{ errors.address.telephone }</small>
                  ) }
                  <small className="help-block">
                    { phoneNumberExample }
                  </small>
                </div>
                <div className="form-group form-group-sm">
                  <label className="control-label" htmlFor="address_description">{ this.props.t('ADMIN_DASHBOARD_TASK_FORM_ADDRESS_DESCRIPTION_LABEL') }</label>
                  <textarea id="address_description" name="address.description" rows="3"
                    placeholder={ this.props.t('ADMIN_DASHBOARD_TASK_FORM_ADDRESS_DESCRIPTION_PLACEHOLDER') }
                    className="form-control"
                    autoComplete="off"
                    onChange={ handleChange }
                    onBlur={ handleBlur }
                    value={ values.address.description || '' }></textarea>
                </div>
              </div>
              <div className="form-group form-group-sm">
                <label className="control-label required">{ this.props.t('ADMIN_DASHBOARD_TASK_FORM_TIME_RANGE_LABEL') }</label>
                <div className="form-group">
                  <DatePicker.RangePicker
                    style={{ width: '100%' }}
                    showTime={{
                      ...timePickerProps,
                      hideDisabledOptions: true,
                    }}
                    format="LLL"
                    defaultValue={[ moment(values.after), moment(values.before) ]}
                    onChange={(value) => {
                      setFieldValue('after', value[0].format())
                      setFieldValue('before', value[1].format())
                    }} />
                </div>
              </div>
              <div className="form-group form-group-sm">
                <label className="control-label" htmlFor="task_comments">{ this.props.t('ADMIN_DASHBOARD_TASK_FORM_COMMENTS_LABEL') }</label>
                <textarea id="task_comments" name="comments" rows="2"
                  placeholder={ this.props.t('ADMIN_DASHBOARD_TASK_FORM_COMMENTS_PLACEHOLDER') }
                  className="form-control"
                  onChange={handleChange}
                  onBlur={handleBlur}
                  value={values.comments}></textarea>
              </div>
              {
                (values.type === "PICKUP" && values.metadata?.order_notes && !!values.metadata?.order_notes?.length) && (
                  <div className="form-group form-group-sm">
                    <label className="control-label" htmlFor="order_notes">{ this.props.t('ADMIN_DASHBOARD_TASK_FORM_ORDER_NOTES_LABEL') }</label>
                    <textarea id="order_notes" name="order_notes" rows="2"
                      className="form-control"
                      disabled="true"
                      value={values.metadata.order_notes}></textarea>
                  </div>
                )
              }
              <div className="form-group form-group-sm">
                <label className="control-label">Tags</label>
                <TagsSelect
                  tags={ this.props.tags }
                  defaultValue={ values.tags }
                  onChange={ tags => {
                    setFieldValue('tags', tags)
                    setFieldTouched('tags')
                  } } />
              </div>
              <div className="form-group form-group-sm">
                <label className="control-label">{ this.props.t('ADMIN_DASHBOARD_COURIER') }</label>
                <CourierSelect
                  username={ values.assignedTo }
                  onChange={ courier => setFieldValue('assignedTo', courier.username)}
                  isDisabled={ values.isAssigned && (values.status === 'DONE' || values.status === 'FAILED') }
                  menuPlacement="top" />
              </div>
              { (values.images && values.images.length > 0) && (
                <div>
                  <label className="control-label">Images</label>
                  <div className="row">
                    { values.images.map(image => (
                      <div className="col-xs-6 col-md-3" key={ image.id }>
                        <a href={ window.Routing.generate('admin_task_image_download', { taskId: values.id, imageId: image.id }) } className="thumbnail">
                          <img src={ image.thumbnail } />
                        </a>
                      </div>
                    )) }
                  </div>
                </div>
              )}
              { (values.packages && !!values.packages.length) && (
                <div className="form-group form-group-sm">
                  <label className="control-label">{ this.props.t('ADMIN_DASHBOARD_PACKAGES') }</label>
                  <ul className="list-group table-hover">
                    { values.packages.map(p => (
                      <li key={p.name} className="task-package list-group-item d-flex justify-content-between align-items-center">
                        {p.name}
                        <span className="badge bg-primary rounded-pill">{p.quantity}</span>
                      </li>
                    )) }
                    <li className="task-package task-package--total list-group-item d-flex justify-content-between align-items-center">
                      <span className="font-weight-bold">Total</span>
                      <span className="badge bg-warning rounded-pill">
                        { values.packages.reduce((sum, p) => sum + p.quantity, 0) }
                      </span>
                    </li>
                  </ul>
                </div>
              )}
              { (values.weight && values.weight > 0) ? (
                <div className="form-group form-group-sm">
                  <label className="control-label" htmlFor="task_weight">{ this.props.t('ADMIN_DASHBOARD_WEIGHT') }</label>
                  <input id="task_weight" name="weight"
                    className="form-control"
                    disabled
                    value={`${(Number(values.weight) / 1000).toFixed(2)} kg`}/>
                </div>
              ) : null }
              { (values.status === 'DONE' && values.type === 'DROPOFF') && (
                <div className="text-center">
                  <a href={ window.Routing.generate('admin_task_receipt', { id: values.id }) } target="_blank" rel="noopener noreferrer">
                    <i className="fa fa-file-pdf-o"></i> { this.props.t('ADMIN_DASHBOARD_TASK_DOWNLOAD_PDF') }
                  </a>
                </div>
              )}
            </div>
            { this.renderFooter(values) }
          </form>
        )}
      </Formik>
    )
  }

  render() {

    const { complete } = this.state

    if (complete) {
      return this.renderCompleteForm()
    }

    return this.renderForm()
  }
}

function mapStateToProps (state) {

  return {
    task: selectCurrentTask(state),
    token: state.jwt,
    loading: state.isTaskModalLoading,
    tags: state.config.tags,
    completeTaskErrorMessage: state.completeTaskErrorMessage,
    date: selectSelectedDate(state),
    isTaskTypeEditable: isTaskTypeEditable(state.currentTask),
    isLoadingEvents: state.isLoadingTaskEvents,
    events: selectCurrentTaskEvents(state),
  }
}

function mapDispatchToProps(dispatch) {

  return {
    closeNewTaskModal: () => dispatch(closeNewTaskModal()),
    createTask: (task) => dispatch(createTask(task)),
    startTask: (task) => dispatch(startTask(task)),
    completeTask: (task, notes, success) => dispatch(completeTask(task, notes, success)),
    cancelTask: (task) => dispatch(cancelTask(task)),
    duplicateTask: (task) => dispatch(duplicateTask(task)),
    loadTaskEvents: (task) => dispatch(loadTaskEvents(task)),
    restoreTask: (task) => dispatch(restoreTask(task)),
    openTaskRescheduleModal: () => dispatch(openTaskRescheduleModal()),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(TaskModalContent))
