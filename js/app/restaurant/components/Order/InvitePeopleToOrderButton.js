import React from 'react'
import { useTranslation } from 'react-i18next'
import { useDispatch, useSelector } from 'react-redux'
import { openInvitePeopleToOrderModal } from '../../redux/actions'
import {
  selectIsGroupOrdersEnabled,
  selectIsPlayer,
} from '../../redux/selectors'

export default function InvitePeopleToOrderButton() {
  const isGroupOrdersEnabled = useSelector(selectIsGroupOrdersEnabled)
  const isPlayer = useSelector(selectIsPlayer)
  const isAuth = window._auth.isAuth

  const { t } = useTranslation()

  const dispatch = useDispatch()

  if (isGroupOrdersEnabled && !isPlayer && isAuth) {
    return (
      <div className="invite-to-order-button px-3 py-2 text-center">
        <a onClick={ () => dispatch(openInvitePeopleToOrderModal()) }>
          <span>{ t('INVITE_PEOPLE_TO_ADD_ITEMS') }</span>
        </a>
      </div>)
  } else {
    return null
  }
}
