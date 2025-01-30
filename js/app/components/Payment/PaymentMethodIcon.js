import React from 'react'

import mastercard from 'payment-icons/min/flat/mastercard.svg'
import visa from 'payment-icons/min/flat/visa.svg'
import edenredLogo from '../../../../assets/svg/Edenred_Logo.svg'
import cashLogo from '../../../../assets/svg/dollar-bill-svgrepo-com.svg'
import restoflashLogo from './restoflash.svg'
import conecsLogo from './conecs.svg'
import swileLogo from './Swile_black.png'

export default ({ code, height }) => {
  switch (code.toLowerCase()) {

    case 'card':
      return (
        <span>
          <img src={ visa } height={ height } className="mr-2" />
          <img src={ mastercard } height={ height } />
        </span>
      )

    case 'edenred':
      return (
        <img src={ edenredLogo } height={ height } />
      )

    case 'cash_on_delivery':
      return (
        <img src={ cashLogo } height={ height } />
      )

    case 'restoflash':
      return (
        <img src={ restoflashLogo } height={ height } style={{ maxWidth: '80px' }} />
      )

    case 'conecs':
      return (
        <img src={ conecsLogo } height={ height } />
      )

    case 'swile':
      return (
        <img src={ swileLogo } height={ height } style={{ maxWidth: '60px', objectFit: 'contain' }} />
      )
  }

  return null
}
