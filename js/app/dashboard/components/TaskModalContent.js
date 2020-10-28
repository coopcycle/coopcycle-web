import React from 'react'
import _ from 'lodash'
import moment from 'moment'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import { ConfigProvider, DatePicker, Form, Radio, Timeline } from 'antd'
import { Formik } from 'formik'
import PhoneInput, { isValidPhoneNumber } from 'react-phone-number-input'
import phoneNumberExamples from 'libphonenumber-js/examples.mobile.json'
import { getExampleNumber } from 'libphonenumber-js'

import { antdLocale, getCountry } from '../../i18n'
import AddressAutosuggest from '../../components/AddressAutosuggest'
import TagsSelect from '../../components/TagsSelect'
import CourierSelect from './CourierSelect'
import { timePickerProps } from '../../utils/antd'

import { closeNewTaskModal, createTask, startTask, completeTask, cancelTask, duplicateTask, loadTaskEvents } from '../redux/actions'
import { selectSelectedDate } from '../../coopcycle-frontend-js/lastmile/redux'

const itemColor = event => {
  switch (event.name) {
  case 'task:done':
    return 'green'
  case 'task:failed':
  case 'task:cancelled':
    return 'red'
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
      complete: false
    }
    this.success = true
  }

  renderHeaderText(task) {
    if (!!task && Object.prototype.hasOwnProperty.call(task, '@id')) {

      return (
        <span>
          { (task.orgName && !_.isEmpty(task.orgName)) && (
          <span>
            <span>{ task.orgName }</span>
            <span className="mx-2">›</span>
          </span>
          ) }
          <span>{ this.props.t('ADMIN_DASHBOARD_TASK_TITLE', { id: task.id }) }</span>
        </span>
      )
    }

    return (
      <span>{ this.props.t('ADMIN_DASHBOARD_TASK_TITLE_NEW') }</span>
    )
  }

  renderCompleteForm() {
    let initialValues = {
      notes: '',
    }

    return (
      <Formik
        ref={ ref => this.completeForm = ref }
        initialValues={ initialValues }
        onSubmit={(values) => {
          // TODO Use setSubmitting
          this.props.completeTask(this.props.task, values.notes, this.success)
        }}
      >
        {({
          values,
          handleChange,
          handleBlur,
          handleSubmit,
        }) => (
          <form name="task_complete" onSubmit={ handleSubmit }>
            { this.renderHeader(values) }
            <div className="modal-body">
              <div className={ this.props.completeTaskErrorMessage ? 'form-group form-group-sm has-error' : 'form-group form-group-sm' }>
                <label className="control-label required">{ this.props.t('ADMIN_DASHBOARD_COMPLETE_FORM_COMMENTS_LABEL') }</label>
                <textarea name="notes" rows="2"
                  placeholder={ this.props.t('ADMIN_DASHBOARD_COMPLETE_FORM_COMMENTS_PLACEHOLDER') }
                  className="form-control"
                  onChange={ handleChange }
                  onBlur={ handleBlur }
                  value={ values.notes }></textarea>
                { this.props.completeTaskErrorMessage && (
                  <span className="help-block">{ this.props.completeTaskErrorMessage }</span>
                )}
              </div>
            </div>
            <div className="modal-footer">
              <button type="button" className="btn btn-transparent pull-left" disabled={ this.props.loading } onClick={ e => {
                this.success = false
                e.persist()
                handleSubmit(e)
              } }>
                { this.props.loading && (
                  <span><i className="fa fa-spinner fa-spin"></i> </span>
                )}
                <span className="text-danger">{ this.props.t('ADMIN_DASHBOARD_COMPLETE_FORM_FAILURE') }</span>
              </button>
              <button type="submit" className="btn btn-success" disabled={ this.props.loading }>
                { this.props.loading && (
                  <span><i className="fa fa-spinner fa-spin"></i> </span>
                )}
                <span>{ this.props.t('ADMIN_DASHBOARD_COMPLETE_FORM_SUCCESS') }</span>
              </button>
            </div>
          </form>
        )}
      </Formik>
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

  renderHeader(task) {

    return (
      <div className="modal-header">
        <h4 className="modal-title">
          <span>{ this.renderHeaderText(task) }</span>
          <a href="#" className="pull-right" onClick={ this.onCloseClick.bind(this) }>
            <i className="fa fa-times" aria-hidden="true"></i>
          </a>
        </h4>
      </div>
    )
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
        { (!!task && Object.prototype.hasOwnProperty.call(task, '@id')) && (
          <button type="button" className="btn pull-left"
            onClick={ () => this.props.duplicateTask(task) }
            disabled={ this.props.loading }>
            <span className="text-success">{ this.props.t('ADMIN_DASHBOARD_DUPLICATE_TASK') }</span>
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
            { event.data.notes && (
              <p>{ event.data.notes }</p>
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
      assignedTo: null
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
          <ConfigProvider locale={ antdLocale }>
            <form name="task" onSubmit={ handleSubmit } autoComplete="off">
              { this.renderHeader(values) }
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
                    addresses={ [] }
                    geohash={ '' }
                    onAddressSelected={ (value, address) => {
                      const cleanAddress =
                        _.omit(address, ['isPrecise', 'latitude', 'longitude', 'addressRegion', 'geohash'])

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
                    <PhoneInput
                      value={ values.address.telephone ? values.address.telephone : '' }
                      country={ this.props.country }
                      showCountrySelect={ false }
                      displayInitialValueAsLocalNumber={ true }
                      inputClassName="form-control"
                      autoComplete="off"
                      onChange={ value => {
                        setFieldValue('address.telephone', value)
                        setFieldTouched('address.telephone')
                      }} />
                    { errors.address && touched.address && errors.address.telephone && touched.address.telephone && (
                      <small className="help-block">{ errors.address.telephone }</small>
                    ) }
                    <small className="help-block">
                      { this.props.t('ADMIN_DASHBOARD_TASK_FORM_ADDRESS_TELEPHONE_HELP', { example: this.props.phoneNumberExample }) }
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
                    <Form.Item>
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
                    </Form.Item>
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
          </ConfigProvider>
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

  const country = (getCountry() || 'fr').toUpperCase()
  const phoneNumber = getExampleNumber(country, phoneNumberExamples)

  const events = state.currentTask && Object.prototype.hasOwnProperty.call(state.taskEvents, state.currentTask['@id']) ? state.taskEvents[state.currentTask['@id']] : []

  return {
    task: state.currentTask,
    token: state.jwt,
    loading: state.isTaskModalLoading,
    tags: state.tags,
    completeTaskErrorMessage: state.completeTaskErrorMessage,
    country,
    phoneNumberExample: phoneNumber.formatNational(),
    date: selectSelectedDate(state),
    isTaskTypeEditable: isTaskTypeEditable(state.currentTask),
    isLoadingEvents: state.isLoadingTaskEvents,
    events,
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
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(TaskModalContent))
