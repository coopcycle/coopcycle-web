import React from "react"
import { formatDistance, formatDuration, formatVolumeUnits, formatWeight } from "../redux/utils"
import { useSelector } from "react-redux"
import { selectSettings } from "../redux/selectors"


export default ({ duration, distance, weight, volumeUnits}) => {

    const { showWeightAndVolumeUnit, showDistanceAndTime } = useSelector(selectSettings)

    const durationFormatted = formatDuration(duration)
    const distanceFormatted = formatDistance(distance)
    const weightFormatted = formatWeight(weight)
    const volumeUnitsFormatted = formatVolumeUnits(volumeUnits)

    return (
      <div className="d-flex align-items-center">
        { showDistanceAndTime ?
          <>
            <span>{ durationFormatted }</span>
            <span className="mx-2">|</span>
            <span>{ distanceFormatted }</span>
          </>
          : null
        }
        { showDistanceAndTime && showWeightAndVolumeUnit ? (<span className="mx-2">|</span>) : null }
        { showWeightAndVolumeUnit ?
          <>
              <span>{ weightFormatted }</span>
              <span className="mx-2">|</span>
              <span>{ volumeUnitsFormatted }</span>
          </>
        : null
        }
      </div>
    )
}