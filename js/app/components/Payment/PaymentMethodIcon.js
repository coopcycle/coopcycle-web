import React from 'react'

import mastercard from 'payment-icons/min/flat/mastercard.svg'
import visa from 'payment-icons/min/flat/visa.svg'
import edenredLogo from '../../../../assets/svg/Edenred_Logo.svg'
import cashLogo from '../../../../assets/svg/dollar-bill-svgrepo-com.svg'
import restoflashLogo from './restoflash.svg'
import conecsLogo from './conecs.svg'
import swileLogo from './Swile_black.png'

const sizeToStyle = {
  md: {
    height: '2.5em',
    maxWidth: '5em',
  },
  xs: {
    height: '1.25em',
    maxWidth: '2.5em',
  }
}

export default ({ code, size = "md" }) => {

  const props = { style: sizeToStyle[size] || sizeToStyle['md']  }

  switch (code.toLowerCase()) {

    case 'card':
      return (
        <span style={{ display: 'flex', gap: '0.5em' }}>
          <img src={visa} {...props} />
          <img src={ mastercard } {...props} />
        </span>
      )

    case 'edenred':
      return (
        <img src={ edenredLogo } {...props} />
      )

    case 'cash_on_delivery':
      return (
        <img src={ cashLogo } {...props} />
      )

    case 'restoflash':
      return (
        <img src={ restoflashLogo } {...props} />
      )

    case 'conecs':
      return (
        <img src={ conecsLogo } {...props} />
      )

    case 'swile':
      return (
        <img src={ swileLogo } style={{ ...props.style, objectFit: 'contain' }} />
      )
  }

  return null
}
