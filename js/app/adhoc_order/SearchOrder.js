import { Formik } from "formik";
import React, { Component } from "react";
import { withTranslation } from "react-i18next";

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

  _onSubmit() {}

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
                    <button className="btn btn-outline-secondary" type="submit">{ this.props.t('ADMIN_DASHBOARD_SEARCH') }</button>
                  </div>
                </div>
                { errors.orderNumber && touched.orderNumber && (
                  <div className="has-error px-4">
                    <small className="help-block">{ errors.orderNumber }</small>
                  </div>
                ) }
              </div>
            </form>
          )}

        </Formik>

        <hr />

        <button className="btn btn-md btn-info" type="button" onClick={this.props.onCreateNewOrderPressed}>
          { this.props.t('CREATE_NEW_ORDER') }
        </button>
      </div>
    )
  }
}

export default withTranslation()(SearchOrder)
