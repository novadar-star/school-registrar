// Open add student modal
document.getElementById('btn-add-student').addEventListener('click', () => {
  document.getElementById('student-modal').classList.add('open');
});

// Close modal — X button
document.getElementById('modal-close-btn').addEventListener('click', () => {
  document.getElementById('student-modal').classList.remove('open');
});

// Close modal — Cancel button
document.getElementById('modal-cancel-btn').addEventListener('click', () => {
  document.getElementById('student-modal').classList.remove('open');
});

// Close modal — click outside the box
document.getElementById('student-modal').addEventListener('click', (e) => {
  if (e.target === e.currentTarget) {
    e.currentTarget.classList.remove('open');
  }
});
