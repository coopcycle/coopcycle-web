import React, { useState } from 'react'
import _ from 'lodash'

function getNameFromId(formatId, formats) {
  const format = _.find(formats, f => f.id === formatId)
  return format.title
}

function getPriceFromId(formatId, formats) {
  const format = _.find(formats, f => f.id === formatId)
  return format.cost_cents
}

export default function({ customerContainers, formats, formatsToDeliver, initialReturns, closeModal, onChange }) {

  const [ returns, setReturns ] = useState(initialReturns)

  return (
    <div className="p-4">
      <section>
        <h5>Je rends mes boîtes</h5>
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

                    console.log(newReturns)

                    setReturns(newReturns)
                    onChange(newReturns)

                  } }>{ _.find(returns, r => r.format_id === container.format_id) ? 'Annuler' : 'Rendre la boîte' }</button>
                </td>
              </tr>
            )
          }) }
          </tbody>
        </table>
      </section>
      <section>
        <h5>Votre commande comporte les boîtes consignées suivantes</h5>
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
      <button type="button" className="btn btn-lg btn-block" onClick={ closeModal }>Valider et retourner au panier</button>
    </div>
  )
}
