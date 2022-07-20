
import React from 'react'
import { Bar } from 'react-chartjs-2'
import { Chart, CategoryScale, LinearScale, BarElement } from 'chart.js'

Chart.register(BarElement, CategoryScale, LinearScale)

export default props => <Bar {...props} />
