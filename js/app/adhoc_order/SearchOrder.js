import { Formik } from "formik";
import React, { Component } from "react";
import { withTranslation } from "react-i18next";
import { connect } from "react-redux";
import { clearAdhocOrder, searchAdhocOrder } from "./redux/actions";

class SearchOrder extends Component {

  constructor(props) {
    super(props)

    this._validate = this._validate.bind(this)
    this._onSubmit = this._onSubmit.bind(this)
  }

  _validate(values) {
    let errors = {}

    if (!values.orderNumber) {
      errors.orderNumber = this.props.t('SEARCH_ORDER_NUMBER_ERROR')
    }

    return errors;
  }

  _onSubmit(values) {
    return this.props.searchOrder(values.orderNumber)
      .then(() => {
        if (this.props.order) {
          this.props.onOrderLoaded()
        }
      })
  }

  _loadErrorMessage() {
    if (!this.props.searchError) {
      return
    }

    if (this.props.searchError.message.indexOf('404') !== -1) {
      return (
        <div className="has-error px-4">
          <small className="help-block">No se encontró ninguna ordern con ese número</small>
        </div>
      )
    }

    return (
      <div className="has-error px-4">
        <small className="help-block">Hubo un error al buscar la orden</small>
      </div>
    )
  }

  _onCreateNewOrderPressed() {
    this.props.clearOrder()
    this.props.onCreateNewOrderPressed()
  }

  render() {
    const initialValues = {
      orderNumber: ''
    }

    return (
      <div>
        <Formik
          initialValues={ initialValues }
          validate={ this._validate }
          onSubmit={ this._onSubmit }
          validateOnBlur={ false }
          validateOnChange={ false }>
          {({
            values,
            errors,
            touched,
            handleSubmit,
            handleChange,
            handleBlur,
          }) => (
            <form onSubmit={ handleSubmit } autoComplete="off" className="form">
              <h4 className="title">{ this.props.t('CONTINUE_EXISTING_ORDER') }</h4>

              <div className="row">
                <div className={ `form-group input-group col-md-4 px-4 ${errors.orderNumber ? 'has-error': ''}` }>
                  <input type="text" name="orderNumber" className="form-control" autoComplete="off"
                    placeholder={ this.props.t('ORDER_NUMBER') }
                    onChange={ handleChange }
                    onBlur={ handleBlur }
                    value={ values.orderNumber } />
                  <div className="input-group-btn">
                    <button className="btn btn-outline-secondary" type="submit" disabled={this.props.isFetching}>
                      { this.props.t('ADMIN_DASHBOARD_SEARCH') }
                    </button>
                  </div>
                </div>
                { errors.orderNumber && touched.orderNumber && (
                  <div className="has-error px-4">
                    <small className="help-block">{ errors.orderNumber }</small>
                  </div>
                ) }
                { this._loadErrorMessage() }
              </div>
            </form>
          )}

        </Formik>

        <hr />

        <button className="btn btn-md btn-info" type="button"
          disabled={this.props.isFetching}
          onClick={() => this._onCreateNewOrderPressed()}>
          { this.props.t('CREATE_NEW_ORDER') }
        </button>
      </div>
    )
  }
}

function mapStateToProps(state) {
  return {
    isFetching: state.isFetching,
    order: state.order,
    searchError: state.error,
  }
}

function mapDispatchToProps(dispatch) {
  return {
    searchOrder: (orderNumber) => dispatch(searchAdhocOrder(orderNumber)),
    clearOrder: () => dispatch(clearAdhocOrder()),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)( withTranslation()(SearchOrder))
