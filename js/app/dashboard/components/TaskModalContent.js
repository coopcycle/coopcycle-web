import React from 'react'
import _ from 'lodash'
import moment from 'moment'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import LocaleProvider from 'antd/lib/locale-provider'
import DatePicker from 'antd/lib/date-picker'
import Form from 'antd/lib/form'
import Radio from 'antd/lib/radio'
import Timeline from 'antd/lib/timeline'
import fr_FR from 'antd/lib/locale-provider/fr_FR'
import en_GB from 'antd/lib/locale-provider/en_GB'
import { Formik } from 'formik'
import Select from 'react-select'
import chroma from 'chroma-js'
import PhoneInput, { isValidPhoneNumber, formatPhoneNumber } from 'react-phone-number-input'

import AddressAutosuggest from '../../components/AddressAutosuggest'
import { openNewTaskModal, closeNewTaskModal, createTask, completeTask, cancelTask } from '../store/actions'
import CourierSelect from './CourierSelect'

const locale = $('html').attr('lang')
const antdLocale = locale === 'fr' ? fr_FR : en_GB

const tagAsOption = tag => ({
  ...tag,
  value: tag.id,
  label: tag.name
})

const lookupTags = (allTags, tags) => {

  let items = []

  _.forEach(tags, tag => {
    const match = _.find(allTags, t => t.slug === tag.slug)
    if (match) {
      items.push(match)
    }
  })

  return items
}

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

const styles = {
  option: (styles, { data, isDisabled, isFocused, isSelected }) => {
    const color = chroma(data.color)

    return {
      ...styles,
      backgroundColor: isDisabled
        ? null
        : isSelected ? data.color : isFocused ? color.alpha(0.1).css() : null,
      color: isDisabled
        ? '#ccc'
        : isSelected
          ? chroma.contrast(color, 'white') > 2 ? 'white' : 'black'
          : data.color,
      cursor: isDisabled ? 'not-allowed' : 'default',
    }
  },
  multiValue: (styles, { data }) => ({
    ...styles,
    backgroundColor: data.color,
  }),
  multiValueLabel: (styles, { data }) => {
    const color = chroma(data.color)

    return {
      ...styles,
      color: chroma.contrast(color, 'white') > 2 ? 'white' : 'black'
    }
  },
  multiValueRemove: (styles, { data }) => {
    const color = chroma(data.color)

    return {
      ...styles,
      color: chroma.contrast(color, 'white') > 2 ? 'white' : 'black',
      ':hover': {
        backgroundColor: color.alpha(0.1).css(),
        color: chroma.contrast(color, 'white') > 2 ? 'white' : 'black',
      }
    }
  },
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
    if (!!task && task.hasOwnProperty('@id')) {
      return this.props.t('ADMIN_DASHBOARD_TASK_TITLE', { id: task.id })
    }

    return this.props.t('ADMIN_DASHBOARD_TASK_TITLE_NEW')
  }

  renderCompleteForm() {
    let initialValues = {
      notes: '',
    }

    return (
      <Formik
        ref={ ref => this.completeForm = ref }
        initialValues={ initialValues }
        onSubmit={(values, { setSubmitting }) => {
          this.props.completeTask(this.props.task, values.notes, this.success)
        }}
      >
        {({
          values,
          errors,
          touched,
          handleChange,
          handleBlur,
          handleSubmit,
          isSubmitting,
          onSubmit,
          submitForm,
          setFieldValue,
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

  onCompleteClick(e) {
    e.preventDefault()
    this.setState({ complete: true })
  }

  onCloseClick(e) {
    e.preventDefault()
    this.props.closeNewTaskModal()
  }

  _validate(values) {
    let errors = {}

    if (!values.address.hasOwnProperty('geo')) {
      errors.address = {
        ...errors.address,
        streetAddress: this.props.t('ADMIN_DASHBOARD_TASK_FORM_ADDRESS_ERROR')
      }
    }

    if (values.address.hasOwnProperty('telephone')) {
      if (!isValidPhoneNumber(values.address.telephone)) {
        errors.address = {
          ...errors.address,
          telephone: this.props.t('ADMIN_DASHBOARD_TASK_FORM_TELEPHONE_ERROR')
        }
      }
    }

    return errors
  }

  _onSubmit(values, { setSubmitting }) {

    const { task } = this.props

    if (!!task && task.hasOwnProperty('@id') && task.address.hasOwnProperty('@id')) {
      values = {
        ...values,
        address: {
          ...values.address,
          '@id': task.address['@id']
        }
      }
    }

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
          <button type="button" className="btn pull-left" onClick={ e => this.props.cancelTask(task) }>
            <span className="text-danger">{ this.props.t('ADMIN_DASHBOARD_CANCEL_TASK') }</span>
          </button>
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

  renderTimeline(task) {

    const events = task.events.slice(0)

    events.sort((a, b) => {
      return moment(a.createdAt).isBefore(moment(b.createdAt)) ? -1 : 1
    })

    return (
      <div>
        <div className="text-center">
          <a className="help-block" role="button" data-toggle="collapse" href="#task_history" aria-expanded="false">
            <small>{ this.props.t('ADMIN_DASHBOARD_TASK_FORM_SHOW_HISTORY') }</small>
          </a>
        </div>
        <div className="collapse" id="task_history" aria-expanded="false">
          <Timeline>
            { events.map(event => (
              <Timeline.Item key={ event.createdAt + '-' + event.name } color={ itemColor(event) }>
                <p>{ moment(event.createdAt).format('LT') } { event.name }</p>
                { event.notes && (
                  <p>{ event.notes }</p>
                ) }
              </Timeline.Item>
            )) }
          </Timeline>
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
      after: moment().add(1, 'hours').format(),
      before: moment().add(2, 'hours').format(),
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
      if (initialValues.address.telephone) {
        initialValues.address.telephone = formatPhoneNumber(initialValues.address.telephone)
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
          isSubmitting,
          isValidating,
          setFieldValue,
          setFieldTouched,
          /* and other goodies */
        }) => (
          <LocaleProvider locale={ antdLocale }>
            <form name="task" onSubmit={ handleSubmit } autoComplete="off">
              { this.renderHeader(values) }
              <div className="modal-body">
                <div className="form-group text-center">
                  <Radio.Group name="type" defaultValue={ values.type } onChange={ (e) => setFieldValue('type', e.target.value) } size="large"
                    disabled={ values.hasOwnProperty('@id') }>
                    <Radio.Button value="PICKUP">Pickup</Radio.Button>
                    <Radio.Button value="DROPOFF">Dropoff</Radio.Button>
                  </Radio.Group>
                </div>
                { values.hasOwnProperty('@id') && this.renderTimeline(values) }
                <div className={ errors.address && touched.address && errors.address.streetAddress && touched.address.streetAddress ? 'form-group form-group-sm has-error' : 'form-group form-group-sm' }>
                  <label className="control-label required">{ this.props.t('ADMIN_DASHBOARD_TASK_FORM_ADDRESS_STREET_ADDRESS_LABEL') }</label>
                  <AddressAutosuggest
                    autofocus={ !values.hasOwnProperty('@id') }
                    address={ values.address.streetAddress }
                    addresses={ [] }
                    geohash={ '' }
                    onAddressSelected={ (value, address, type) => {
                      const cleanAddress =
                        _.omit(address, ['isPrecise', 'latitude', 'longitude', 'addressRegion', 'geohash'])

                      address = {
                        ...values.address,
                        ...cleanAddress
                      }

                      setFieldValue('address', address)
                    } } />
                  { errors.address && touched.address && errors.address.streetAddress && touched.address.streetAddress && (
                    <span className="help-block">{ errors.address.streetAddress }</span>
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
                  <div className={ errors.address && touched.address && errors.address.telephone && touched.address.telephone ? 'form-group form-group-sm has-error' : 'form-group form-group-sm' }>
                    <label className="control-label" htmlFor="address_telephone">{ this.props.t('ADMIN_DASHBOARD_TASK_FORM_ADDRESS_TELEPHONE_LABEL') }</label>
                    <PhoneInput
                      value={ values.address.telephone || '' }
                      country={ this.props.country }
                      showCountrySelect={ false }
                      inputClassName="form-control"
                      autoComplete="off"
                      onChange={ value => {
                        setFieldValue('address.telephone', value)
                        setFieldTouched('address.telephone')
                      }} />
                    { errors.address && touched.address && errors.address.telephone && touched.address.telephone && (
                      <span className="help-block">{ errors.address.telephone }</span>
                    ) }
                  </div>
                  <div className="form-group form-group-sm">
                    <label className="control-label" htmlFor="address_floor">{ this.props.t('ADMIN_DASHBOARD_TASK_FORM_ADDRESS_FLOOR_LABEL') }</label>
                    <input type="text" id="address_floor" name="address.floor" className="form-control"
                      autoComplete="off"
                      onChange={ handleChange }
                      onBlur={ handleBlur }
                      value={ values.address.floor || '' } />
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
                        showTime={{ hideDisabledOptions: true, format: 'HH:mm' }}
                        format="YYYY-MM-DD HH:mm"
                        defaultValue={[ moment(values.after), moment(values.before) ]}
                        onChange={(value, dateString) => {
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
                  <Select
                    defaultValue={ lookupTags(this.props.tags, values.tags) }
                    isMulti
                    options={ this.props.tags }
                    onChange={ tags => {
                      setFieldValue('tags', tags)
                    }}
                    styles={ styles }
                    getOptionValue={ tag => tag.slug } />
                </div>
                <div className="form-group form-group-sm">
                  <label className="control-label">{ this.props.t('ADMIN_DASHBOARD_COURIER') }</label>
                  <CourierSelect
                    username={ values.assignedTo }
                    onChange={ courier => {
                      setFieldValue('assignedTo', courier.username)
                    }}
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
                { (values.status === 'TODO' && values.isAssigned) && (
                  <div className="text-center">
                    <a href="#" onClick={ this.onCompleteClick.bind(this) }>Terminer</a>
                  </div>
                )}
              </div>
              { this.renderFooter(values) }
            </form>
          </LocaleProvider>
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
    task: state.currentTask,
    token: state.jwt,
    loading: state.isTaskModalLoading,
    tags: _.map(state.tags, tagAsOption),
    completeTaskErrorMessage: state.completeTaskErrorMessage,
    country: (window.AppData.countryIso || 'fr').toUpperCase()
  }
}

function mapDispatchToProps(dispatch) {

  return {
    closeNewTaskModal: _ => dispatch(closeNewTaskModal()),
    createTask: (task) => dispatch(createTask(task)),
    completeTask: (task, notes, success) => dispatch(completeTask(task, notes, success)),
    cancelTask: (task) => dispatch(cancelTask(task))
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(TaskModalContent))
