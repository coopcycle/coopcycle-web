import React, { useState } from "react"

export default ({ initialChoices, onChange }) => {
  // récupérer les dates dans les initalChoices
  // les heures sont fonctions des dates. Donc créer un objet avec comme clés les dates et en valeur, un array avec les horaires possibles ou alors
  // venir uniquement créer les heures en fonction de l'heure sélectionnée. On a un state vide et un useEffect pour le gérer

  // on a besoin d'un calendrier et on vient désactiver ce qui n'est pas une date dans le state
  // un select avec les heures qui s'affichent en fonction de la date sélectionnée // il va falloir un state avec la date sélectionnée

  const [value, setValue] = useState(initialChoices[0].value)

  const dates = {}

  initialChoices.forEach(choice => {
    const [date, hour] = choice.value.split(' ')
    if (Object.prototype.hasOwnProperty.call(dates, date)) {
      dates[date].push(hour)
    } else {
      dates[date] = [hour]
    }
  })

  return (
    <select
      onChange={e => {
        setValue(e.target.value)
        onChange(e.target.value)
      }}
      value={value}>
      {initialChoices.map(choice => (
        <option key={choice.value} value={choice.value}>
          {choice.value}
        </option>
      ))}
    </select>
  )
}
