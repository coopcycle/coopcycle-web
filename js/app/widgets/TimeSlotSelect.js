import React from "react"
import { createRoot } from "react-dom/client"

const TimeSlotSelect = () => { 
    return <span>Hello world</span>
}



export default function (el, props) {
    
    const root = createRoot(el)

    root.render(
        <TimeSlotSelect {...props} />
    )
  }
  