import React, { useState } from 'react'
import _ from 'lodash'
import { withTranslation } from 'react-i18next'

function getNameFromId(formatId, formats) {
  const format = _.find(formats, f => f.id === formatId)
  return format.title
}

function getPriceFromId(formatId, formats) {
  const format = _.find(formats, f => f.id === formatId)
  return format.cost_cents
}

const LoopeatReturns = function({ t, customerContainers, formats, formatsToDeliver, initialReturns, closeModal, onChange }) {

  const [ returns, setReturns ] = useState(initialReturns)

  return (
    <div className="p-4">
      <section>
        <h5>{ t('CART_LOOPEAT_RETURNS_WIDGET') }</h5>
        <table className="table">
          <tbody>
          { customerContainers.map((container, index) => {

            return (
              <tr key={ `container-${index}` }>
                <td>{ `${container.quantity} × ${getNameFromId(container.format_id, formats)}` }</td>
                <td className="text-right">
                  <button type="button" className="btn btn-md" onClick={ () => {

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
        <table className="table">
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
        </table>
      </section>
      <button type="button" className="btn btn-lg btn-block" onClick={ closeModal }>
        { t('CART_LOOPEAT_RETURNS_VALIDATE') }
      </button>
    </div>
  )
}

export default withTranslation()(LoopeatReturns)
