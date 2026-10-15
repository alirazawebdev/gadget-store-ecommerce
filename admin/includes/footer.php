<?php /** Admin layout footer */ ?>
    </div><!-- .content -->
  </div><!-- .admin-main -->
</div><!-- .admin-wrap -->
<script>
(function () {
  var b = document.getElementById('burger');
  if (b) b.addEventListener('click', function () { document.body.classList.toggle('sidebar-open'); });
  document.addEventListener('click', function (e) {
    if (window.innerWidth <= 992 && document.body.classList.contains('sidebar-open')) {
      if (!e.target.closest('#sidebar') && !e.target.closest('#burger')) document.body.classList.remove('sidebar-open');
    }
  });
})();
</script>
</body>
</html>
