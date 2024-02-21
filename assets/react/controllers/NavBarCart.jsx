import React from 'react'
import CartTop from '../../../js/app/cart/CartTop'

export default function NavBarCart(props) {
  return (<CartTop url={ props.url } href={ props.href } />)
}
