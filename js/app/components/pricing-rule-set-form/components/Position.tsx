import React from 'react'

type Props = {
  position: number
}

export default function Position({ position }: Props) {
  return <div>#{position + 1}</div>
}
