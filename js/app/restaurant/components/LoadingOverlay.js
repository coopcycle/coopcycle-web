import React from 'react'
import { useSelector } from 'react-redux';
import { selectIsLoadingOverlayVisible } from '../redux/selectors'

export default function LoadingOverlay() {
  const isVisible = useSelector(selectIsLoadingOverlayVisible);
  if (!isVisible) return null;
  return (
    <div className="absolute inset-0 flex items-center justify-center bg-base-100/60 backdrop-blur-sm" style={{ zIndex: 1000 }}>
      <span className="loading loading-spinner loading-lg"></span>
    </div>
  );
}
