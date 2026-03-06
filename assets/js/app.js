(function () {
  var forms = document.querySelectorAll('form');
  forms.forEach(function (f) {
    f.addEventListener('submit', function () {
      var btn = f.querySelector('button[type="submit"]');
      if (btn) {
        btn.disabled = true;
        setTimeout(function () { btn.disabled = false; }, 1200);
      }
    });
  });
})();
