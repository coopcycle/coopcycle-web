
import React from 'react'
import { Pie } from 'react-chartjs-2'
import { Chart, CategoryScale, LinearScale, ArcElement } from 'chart.js'

Chart.register(ArcElement, CategoryScale, LinearScale)

export default props => <Pie {...props} />
