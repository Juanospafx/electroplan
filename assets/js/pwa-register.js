(function () {
  if (!('serviceWorker' in navigator)) return;
  if (!window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') return;

  var basePath = '/electroplan';
  var swPath = basePath + '/sw.js';

  window.addEventListener('load', function () {
    navigator.serviceWorker.register(swPath, { scope: basePath + '/' }).catch(function () {
      // Registration can fail on non-HTTPS development hosts. The app still works as a normal web app.
    });
  });
})();
