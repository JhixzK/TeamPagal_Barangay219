(() => {
  const buildApiUrl = () => {
    if (typeof window.API_URL === 'string' && window.API_URL.indexOf('<?') === -1) {
      return window.API_URL;
    }
    return window.location.origin + '/TeamPagal_Barangay219/Barangay219/api/';
  };

  const handleToggle = (toggle) => {
    const mode = toggle.checked ? 'resident' : 'official';
    const apiUrl = buildApiUrl();
    window.location.href = `${apiUrl}auth.php?action=view_mode&mode=${mode}`;
  };

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-view-mode-toggle]').forEach(toggle => {
      toggle.addEventListener('change', () => handleToggle(toggle));
    });
  });
})();
