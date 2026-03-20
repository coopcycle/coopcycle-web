import React from 'react'

import mastercard from 'payment-icons/min/flat/mastercard.svg'
import visa from 'payment-icons/min/flat/visa.svg'
import edenredLogo from '../../../../assets/svg/Edenred_Logo.svg'
import cashLogo from '../../../../assets/svg/dollar-bill-svgrepo-com.svg'
import restoflashLogo from './restoflash.svg'
import conecsLogo from './conecs.svg'
import swileLogo from './Swile_black.png'

export default ({ code, height }) => {
  const heightPx = typeof height === 'number' || (typeof height === 'string' && !height.endsWith('px'))
    ? `${height}px`
    : height

  switch (code.toLowerCase()) {

    case 'card':
      return (
        <span className="d-flex gap-1">
          <img src={ visa } style={{ height: heightPx }} className="mr-2" />
          <img src={ mastercard } style={{ height: heightPx }} />
        </span>
      )

    case 'edenred':
      return (
        <img src={ edenredLogo } style={{ height: heightPx }} />
      )

    case 'cash_on_delivery':
      return (
        <img src={ cashLogo } style={{ height: heightPx }} />
      )

    case 'restoflash':
      return (
        <img src={ restoflashLogo } style={{ height: heightPx, maxWidth: '80px' }} />
      )

    case 'conecs':
      return (
        <img src={ conecsLogo } style={{ height: heightPx }} />
      )

    case 'swile':
      return (
        <img src={ swileLogo } style={{ height: heightPx, maxWidth: '60px', objectFit: 'contain' }} />
      )
  }

  return null
}
