(function () {
  var supportsSW = 'serviceWorker' in navigator;
  var isLocal = location.hostname === 'localhost' || location.hostname === '127.0.0.1';
  if (!supportsSW) return;
  if (!window.isSecureContext && !isLocal) return;

  // Soporta despliegue bajo subruta (ej: /electroplan/)
  var basePath = (document.querySelector('base') && document.querySelector('base').getAttribute('href')) || '/';
  if (!basePath.endsWith('/')) basePath += '/';

  window.addEventListener('load', function () {
    var swUrl = new URL('sw.js', location.origin + basePath).toString();
    navigator.serviceWorker.register(swUrl, { scope: basePath }).catch(function () {
      // Si no existe SW o falla registro, la app sigue funcionando normal.
    });
  });
})();
