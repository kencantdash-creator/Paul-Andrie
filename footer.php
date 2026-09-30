</main>
<footer class="bg-white border-t border-slate-200 text-center text-sm text-slate-500 py-5">
  KolektoSwap &copy; <?= date('Y') ?> &middot; Trading platform for local collectors and hobby shops
</footer>
<script>
  const btn = document.getElementById('menuBtn');
  const menu = document.getElementById('mobileMenu');
  btn.addEventListener('click', () => {
    const open = menu.classList.toggle('hidden') === false;
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
</script>
</body>
</html>
