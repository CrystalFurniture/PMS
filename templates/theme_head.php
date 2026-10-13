<?php /* Light/dark theme: applied before the page paints. Saved choice first, otherwise the computer's setting. */ ?>
<script>
(function () {
	var t = null;
	try { t = localStorage.getItem('pms-theme'); } catch (e) {}
	if (t !== 'dark' && t !== 'light') {
		t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
	}
	document.documentElement.setAttribute('data-theme', t);
	window.pmsToggleTheme = function () {
		var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
		document.documentElement.setAttribute('data-theme', next);
		try { localStorage.setItem('pms-theme', next); } catch (e) {}
		var b = document.querySelectorAll('.pms-theme-toggle');
		for (var i = 0; i < b.length; i++) {
			b[i].setAttribute('aria-label', next === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
			b[i].setAttribute('aria-pressed', next === 'dark' ? 'true' : 'false');
		}
	};
	document.addEventListener('click', function (e) {
		var b = e.target && e.target.closest ? e.target.closest('.pms-theme-toggle') : null;
		if (b) { window.pmsToggleTheme(); }
	});
	// Follow the computer's setting while the employee has not chosen one.
	if (window.matchMedia) {
		var mq = window.matchMedia('(prefers-color-scheme: dark)');
		var follow = function (e) {
			var saved = null;
			try { saved = localStorage.getItem('pms-theme'); } catch (err) {}
			if (saved !== 'dark' && saved !== 'light') { document.documentElement.setAttribute('data-theme', e.matches ? 'dark' : 'light'); }
		};
		if (mq.addEventListener) { mq.addEventListener('change', follow); } else if (mq.addListener) { mq.addListener(follow); }
	}
})();
</script>
