    </main>
</div>

<script>
// ==========================================
// Global Interactive Helpers & Enhancements
// ==========================================

// 1. Sidebar Toggle & Persistence
function toggleSidebar() {
    document.body.classList.toggle('sidebar-collapsed');
    const isCollapsed = document.body.classList.contains('sidebar-collapsed');
    localStorage.setItem('sms_sidebar_collapsed', isCollapsed ? '1' : '0');
}

// Restore sidebar state on initial load
(function() {
    if (localStorage.getItem('sms_sidebar_collapsed') === '1') {
        document.body.classList.add('sidebar-collapsed');
    }
})();

// Keyboard shortcut Ctrl+B or Cmd+B to toggle sidebar
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'b') {
        e.preventDefault();
        toggleSidebar();
    }
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(modal => {
            modal.classList.remove('active');
        });
    }
});

// 2. Live Real-Time Clock
function updateClock() {
    const clockEl = document.getElementById('liveClock');
    if (!clockEl) return;
    const now = new Date();
    const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    clockEl.innerHTML = '<i class="fa-regular fa-clock" style="margin-right:4px;"></i>' + timeStr;
}
setInterval(updateClock, 1000);
updateClock();

// 3. Modal Helpers
function openModal(id) {
    const el = document.getElementById(id);
    if (el) {
        el.classList.add('active');
        const firstInput = el.querySelector('input:not([type="hidden"]), select, textarea');
        if (firstInput) setTimeout(() => firstInput.focus(), 100);
    }
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('active');
}

// Close modal when clicking on backdrop
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
    }
});

// 4. Auto-dismiss flash alert with smooth fade
(function() {
    const alert = document.getElementById('flashAlert');
    if (alert) {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-6px)';
            setTimeout(() => alert.remove(), 400);
        }, 4500);
    }
})();

// 5. Stat Count-Up Animation
document.addEventListener('DOMContentLoaded', () => {
    const counters = document.querySelectorAll('.stat-card .value, .stat-item-modern .val');
    counters.forEach(counter => {
        const text = counter.innerText.trim();
        // Match numbers, possibly with currency prefix or decimals
        const match = text.match(/^([^0-9.-]*)([0-9,.]+)(.*)$/);
        if (match) {
            const prefix = match[1];
            const rawVal = parseFloat(match[2].replace(/,/g, ''));
            const suffix = match[3];
            if (!isNaN(rawVal) && rawVal > 0 && rawVal < 10000000) {
                const duration = 800;
                const startTime = performance.now();
                const step = (currentTime) => {
                    const elapsed = currentTime - startTime;
                    const progress = Math.min(elapsed / duration, 1);
                    // easeOutQuart
                    const ease = 1 - Math.pow(1 - progress, 4);
                    const currentVal = Math.round(rawVal * ease * 100) / 100;
                    counter.innerText = prefix + (rawVal % 1 === 0 ? Math.round(currentVal).toLocaleString() : currentVal.toFixed(2)) + suffix;
                    if (progress < 1) {
                        requestAnimationFrame(step);
                    } else {
                        counter.innerText = text;
                    }
                };
                requestAnimationFrame(step);
            }
        }
    });
});
</script>
</body>
</html>
