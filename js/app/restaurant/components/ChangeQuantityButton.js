import React from 'react'

import './changeQuantityButton.scss'

function ChangeQuantityButton({ children, onClick, disabled }) {
  return (
    <button
      className="quantity-change-button disabled:opacity-65 disabled:cursor-not-allowed"
      type="button"
      disabled={ disabled }
      onClick={ onClick }>
      { children }
    </button>
  )
}

export function DecrementQuantityButton({ onClick, disabled = false }) {
  return (
    <ChangeQuantityButton
      disabled={ disabled }
      onClick={ onClick }>
      <div>-</div>
    </ChangeQuantityButton>
  )
}

export function IncrementQuantityButton({ onClick, disabled = false }) {
  return (
    <ChangeQuantityButton
      disabled={ disabled }
      onClick={ onClick }>
      <div>+</div>
    </ChangeQuantityButton>
  )
}
