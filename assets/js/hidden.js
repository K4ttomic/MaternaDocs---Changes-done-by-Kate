// Hidden staff entry: click any element marked data-secret-link 5 times within 3 seconds.
document.querySelectorAll('[data-secret-link]').forEach(function (el) {
  var times = [];
  el.addEventListener('click', function () {
    var now = Date.now();
    times.push(now);
    if (times.length > 5) times.shift();
    if (times.length === 5 && now - times[0] <= 3000) window.location.href = el.getAttribute('data-secret-link');
  });
});
