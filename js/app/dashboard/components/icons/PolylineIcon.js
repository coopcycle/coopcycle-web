import React from "react"

export default ({fillColor}) => {

    let style = {fill: fillColor, fillRule: 'evenodd', strokeWidth: '0px'}

    return (
        <svg style={style} data-name="Show polyline" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 184.63 182.02">
            <g id="Calque_1-2" data-name="Calque 1">
                <g>
                <path d="M157.76,128.27c-16.65.66-25.53,12.13-26.62,24.31h-12.06s-65.88.01-65.88.01c-.69-.03.14.09-.9-.2-1.28-.34-2.49-.87-3.71-1.36-24.76-11.62-22.84-45.8,2.95-54.62,3.66-.01,7.34-.01,11.03-.01v20.82l25.57-20.83,5.42-4.42-5.66-4.61-25.33-20.65v20.65h-11.42c-.38,0-.76-.02-1.14.02l-1.02.36c-33.89,11.43-36.47,56.3-4.13,71.52,1.59.65,3.16,1.32,4.81,1.78l1.57.48c.61.2,1.77.05,1.96.1h65.88v-.03h12.68c2.46,10.63,11.12,19.84,25.99,20.43,14.84,0,26.87-12.03,26.87-26.87s-12.03-26.87-26.87-26.87Z"/>
                <path d="M53.61,27.49C53.61,12.65,41.57.62,26.73.62c-35.64,1.41-35.65,52.33,0,53.75,14.84,0,26.87-12.03,26.87-26.87Z"/>
                <path d="M85.83,29.77h50.49c.71.27,1.46.46,2.15.77,21.99,9.11,24.87,37.16,8.27,51.06-3.57-10.65-13.63-18.32-25.48-18.32-16.55.66-25.41,11.98-26.59,24.08-.3,3.02-.12,6.08.55,9.03,2.39,10.73,11.07,20.05,26.04,20.64,14.28,0,25.98-11.15,26.83-25.22,12.78-8.03,20.39-23.74,17.98-38.8-1.85-13.63-11.43-25.79-24.22-30.85-.78-.35-1.61-.58-2.41-.87-.33-.07-1.18-.48-1.87-.55h-51.74V0l-30.99,25.25,30.99,25.26v-20.74Z"/>
                </g>
            </g>
        </svg>
    )
}