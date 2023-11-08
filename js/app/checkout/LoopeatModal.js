import React, { useState } from 'react'
import _ from 'lodash'
import { useTranslation } from 'react-i18next'
import classNames from 'classnames'
import { Checkbox } from 'antd'

function getNameFromId(formatId, formats) {
  const format = formats.find(f => f.id === formatId)
  return format.title
}

function getPriceFromId(formatId, formats) {
  const format = _.find(formats, f => f.id === formatId)
  return format.cost_cents
}

const getReturnsTotalAmount = (returns, formats) => returns.reduce(
  (total, container) => total + (getPriceFromId(container.format_id, formats) * container.quantity),
  0
)

const LoopeatModal = function({ customerContainers, formats, formatsToDeliver, initialReturns, creditsCountCents, requiredAmount, containersCount, oauthUrl, onChange, onSubmit }) {

  const { t } = useTranslation()
  const [ returns, setReturns ] = useState(initialReturns)

  const returnsTotalAmount = getReturnsTotalAmount(returns, formats)
  const missing = requiredAmount - (creditsCountCents + returnsTotalAmount)

  return (
    <div className="p-4">
      <h4>{ t('CART_LOOPEAT_MODAL_RETURN_SECTION_TITLE', { count: containersCount }) }</h4>
      <p className="text-muted">{ t('CART_LOOPEAT_MODAL_RETURN_SECTION_SUBTITLE') }</p>
      <section>
        <table className="table">
          <thead>
            <tr>
              <th></th>
              <th>{ t('CART_LOOPEAT_RETURNS_TYPE') }</th>
              <th className="text-right">{ t('CART_LOOPEAT_RETURNS_QUANTITY') }</th>
            </tr>
          </thead>
          <tbody>
          { customerContainers.map((container, index) => {

            const isSelected = !!_.find(returns, r => r.format_id === container.format_id)

            return (
              <tr key={ `container-${index}` } className={ classNames({
                'active': isSelected
              }) }>
                <td style={{ width: '1px', whiteSpace: 'nowrap' }}>
                  <Checkbox checked={ isSelected } onChange={ e => {
                    const newReturns = e.target.checked ?
                      [ ...returns, container ] : _.filter(returns, r => r.format_id !== container.format_id)
                    setReturns(newReturns)
                    onChange(newReturns)
                  }} />
                </td>
                <td>{ `${getNameFromId(container.format_id, formats)} (${(getPriceFromId(container.format_id, formats) / 100).formatMoney()})` }</td>
                <td style={{ width: '1px', whiteSpace: 'nowrap' }}>
                  <input type="number" className="form-control"
                    disabled={ !isSelected }
                    style={{ width: '5em' }}
                    defaultValue={ container.quantity }
                    min={ 1 } max={ container.quantity }
                    onChange={ e => {
                      const idx = _.findIndex(returns, r => r.format_id === container.format_id)
                      if (idx !== -1) {
                        const newReturns = returns.map(function(ret, retIndex) {
                          return retIndex === idx ? { ...ret, quantity: parseInt(e.target.value, 10) } : ret;
                        });
                        setReturns(newReturns)
                        onChange(newReturns)
                      }
                    }} />
                </td>
              </tr>
            )
          }) }
          </tbody>
        </table>
      </section>
      <h4>{ t('CART_LOOPEAT_MODAL_WALLET_SECTION_TITLE', { count: (creditsCountCents / 100).formatMoney() }) }</h4>
      <section>
        <a href={ oauthUrl} className="btn btn-default btn-lg btn-block">{ t('CART_LOOPEAT_MODAL_ADD_CREDITS') }</a>
      </section>
      <hr />
      <section>
        <h5>{ t('CART_LOOPEAT_RETURNS_SUMMARY') }</h5>
        <table className="table table-condensed">
          <tbody>
          { formatsToDeliver.map((container, index) => {

            return (
              <tr key={ `deliver-${index}` }>
                <td>{ `${container.quantity} × ${getNameFromId(container.format_id, formats)}` }</td>
                <td className="text-right">{ ((getPriceFromId(container.format_id, formats) * container.quantity) / 100).formatMoney() }</td>
              </tr>
            )
          }) }
          </tbody>
          <tfoot>
            <tr>
              <th>{ t('CART_LOOPEAT_MODAL_TOTAL_DEPOSIT') }</th>
              <td className="text-right">{ (requiredAmount / 100).formatMoney() }</td>
            </tr>
            <tr>
              <th>{ t('CART_LOOPEAT_RETURNS_CREDITS_COUNT') }</th>
              <td className="text-right">{ (creditsCountCents / 100).formatMoney() }</td>
            </tr>
            <tr>
              <th>{ t('CART_LOOPEAT_MODAL_RETURNS_AMOUNT') }</th>
              <td className="text-right">{ (returnsTotalAmount / 100).formatMoney() }</td>
            </tr>
          </tfoot>
        </table>
        <p className={ classNames({
          'text-center': true,
          'text-success': missing <= 0,
          'text-danger': missing > 0,
        }) }>
          { missing <= 0 && t('CART_LOOPEAT_MODAL_OK') }
          { missing > 0 && t('CART_LOOPEAT_MODAL_NOK') }
        </p>
      </section>
      <button type="button" className="btn btn-lg btn-block btn-primary" onClick={ onSubmit }>
        { t('CART_LOOPEAT_RETURNS_VALIDATE') }
      </button>
    </div>
  )
}

export default LoopeatModal
