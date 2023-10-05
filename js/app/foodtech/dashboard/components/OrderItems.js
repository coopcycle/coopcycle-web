import React from 'react'
import _ from 'lodash'
import { withTranslation } from 'react-i18next'
import classNames from 'classnames'

const isSameVendor = (restaurant, items) => {

  const ids = _.uniq(items.map(item => item.vendor['@id']))

  return ids.length === 1 && ids[0] === restaurant['@id']
}

const Adjustments = ({ item, type }) => {

  if (!Object.prototype.hasOwnProperty.call(item.adjustments, type)) {

    return null
  }

  if (item.adjustments[type].length === 0) {

    return null
  }

  return (
    <ul className="list-unstyled">
      { item.adjustments[type].map((adjustment) =>
        <li key={ `adjustment-${adjustment.id}` }>
          <small className="text-muted">{ adjustment.label }</small>
        </li>
      ) }
    </ul>
  )
}

class OrderItems extends React.Component {

  renderItems(items) {

    return (
      <table className="table table-condensed nomargin">
        <tbody>
          { items.map((item, key) =>
            <tr key={ key }>
              <td className={ classNames({
                'text-blur': this.props.restaurant ? !isSameVendor(this.props.restaurant, [ item ]) : false
              }) }>
                <span className="d-block">{ item.quantity } x { item.name }</span>
                <Adjustments item={ item } type="menu_item_modifier" />
                <Adjustments item={ item } type="reusable_packaging" />
              </td>
              <td className="text-right">{ (item.total / 100).formatMoney() }</td>
            </tr>
          ) }
        </tbody>
      </table>
    )
  }

  render() {

    if (_.size(this.props.itemsGroups) === 1) {

      const key = _.first(_.keys(this.props.itemsGroups))

      return this.renderItems(this.props.itemsGroups[key])
    }

    return (
      <div>
        { _.map(this.props.itemsGroups, (items, title) => {
          return (
            <React.Fragment key={ title }>
              <h5 className={ classNames({
                'text-muted': true,
                'text-blur': this.props.restaurant ? !isSameVendor(this.props.restaurant, items) : false
              }) }>{ title }</h5>
              { this.renderItems(items) }
            </React.Fragment>
          )
        })}
      </div>
    )
  }

}

export default withTranslation()(OrderItems)
