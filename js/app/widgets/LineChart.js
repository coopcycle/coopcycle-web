
import React from 'react'
import { Line } from 'react-chartjs-2'
import { Chart, CategoryScale, LinearScale, LineElement, PointElement } from 'chart.js'

Chart.register(CategoryScale, LinearScale, LineElement, PointElement)

export default props => <Line {...props} />
