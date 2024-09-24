import { useTranslation } from 'react-i18next'
import React, { useEffect, useState } from 'react'
import _ from 'lodash'
import classNames from 'classnames'
import PaymentMethodIcon from './PaymentMethodIcon'

const methodPickerStyles = {
  display: 'flex',
  flexDirection: 'row',
  alignItems: 'center',
  justifyContent: 'space-between',
  marginTop: '8px'
}

const methodPickerBtnClassNames = {
  'btn': true,
  'btn-default': true,
  'p-2': true
}

const methodStyles = {
  display: 'flex',
  flexDirection: 'column',
  alignItems: 'start',
  justifyContent: 'space-between',
}

export default function PaymentMethodPicker({ methods, onSelect }) {

  const { t } = useTranslation()

  const [ method, setMethod ] = useState('')

  useEffect(() => {
    if (method) {
      onSelect(method)
    }
  }, [ method ])

  return (
    <div style={ methodPickerStyles }>
      { _.map(methods, m => {

        switch (m.type) {

          case 'card':
            return (
              <div style={ methodStyles } key={ m.type }>
                <label>{ t('PM_CREDIT_OR_DEBIT_CARD') }</label>
                <button key={ m.type } type="button" className={ classNames({ ...methodPickerBtnClassNames, active: method === 'card' }) }
                        onClick={ () => setMethod('card') }>
                  <PaymentMethodIcon code={ m.type } height="45" />
                </button>
              </div>
            )

          case 'edenred':

            return (
              <div style={ methodStyles }key={ m.type }>
                <label>{ t('PM_EDENRED') }</label>
                <button key={ m.type } type="button" className={ classNames({ ...methodPickerBtnClassNames, active: method === m.type }) }
                        onClick={ () => {

                          if (!m.data.edenredIsConnected) {
                            window.location.href = m.data.edenredAuthorizeUrl
                            return
                          }

                          setMethod(m.type)
                        }}>
                  <PaymentMethodIcon code={ m.type } height="45" />
                </button>
              </div>
            )

          case 'cash_on_delivery':

            return (
              <div style={ methodStyles }key={ m.type } data-testid="pm.cash">
                <label>{ t('PM_CASH') }</label>
                <button key={ m.type } type="button" className={ classNames({ ...methodPickerBtnClassNames, active: method === m.type }) }
                        onClick={ () => setMethod('cash_on_delivery') }>
                  <PaymentMethodIcon code={ m.type } height="45" />
                </button>
              </div>
            )

        }
      }) }
    </div>
  )
}
