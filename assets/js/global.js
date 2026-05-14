document.addEventListener("DOMContentLoaded", function () {
    
    // 1. Elements
    const sidebar = document.getElementById("sidebar");
    const toggleBtn = document.getElementById("toggle-sidebar");
    const themeBtn = document.getElementById("toggle-theme");
    const themeIcon = themeBtn ? themeBtn.querySelector("i") : null;
    const themeText = themeBtn ? themeBtn.querySelector(".link-text") : null;

    // ---------------------------------------------------------
    // 1. THEME LOGIC (Synchronized)
    // ---------------------------------------------------------
    
    // Function to Force a Specific Theme
    function applyTheme(themeName) {
        const html = document.documentElement;
        
        // 1. Set the Global Bootstrap Theme (Content Area)
        html.setAttribute('data-bs-theme', themeName);
        localStorage.setItem("theme", themeName);

        // 2. Set the Sidebar Theme (Explicitly match the global theme)
        if (themeName === 'dark') {
            // DARK MODE: Sidebar is Dark, Text is White
            sidebar.classList.remove('bg-white', 'text-dark', 'border-end');
            sidebar.classList.add('bg-dark', 'text-white');
            
            // Fix Links color
            document.querySelectorAll('#sidebar .nav-link, #sidebar .btn-toggle').forEach(el => {
                el.classList.remove('text-dark');
                el.classList.add('text-white');
            });

            // Button Text updates
            if (themeIcon) themeIcon.className = "bi bi-sun-fill me-2";
            if (themeText) themeText.textContent = "Light Mode";

        } else {
            // LIGHT MODE: Sidebar is White, Text is Dark
            sidebar.classList.remove('bg-dark', 'text-white');
            sidebar.classList.add('bg-white', 'text-dark', 'border-end');
            
            // Fix Links color
            document.querySelectorAll('#sidebar .nav-link, #sidebar .btn-toggle').forEach(el => {
                el.classList.remove('text-white');
                el.classList.add('text-dark');
            });

            // Button Text updates
            if (themeIcon) themeIcon.className = "bi bi-moon-stars-fill me-2";
            if (themeText) themeText.textContent = "Dark Mode";
        }
    }

    // Initialize on Load
    const savedTheme = localStorage.getItem("theme") || "light";
    applyTheme(savedTheme);

    // Toggle Button Click Event
    if (themeBtn) {
        themeBtn.addEventListener("click", function(e) {
            e.preventDefault();
            const currentTheme = document.documentElement.getAttribute('data-bs-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            applyTheme(newTheme);
        });
    }

    // ---------------------------------------------------------
    // 2. SIDEBAR COLLAPSE LOGIC (Keep existing working logic)
    // ---------------------------------------------------------
    if (toggleBtn && sidebar) {
        // Check saved state
        if (localStorage.getItem("sidebar-state") === "collapsed") {
            sidebar.classList.add("collapsed");
        }

        toggleBtn.addEventListener("click", function () {
            sidebar.classList.toggle("collapsed");
            
            if (sidebar.classList.contains("collapsed")) {
                localStorage.setItem("sidebar-state", "collapsed");
                // Close open submenus
                document.querySelectorAll('#sidebar .collapse.show').forEach(function(el) {
                    var bsCollapse = bootstrap.Collapse.getInstance(el);
                    if (bsCollapse) bsCollapse.hide();
                    else new bootstrap.Collapse(el).hide();
                });
            } else {
                localStorage.setItem("sidebar-state", "expanded");
            }
        });
    }
});