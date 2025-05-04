import React, { createRef } from "react"

import Split from 'react-split'
import { Rnd } from 'react-rnd'

import FiltersModalContent from './FiltersModalContent'
import RightPanel from './RightPanel'
import LeafletMap from './LeafletMap'
import Navbar from './Navbar'
import Modals from './Modals'
import { useDispatch } from "react-redux"
import { updateRightPanelSize } from "../redux/actions"
import { useSelector } from "react-redux"

const App = ({ loadingAnim }) => {

    const mapRef = createRef()
    const dispatch = useDispatch()
    const showFiltersPanel = useSelector(state => state.filtersModalIsOpen)

    const saveFilterPanelSize = ({ref}) => {
      localStorage.setItem('cpccl__dshbd__fltrs__size', JSON.stringify({width: ref.style.width, height: ref.style.height}))
    }
    const saveFilterPanelPosition = ({d}) => {
      // FIXME : I don't really get what x, y values from the "d" variable are indicating... position is not saved correctly between page loads
      localStorage.setItem('cpccl__dshbd__fltrs__position', JSON.stringify({x: d.x, y: d.y}))
    }

    const initialFilterPanelSize = {width: 1000, height: 510}
    // const initialFilterPanelPosition = {x: 0, y: 0,...JSON.parse(localStorage.getItem('cpccl__dshbd__fltrs__position'))}
    const initialFilterPanelPosition = {x: 0, y: 0}
    
    return (
    <>
      <div className="dashboard__toolbar-container">
        <Navbar />
      </div>
      <div className="dashboard__content">
        <Split
          sizes={[ 75, 25 ]}
          style={{ display: 'flex', width: '100%', height: '100%' }}
          onDrag={ sizes => dispatch(updateRightPanelSize(sizes[1])) }
          onDragEnd={ () => mapRef.current.invalidateSize() }>
          <div className="dashboard__map">
            <div className="dashboard__map-container">
              <svg xmlns="http://www.w3.org/2000/svg"
                className="arrow-container"
                style={{ position: 'absolute', top: '0px', left: '0px', width: '100%', height: '100%', overflow: 'visible', pointerEvents: 'none' }}
              >
                <defs>
                  <marker id="custom_arrow" markerWidth="4" markerHeight="4" refX="2" refY="2">
                    <circle cx="2" cy="2" r="2" stroke="none" fill="#3498DB"/>
                  </marker>
                </defs>
              </svg>
              <LeafletMap onLoad={ (e) => {
                // It seems like a bad way to get a ref to the map,
                // but we can't use the ref prop
                mapRef.current = e.target
              }} />
            </div>
          </div>
          <aside className="dashboard__aside">
            <RightPanel loadingAnim={loadingAnim} />
          </aside>
        </Split>
      </div>
      <Modals />
      <Rnd
        default={{...initialFilterPanelSize, ...initialFilterPanelPosition}}
        style={{display: showFiltersPanel ? 'block' : 'none'}}
        onDragStop={(e, d) => saveFilterPanelPosition({e, d})}
        onResizeStop={(e, direction, ref, delta, position) => saveFilterPanelSize({e, direction, ref, delta, position})}
        > 
          <FiltersModalContent />
      </Rnd>
    </>
  )
}

export { App }