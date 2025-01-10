import React, { useEffect, useState } from 'react'

export default ({ distance, tasks }) => {
  const [createdTasks, setCreatedTasks] = useState(null)
  console.log(createdTasks)

  useEffect(() => {
    const createdTasks = tasks.filter(task => task.address.streetAddress !== '')
    setCreatedTasks(createdTasks)
  }, [tasks])

  return (
    <div className="resume mt-2 mb-4">
      <div className="resume__title"> Résumé </div>
      <div className="resume__distance"> Distance : {distance} kms </div>
      <div className="resumer__tasks">
        {createdTasks?.map((task, index) => (
          <div key={index} className="resume__tasks__item">
            <div>
              {task.type === 'PICKUP' ? (
                <div className="resume__tasks__item__title">
                  <i className="fa fa-arrow-up"></i> Pickup{' '}
                </div>
              ) : (
                <div className="resume__tasks__item__title">
                  <i className="fa fa-arrow-down"></i> Dropoff
                </div>
              )}
              <div className="resume__tasks__item__address">
                {task.address.streetAddress}
              </div>
              {task.packages?.map((p, index) =>
                p.quantity > 0 ? (
                  <div key={index} className="resume__tasks__item__packages">
                    {p.quantity} {p.type}
                  </div>
                ) : null,
              )}
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
