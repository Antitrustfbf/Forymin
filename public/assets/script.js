document.addEventListener('input', (event) => {
  if (!(event.target instanceof HTMLTextAreaElement)) return;
  const el = event.target;
  if (!el.classList.contains('auto-resize')) return;
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 500) + 'px';
});

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('textarea.auto-resize').forEach((el) => {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 500) + 'px';
  });
});