import React, { useEffect, useState } from 'react'

export default ({ distance, tasks }) => {
  const [createdTasks, setCreatedTasks] = useState(null)

  useEffect(() => {
    const createdTasks = tasks.filter(task => task.address.streetAddress !== '')
    setCreatedTasks(createdTasks)
  }, [tasks])

  return (
    <div className="resume mb-4">
      <div className="resume__distance mt-2 mb-4">
        {' '}
        <span className="font-weight-bold">Distance</span> : {distance} kms{' '}
      </div>
      <div className="resumer__tasks">
        {createdTasks?.map((task, index) => (
          <div key={index} className="resume__tasks__item mb-3">
            <div>
              {task.type === 'PICKUP' ? (
                <div className="resume__tasks__item__title mb-1 font-weight-bold">
                  <i className="fa fa-arrow-up"></i> Pickup{' '}
                </div>
              ) : (
                <div className="resume__tasks__item__title mb-1 font-weight-bold">
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
