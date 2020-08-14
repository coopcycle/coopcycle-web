import React from 'react'

export default (props) => {

  return (
    <img src={ window.Routing.generate('user_avatar', { username: props.username }) }
      width="20" height="20" />
  )
}
