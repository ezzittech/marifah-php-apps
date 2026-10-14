// School Schedule System — Frontend JS

// ── Live Clock ──
function updateClock() {
    const el = document.getElementById('live-clock');
    if (el) el.textContent = new Date().toLocaleTimeString('ar-SA');
}
setInterval(updateClock, 1000);
updateClock();

// ── Auto-refresh free count ──
(function() {
    const el = document.getElementById('free-count');
    if (!el) return;
    setInterval(function() {
        fetch('api.php?action=free_now')
            .then(r => r.json())
            .then(d => { if (d.teachers) el.textContent = d.teachers.length; })
            .catch(() => {});
    }, 60000);
}());

// ── Modals ──
function openAddModal(periodKey, periodLabel) {
    document.getElementById('add_period_key').value   = periodKey;
    document.getElementById('add_period_label').value = 'حصة ' + periodKey + '  (' + periodLabel + ')';
    document.getElementById('addModal').classList.add('open');
}

function openEditModal(slotId, activity, activityType) {
    document.getElementById('edit_slot_id').value        = slotId;
    document.getElementById('edit_activity').value       = activity;
    document.getElementById('edit_activity_type').value  = activityType;
    document.getElementById('editModal').classList.add('open');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}

// Close modal on overlay click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
    }
});

// Escape to close
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
    }
});

// ── Confirm deletes ──
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
        if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
});
