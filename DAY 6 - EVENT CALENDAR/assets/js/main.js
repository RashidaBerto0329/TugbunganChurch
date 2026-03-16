/* ================================================================
   church/assets/js/main.js
   Our Lady of Peace and Good Voyage Parish — MIS
   Landing page animations + global utilities
================================================================ */

document.addEventListener('DOMContentLoaded', function () {

    /* ── 1. STARFIELD CANVAS ──────────────────────────────────
       Renders the floating star particles from the parish seal
    ──────────────────────────────────────────────────────────── */
    const canvas = document.getElementById('starfield');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        let stars  = [];
        let W, H;

        function resize() {
            W = canvas.width  = window.innerWidth;
            H = canvas.height = window.innerHeight;
        }

        function createStar() {
            return {
                x:      Math.random() * W,
                y:      Math.random() * H,
                r:      Math.random() * 1.4 + 0.3,
                alpha:  Math.random() * 0.6 + 0.2,
                da:     (Math.random() * 0.008 + 0.003) * (Math.random() < 0.5 ? 1 : -1),
                vx:     (Math.random() - 0.5) * 0.08,
                vy:     (Math.random() - 0.5) * 0.08,
                // Some stars are cross-shaped like the seal
                isCross: Math.random() < 0.12,
            };
        }

        function initStars(count) {
            stars = [];
            for (let i = 0; i < count; i++) stars.push(createStar());
        }

        function drawCross(x, y, r, alpha) {
            ctx.save();
            ctx.globalAlpha = alpha;
            ctx.strokeStyle = `rgba(224, 192, 96, ${alpha})`;
            ctx.lineWidth   = r * 0.7;
            ctx.lineCap     = 'round';
            // 4-pointed star cross
            const arm = r * 3.5;
            ctx.beginPath(); ctx.moveTo(x - arm, y); ctx.lineTo(x + arm, y); ctx.stroke();
            ctx.beginPath(); ctx.moveTo(x, y - arm); ctx.lineTo(x, y + arm); ctx.stroke();
            ctx.restore();
        }

        function drawStar(s) {
            if (s.isCross) {
                drawCross(s.x, s.y, s.r, s.alpha);
            } else {
                ctx.beginPath();
                ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
                ctx.fillStyle = `rgba(224, 200, 120, ${s.alpha})`;
                ctx.fill();
            }
        }

        function tick() {
            ctx.clearRect(0, 0, W, H);
            stars.forEach(s => {
                s.alpha += s.da;
                if (s.alpha > 0.85 || s.alpha < 0.1) s.da *= -1;
                s.x += s.vx;
                s.y += s.vy;
                // Wrap around edges
                if (s.x < -10) s.x = W + 10;
                if (s.x > W + 10) s.x = -10;
                if (s.y < -10) s.y = H + 10;
                if (s.y > H + 10) s.y = -10;
                drawStar(s);
            });
            requestAnimationFrame(tick);
        }

        resize();
        initStars(90);
        tick();
        window.addEventListener('resize', () => { resize(); initStars(90); });
    }


    /* ── 2. NAVBAR SCROLL EFFECT ──────────────────────────────
       Adds .scrolled class to darken the nav on scroll
    ──────────────────────────────────────────────────────────── */
    const nav = document.getElementById('topnav');
    if (nav) {
        function onScroll() {
            if (window.scrollY > 40) {
                nav.classList.add('scrolled');
            } else {
                nav.classList.remove('scrolled');
            }
        }
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll(); // run once on load
    }


    /* ── 3. REVEAL ON SCROLL ──────────────────────────────────
       Uses IntersectionObserver to animate elements into view
    ──────────────────────────────────────────────────────────── */
    const revealEls = document.querySelectorAll('.reveal-up, .reveal-fade');

    if (revealEls.length) {
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        observer.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0.12, rootMargin: '0px 0px -40px 0px' }
        );

        revealEls.forEach(el => observer.observe(el));
    }


    /* ── 4. AUTO-DISMISS ALERTS ───────────────────────────────
       Success alerts auto-hide after 4.5 seconds
    ──────────────────────────────────────────────────────────── */
    const alerts = document.querySelectorAll('.alert-success, .alert.auto-dismiss');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            alert.style.opacity    = '0';
            alert.style.transform  = 'translateY(-8px)';
            setTimeout(() => alert.remove(), 500);
        }, 4500);
    });


    /* ── 5. CONFIRM DELETE ────────────────────────────────────
       Any link/button with data-confirm triggers a dialog
       Usage: <a href="delete.php?id=1" data-confirm="Delete this record?">
    ──────────────────────────────────────────────────────────── */
    document.addEventListener('click', function (e) {
        const el = e.target.closest('[data-confirm]');
        if (el) {
            const msg = el.dataset.confirm || 'Are you sure?';
            if (!confirm(msg)) {
                e.preventDefault();
                e.stopPropagation();
            }
        }
    });


    /* ── 6. PASSWORD TOGGLE ───────────────────────────────────
       Any button with data-toggle-password="#inputId" toggles
       visibility of that password field
    ──────────────────────────────────────────────────────────── */
    document.querySelectorAll('[data-toggle-password]').forEach(btn => {
        btn.addEventListener('click', function () {
            const target = document.querySelector(this.dataset.togglePassword);
            if (!target) return;
            const isHidden = target.type === 'password';
            target.type = isHidden ? 'text' : 'password';
            // Toggle icon
            const icon = this.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-eye',        !isHidden);
                icon.classList.toggle('fa-eye-slash',   isHidden);
            }
        });
    });


    /* ── 7. ACTIVE NAV LINK (sidebar) ─────────────────────────
       Marks the current page's nav link as active based on URL
       (Backup — PHP also handles this via $_SERVER['REQUEST_URI'])
    ──────────────────────────────────────────────────────────── */
    const path = window.location.pathname;
    document.querySelectorAll('#sidebar .nav-item[href]').forEach(link => {
        if (link.getAttribute('href') && path.includes(link.getAttribute('href').split('/').pop())) {
            link.classList.add('active');
        }
    });


    /* ── 8. SIDEBAR SUBMENU (mobile fallback) ─────────────────
       Already handled inline in header.php, but exposed globally
       so other pages can call toggleSubmenu() if needed
    ──────────────────────────────────────────────────────────── */
    window.toggleSubmenu = function (id) {
        const submenu = document.getElementById(id);
        if (!submenu) return;
        const key  = id.replace('sub-', '');
        const icon = document.getElementById('icon-' + key);
        const isOpen = submenu.classList.contains('open');

        document.querySelectorAll('.submenu').forEach(el => el.classList.remove('open'));
        document.querySelectorAll('[id^="icon-"]').forEach(el => el.classList.remove('rotate-180'));

        if (!isOpen) {
            submenu.classList.add('open');
            if (icon) icon.classList.add('rotate-180');
        }
    };

    window.openSidebar = function () {
        const sidebar  = document.getElementById('sidebar');
        const overlay  = document.getElementById('sidebar-overlay');
        if (sidebar) sidebar.classList.remove('-translate-x-full');
        if (overlay) overlay.classList.add('show');
    };

    window.closeSidebar = function () {
        const sidebar  = document.getElementById('sidebar');
        const overlay  = document.getElementById('sidebar-overlay');
        if (sidebar) sidebar.classList.add('-translate-x-full');
        if (overlay) overlay.classList.remove('show');
    };

});