// script.js
(() => {
  // Elements
  const modal = document.getElementById('modal');
  if (!modal) return; // abort if modal not present
  const dialog = modal.querySelector('.modal__dialog');
  const overlayCloseEls = modal.querySelectorAll('[data-close]');
  const form = document.getElementById('modalForm');
  const feedback = document.getElementById('formFeedback');
  const packageFormPanel = document.getElementById('package-form-panel');
  const packageFormTitle = document.getElementById('package-form-title');
  const packageFormFeedback = document.getElementById('package-form-feedback');
  const cognitoFormContainer = document.getElementById('cognito-form-container');

  if (packageFormPanel && packageFormTitle && packageFormFeedback && cognitoFormContainer) {
    const packageButtons = document.querySelectorAll('.package__button[data-form]');
    const closePackageForm = packageFormPanel.querySelector('.package-form-panel__close');

    packageButtons.forEach((button) => {
      button.addEventListener('click', () => {
        const packageName = button.closest('.package')?.querySelector('.package__name')?.textContent.trim();
        packageFormTitle.textContent = `${packageName || 'Selected'} package order form`;
        packageFormFeedback.textContent = '';
        cognitoFormContainer.replaceChildren();
        packageFormPanel.hidden = false;

        const cognitoScript = document.createElement('script');
        cognitoScript.src = 'https://www.cognitoforms.com/f/seamless.js';
        cognitoScript.async = true;
        cognitoScript.dataset.key = 'mgMJcx3TQEaKMg1phQodJw';
        cognitoScript.dataset.form = button.dataset.form;
        cognitoScript.onerror = () => {
          packageFormFeedback.textContent = 'The order form could not be loaded. Please try again.';
        };
        cognitoFormContainer.appendChild(cognitoScript);

        packageButtons.forEach((packageButton) => {
          packageButton.setAttribute('aria-expanded', String(packageButton === button));
        });
        packageFormPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });

    closePackageForm.addEventListener('click', () => {
      packageFormPanel.hidden = true;
      cognitoFormContainer.replaceChildren();
      packageButtons.forEach((button) => button.setAttribute('aria-expanded', 'false'));
    });
  }

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

  function openModal(trigger) {
    lastFocused = trigger || document.activeElement;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('no-scroll');

    // focus the dialog then the first focusable control
    requestAnimationFrame(() => {
      dialog.focus();
      const first = dialog.querySelector(focusableSelector);
      if (first) first.focus();
    });

    document.addEventListener('keydown', onKeyDown);
  }

  function closeModal() {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('no-scroll');
    feedback && (feedback.textContent = '');
    document.removeEventListener('keydown', onKeyDown);
    if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
    // Update any triggers' aria-expanded to false
    document.querySelectorAll('[data-open="modal"]').forEach(btn => btn.setAttribute('aria-expanded', 'false'));
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

  // Wire up open triggers (data-open="modal")
  document.querySelectorAll('[data-open="modal"]').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      btn.setAttribute('aria-expanded', 'true');
      openModal(btn);
    });
  });

  // Close via overlay or close buttons
  overlayCloseEls.forEach(el => el.addEventListener('click', closeModal));

  // Click on overlay (but ignore clicks inside the dialog)
  modal.addEventListener('click', (e) => {
    if (e.target === modal.querySelector('.modal__overlay')) closeModal();
  });

  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }

      const submitButton = form.querySelector('[type="submit"]');
      submitButton.disabled = true;
      feedback.style.color = 'black';
      feedback.textContent = 'Sending your message...';

      try {
        const response = await fetch(form.action, {
          method: 'POST',
          body: new FormData(form),
          headers: { Accept: 'application/json' }
        });
        let result;
        try {
          result = JSON.parse(await response.text());
        } catch {
          throw new Error(
            `The form server returned an unexpected response (HTTP ${response.status}). ` +
            'Verify that contact.php is deployed and PHP is enabled on the host.'
          );
        }

        if (!response.ok) {
          throw new Error(result.error || 'We could not send your message. Please try again.');
        }

        feedback.style.color = 'green';
        feedback.textContent = 'Your message was sent. Thank you!';
        form.reset();
      } catch (error) {
        feedback.style.color = 'red';
        feedback.textContent = error.message || 'Unable to contact the server. Please try again later.';
      } finally {
        submitButton.disabled = false;
      }
    });
  }

  // Prevent clicks inside dialog from closing
  if (dialog) dialog.addEventListener('click', (e) => e.stopPropagation());
})();