document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('sidebarToggle');
    var sidebar = document.getElementById('sidebar');
    if (!btn || !sidebar) return;

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        sidebar.classList.toggle('open');
    });
    document.addEventListener('click', function (e) {
        if (!sidebar.contains(e.target)) {
            sidebar.classList.remove('open');
        }
    });
});