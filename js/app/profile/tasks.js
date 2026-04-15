import axios from 'axios';

document.querySelectorAll('[data-task]').forEach((el) => {
  el.addEventListener('click', async function (e) {

    e.preventDefault();
    if (e.currentTarget.classList.contains('disabled')) {
      return;
    }

    const taskID = e.currentTarget.dataset.task;
    const response = await axios.get(window.Routing.generate('profile_task_complete', { id: taskID }));

    if (response.status === 200) {
      document.querySelector('#task-complete-modal').innerHTML = response.data;
      document.querySelector('#task-complete-modal').showModal();
    }

  });
})
