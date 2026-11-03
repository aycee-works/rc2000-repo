(() => {
  const revealItems = document.querySelectorAll('[data-reveal]');
  if (revealItems.length && 'IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.documentElement.classList.add('reveal-ready');
    const revealObserver = new IntersectionObserver((entries, observer) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      });
    }, { threshold: 0.16 });
    revealItems.forEach((item) => revealObserver.observe(item));
  }

  document.querySelectorAll('[data-carousel]').forEach((root) => {
    const track = root.querySelector('.carousel-track');
    if (!track) return;
    const step = () => Math.max(220, track.clientWidth * 0.8);
    root.querySelector('[data-prev]')?.addEventListener('click', () => {
      track.scrollBy({ left: -step(), behavior: 'smooth' });
    });
    root.querySelector('[data-next]')?.addEventListener('click', () => {
      track.scrollBy({ left: step(), behavior: 'smooth' });
    });
  });

  document.querySelectorAll('[data-tabs]').forEach((tabs) => {
    const buttons = [...tabs.querySelectorAll('[data-tab-target]')];
    const panels = [...tabs.parentElement.querySelectorAll('[data-tab-panel]')];
    buttons.forEach((button) => {
      button.addEventListener('click', () => {
        const id = button.getAttribute('data-tab-target');
        buttons.forEach((item) => item.classList.toggle('is-active', item === button));
        panels.forEach((panel) => {
          const match = panel.getAttribute('data-tab-panel') === id;
          panel.hidden = !match;
          panel.classList.toggle('is-active', match);
        });
      });
    });
  });
})();
