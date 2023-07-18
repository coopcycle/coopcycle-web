import React, { useState } from 'react'
import _ from 'lodash'
import { useTranslation } from 'react-i18next'
import classNames from 'classnames'

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

const LoopeatReturns = function({ customerContainers, formats, formatsToDeliver, initialReturns, closeModal, creditsCountCents, requiredAmount, onChange }) {

  const { t } = useTranslation()
  const [ returns, setReturns ] = useState(initialReturns)

  const returnsTotalAmount = getReturnsTotalAmount(returns, formats)
  const missing = requiredAmount - (creditsCountCents + returnsTotalAmount)

  return (
    <div className="p-4">
      <section>
        <h5>{ t('CART_LOOPEAT_RETURNS_WIDGET') }</h5>
        <table className="table">
          <tbody>
          { customerContainers.map((container, index) => {

            return (
              <tr key={ `container-${index}` }>
                <td>{ `${container.quantity} × ${getNameFromId(container.format_id, formats)} (${(getPriceFromId(container.format_id, formats) / 100).formatMoney()})` }</td>
                <td className="text-right">
                  <button type="button" className="btn btn-sm" onClick={ () => {

                    const newReturns = _.find(returns, r => r.format_id === container.format_id) ?
                      _.filter(returns, r => r.format_id !== container.format_id) : [ ...returns, container ]

                    setReturns(newReturns)
                    onChange(newReturns)

                  } }>{ _.find(returns, r => r.format_id === container.format_id) ? t('CART_LOOPEAT_RETURNS_TURN_OFF') : t('CART_LOOPEAT_RETURNS_TURN_ON') }</button>
                </td>
              </tr>
            )
          }) }
          </tbody>
        </table>
      </section>
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
              <th>{ t('CART_TOTAL') }</th>
              <td className="text-right">{ (requiredAmount / 100).formatMoney() }</td>
            </tr>
            <tr>
              <th>{ t('CART_LOOPEAT_RETURNS_CREDITS_COUNT') }</th>
              <td className="text-right">{ (creditsCountCents / 100).formatMoney() }</td>
            </tr>
            <tr>
              <th>{ t('CART_LOOPEAT_RETURNS_RETURNS_AMOUNT') }</th>
              <td className="text-right">{ (returnsTotalAmount / 100).formatMoney() }</td>
            </tr>
            <tr className={ classNames({
              'text-success': missing <= 0,
              'text-danger': missing > 0,
            }) }>
              <th>{ t('CART_LOOPEAT_RETURNS_RETURNS_DIFF') }</th>
              <td className="text-right">{ ((missing * -1) / 100).formatMoney() }</td>
            </tr>
          </tfoot>
        </table>
      </section>
      <button type="button" className="btn btn-lg btn-block" onClick={ closeModal }>
        { t('CART_LOOPEAT_RETURNS_VALIDATE') }
      </button>
    </div>
  )
}

export default LoopeatReturns
