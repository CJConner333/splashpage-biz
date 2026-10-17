// Accessible modal with focus trap
(() => {
  const openBtn = document.getElementById('openModalBtn');
  const modal = document.getElementById('modal');
  const dialog = modal.querySelector('.modal__dialog');
  const overlayCloseEls = modal.querySelectorAll('[data-close]');
  const form = document.getElementById('modalForm');
  const feedback = document.getElementById('formFeedback');
  let lastFocused = null;

  const focusableSelector = [
    'a[href]',
    'area[href]',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    'button:not([disabled])',
    'iframe',
    '[tabindex]:not([tabindex="-1"])',
    '[contenteditable]'
  ].join(',');

  function openModal() {
    lastFocused = document.activeElement;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('no-scroll');

    // Wait a tick then focus
    requestAnimationFrame(() => {
      dialog.focus();
      // set focus to the first focusable form control if present
      const first = dialog.querySelector(focusableSelector);
      if (first) first.focus();
    });

    document.addEventListener('keydown', onKeyDown);
    // find all triggers that should open the modal
document.querySelectorAll('[data-open="modal"]').forEach(btn => {
  btn.addEventListener('click', () => {
    // openModal() is from your modal script; it should set aria-expanded & focus management
    openModal(); // or call the function you have that opens the modal
    // update aria-expanded for the trigger
    btn.setAttribute('aria-expanded', 'true');
  });
});
  }

  function closeModal() {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('no-scroll');
    feedback.textContent = '';
    document.removeEventListener('keydown', onKeyDown);
    if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
  }

  function onKeyDown(e) {
    if (e.key === 'Escape') {
      e.preventDefault();
      closeModal();
      return;
    }

    if (e.key === 'Tab') {
      // focus trap
      const focusables = Array.from(dialog.querySelectorAll(focusableSelector));
      if (focusables.length === 0) {
        e.preventDefault();
        return;
      }
      const first = focusables[0];
      const last = focusables[focusables.length - 1];
      if (e.shiftKey) {
        if (document.activeElement === first) {
          e.preventDefault();
          last.focus();
        }
      } else {
        if (document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    }
  }

  // Open
  openBtn.addEventListener('click', openModal);

  // Close via overlay or close buttons
  overlayCloseEls.forEach(el => el.addEventListener('click', closeModal));

  // Click on overlay (but ignore clicks inside the dialog)
  modal.addEventListener('click', (e) => {
    if (e.target === modal.querySelector('.modal__overlay')) closeModal();
  });

  // Form submit (example)
  form.addEventListener('submit', (e) => {
    e.preventDefault();

    // Basic constraint validation
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }

    const data = new FormData(form);
    const payload = Object.fromEntries(data.entries());
    // Simulate async submission
    feedback.style.color = 'black';
    feedback.textContent = 'Sending...';

    setTimeout(() => {
      // Replace with fetch() in real app
      feedback.style.color = 'green';
      feedback.textContent = 'Thanks! Your message was sent.';
      form.reset();

      // close after a short delay
      setTimeout(closeModal, 1200);
    }, 700);
  });

  // Prevent clicks inside dialog from closing (keeps overlayClose logic minimal)
  dialog.addEventListener('click', (e) => e.stopPropagation());
})();