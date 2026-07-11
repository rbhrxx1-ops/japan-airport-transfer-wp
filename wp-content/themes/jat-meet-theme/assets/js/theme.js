(() => {
  'use strict';

  const signInput = document.querySelector('[data-jat-sign-input]');
  const signOutput = document.querySelector('[data-jat-sign-output]');

  if (signInput instanceof HTMLInputElement && signOutput instanceof HTMLElement) {
    const defaultName = signOutput.textContent?.trim() || 'WELCOME';

    const updateSign = () => {
      const value = signInput.value.trim().slice(0, 40);
      signOutput.textContent = value || defaultName;
    };

    signInput.addEventListener('input', updateSign);
  }

  const navigation = document.querySelector('.wp-block-navigation');
  if (navigation instanceof HTMLElement) {
    const observer = new MutationObserver(() => {
      const menu = navigation.querySelector('.wp-block-navigation__responsive-container');
      const isOpen = menu?.classList.contains('is-menu-open') ?? false;
      document.documentElement.classList.toggle('jat-menu-open', isOpen);
    });

    observer.observe(navigation, {
      attributes: true,
      subtree: true,
      attributeFilter: ['class'],
    });
  }
})();
