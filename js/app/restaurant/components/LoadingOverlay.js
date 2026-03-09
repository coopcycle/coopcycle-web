import React from 'react'
import { LoadingOverlay } from '@mantine/core';
import { useSelector } from 'react-redux';
import { selectIsLoadingOverlayVisible } from '../redux/selectors'

export default () => {

  const isVisible = useSelector(selectIsLoadingOverlayVisible);

  console.log('isVisible', isVisible)

  return (
    <LoadingOverlay visible={isVisible} zIndex={1000} overlayProps={{ radius: "sm", blur: 2 }} />
  )
}
