import React from 'react'
import { PageHeader } from '../components/PageHeader'

/**
 * Test component that will always crash during render
 * This is useful for testing error boundaries and error handling
 */
export default function CrashTestPage() {
  // Intentionally throw an error during render
  throw new Error('This is an intentional crash for testing purposes!')

  // This code will never be reached
  return (
    <div>
      <PageHeader title="Crash Test Page" />
      <div className="container">
        <p>This page should never render successfully.</p>
      </div>
    </div>
  )
}
