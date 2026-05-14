/**
 * ═══════════════════════════════════════════════════════════
 * NexusCore 2.0 — Innovation Interaction Engine
 * ═══════════════════════════════════════════════════════════
 */
document.addEventListener('DOMContentLoaded', () => {

    // ─── 1. Theme Hub ───────────────────────────────────
    const applyTheme = (theme) => {
        document.body.classList.toggle('dark-mode', theme === 'dark');
        localStorage.setItem('nc_theme', theme);
        if (window.Chart) updateCharts(theme);
    };

    const savedTheme = localStorage.getItem('nc_theme') || 'light';
    applyTheme(savedTheme);

    window.toggleDarkMode = () => {
        const isDark = document.body.classList.contains('dark-mode');
        applyTheme(isDark ? 'light' : 'dark');
    };

    // ─── 2. Cinema Reveal Engine ────────────────────────
    const cinemaReveal = () => {
        const items = document.querySelectorAll('.card:not(.dropdown-menu), .nc-stat-card, .info-box');
        items.forEach((item, index) => {
            item.style.opacity = '0';
            item.style.transform = 'translateY(40px) scale(0.97)';
            item.style.transition = `all 0.8s cubic-bezier(0.2, 0.8, 0.2, 1) ${index * 0.1}s`;

            setTimeout(() => {
                item.style.opacity = '1';
                item.style.transform = 'translateY(0) scale(1)';
            }, 100);
        });
    };
    cinemaReveal();

    // ─── 3. Professional Counter ────────────────────────
    const startCounters = () => {
        const counters = document.querySelectorAll('.nc-counter');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animate(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        const animate = (el) => {
            const target = parseFloat(el.dataset.target);
            const duration = 2000;
            let start = null;

            const step = (now) => {
                if (!start) start = now;
                const progress = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 4); // easeOutQuart
                const current = eased * target;
                
                el.innerText = Math.floor(current).toLocaleString();
                if (progress < 1) requestAnimationFrame(step);
            };
            requestAnimationFrame(step);
        };

        counters.forEach(c => observer.observe(c));
    };
    startCounters();

    // ─── 4. 3D Parallax Tilt ────────────────────────────
    const init3DTilt = () => {
        const cards = document.querySelectorAll('.nc-stat-card');
        cards.forEach(card => {
            card.addEventListener('mousemove', (e) => {
                const { left, top, width, height } = card.getBoundingClientRect();
                const x = (e.clientX - left) / width;
                const y = (e.clientY - top) / height;
                
                const tiltX = (y - 0.5) * -15;
                const tiltY = (x - 0.5) * 15;

                card.style.transform = `perspective(1000px) rotateX(${tiltX}deg) rotateY(${tiltY}deg) translateY(-10px) scale(1.05)`;
                
                // Inner Parallax
                const icon = card.querySelector('.nc-stat-icon');
                if (icon) {
                    icon.style.transform = `translateZ(50px) rotate(${(x - 0.5) * -10}deg)`;
                }
                const value = card.querySelector('.nc-stat-value');
                if (value) {
                    value.style.transform = `translateZ(30px)`;
                }
            });

            card.addEventListener('mouseleave', () => {
                card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) translateY(0) scale(1)';
                card.style.transition = 'all 0.6s cubic-bezier(0.2, 0.8, 0.2, 1)';
                
                const icon = card.querySelector('.nc-stat-icon');
                if (icon) icon.style.transform = 'translateZ(0) rotate(0)';
            });

            card.addEventListener('mouseenter', () => {
                card.style.transition = 'all 0.1s ease-out';
            });
        });
    };
    init3DTilt();

    // ─── 5. Magnetic Buttons ───────────────────────────
    const initMagneticButtons = () => {
        const btns = document.querySelectorAll('.btn-primary, .btn-info, .btn-success');
        btns.forEach(btn => {
            btn.addEventListener('mousemove', (e) => {
                const { left, top, width, height } = btn.getBoundingClientRect();
                const x = e.clientX - (left + width / 2);
                const y = e.clientY - (top + height / 2);
                
                btn.style.transform = `translate(${x * 0.3}px, ${y * 0.3}px)`;
            });

            btn.addEventListener('mouseleave', () => {
                btn.style.transform = 'translate(0, 0)';
                btn.style.transition = 'all 0.5s cubic-bezier(0.2, 0.8, 0.2, 1)';
            });

            btn.addEventListener('mouseenter', () => {
                btn.style.transition = 'all 0.1s ease-out';
            });
        });
    };
    initMagneticButtons();

    // ─── 6. Innovation Ripple ──────────────────────────
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn');
        if (!btn) return;

        const ripple = document.createElement('span');
        ripple.className = 'nc-ripple';
        const { left, top, width, height } = btn.getBoundingClientRect();
        const size = Math.max(width, height);
        
        ripple.style.width = ripple.style.height = `${size}px`;
        ripple.style.left = `${e.clientX - left - size / 2}px`;
        ripple.style.top = `${e.clientY - top - size / 2}px`;

        btn.appendChild(ripple);
        setTimeout(() => ripple.remove(), 600);
    });

    // ─── 7. Ambient Glass Particles ─────────────────────
    const initParticles = () => {
        const target = document.body;
        if (!target) return;

        for (let i = 0; i < 8; i++) {
            const p = document.createElement('div');
            const size = 5 + Math.random() * 15;
            p.style.cssText = `
                position: fixed;
                width: ${size}px; height: ${size}px;
                background: rgba(99, 102, 241, 0.1);
                border: 1px solid rgba(255, 255, 255, 0.1);
                backdrop-filter: blur(5px);
                border-radius: 4px;
                top: ${Math.random() * 100}%;
                left: ${Math.random() * 100}%;
                z-index: -1;
                pointer-events: none;
                animation: particleFloat ${10 + Math.random() * 20}s linear infinite;
                opacity: 0.4;
            `;
            target.appendChild(p);
        }

        const style = document.createElement('style');
        style.innerText = `
            @keyframes particleFloat {
                0% { transform: translate(0, 0) rotate(0deg); opacity: 0; }
                10% { opacity: 0.4; }
                90% { opacity: 0.4; }
                100% { transform: translate(${Math.random() * 100 - 50}px, -200px) rotate(${Math.random() * 360}deg); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    };
    initParticles();

    // ─── 8. Chart Adaptation ───────────────────────────
    function updateCharts(theme) {
        if (!window.Chart || !Chart.instances) return;
        const color = theme === 'dark' ? '#94a3b8' : '#64748b';
        Object.values(Chart.instances).forEach(c => {
            if (c.options.scales) {
                ['x', 'y'].forEach(s => {
                    if (c.options.scales[s]) {
                        c.options.scales[s].ticks.color = color;
                        c.options.scales[s].grid.color = 'rgba(99, 102, 241, 0.1)';
                    }
                });
            }
            c.update();
        });
    }
});

