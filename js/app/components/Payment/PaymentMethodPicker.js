import { useTranslation } from 'react-i18next'
import React, { useEffect, useState } from 'react'
import _ from 'lodash'
import clsx from 'clsx'
import PaymentMethodIcon from './PaymentMethodIcon'

const Wrapper = ({ children, ...props }) => {

  return (
    <div className="flex flex-col gap-2" {...props}>
      {children}
    </div>
  )
}

const Button = ({ method, isSelected, onClick }) => {

  return (
    <button
      type="button"
      className={clsx('btn btn-lg', isSelected && 'btn-active')}
      onClick={ onClick }>
      <PaymentMethodIcon code={ method } size="md" />
    </button>
  )
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
    <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between md:gap-2">
      { _.map(methods, m => {

        switch (m.type) {

          case 'card':
            return (
              <Wrapper key={ m.type }>
                <label>{t('PM_CREDIT_OR_DEBIT_CARD')}</label>
                <Button method={m.type} isSelected={method === m.type} onClick={ () => setMethod('card') } />
              </Wrapper>
            )

          case 'edenred':

            return (
              <Wrapper key={ m.type }>
                <label>{t('PM_EDENRED')}</label>
                <Button method={m.type} isSelected={method === m.type} onClick={ () => {
                  if (!m.data.edenredIsConnected) {
                    window.location.href = m.data.edenredAuthorizeUrl
                    return
                  }
                  setMethod(m.type)
                }} />
              </Wrapper>
            )

          case 'cash_on_delivery':

            return (
              <Wrapper key={ m.type } data-testid="pm.cash">
                <label>{ t('PM_CASH') }</label>
                <Button method={m.type} isSelected={method === m.type} onClick={ () => setMethod('cash_on_delivery') } />
              </Wrapper>
            )

          case 'restoflash':

            return (
              <Wrapper key={ m.type } data-testid="pm.restoflash">
                <label>{ t('PM_RESTOFLASH') }</label>
                <Button method={m.type} isSelected={method === m.type} onClick={ () => setMethod('restoflash') } />
              </Wrapper>
            )

          case 'conecs':

            return (
              <Wrapper key={ m.type } data-testid="pm.conecs">
                <label>{ t('PM_CONECS') }</label>
                <Button method={m.type} isSelected={method === m.type} onClick={ () => setMethod('conecs') } />
              </Wrapper>
            )

          case 'swile':

            return (
              <Wrapper key={ m.type } data-testid="pm.swile">
                <label>{ t('PM_SWILE') }</label>
                <Button method={m.type} isSelected={method === m.type} onClick={ () => setMethod('swile') } />
              </Wrapper>
            )

        }
      }) }
    </div>
  )
}
