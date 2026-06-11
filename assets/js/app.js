document.addEventListener('DOMContentLoaded', () => {
    // Initialize Theme Switcher
    initTheme();

    // Initialize Copy Snippet Buttons
    initCopyButtons();

    // Initialize File Upload Dropzone listeners if present
    initDropzones();
});

/**
 * Initializes and manages theme switching (light/dark mode).
 */
function initTheme() {
    const themeBtn = document.getElementById('theme-toggle-btn');
    if (!themeBtn) return;

    // Check saved preference or system preference
    const savedTheme = localStorage.getItem('theme');
    const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    let currentTheme = savedTheme || (systemDark ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', currentTheme);
    updateThemeIcon(currentTheme);

    themeBtn.addEventListener('click', () => {
        const activeTheme = document.documentElement.getAttribute('data-theme');
        const nextTheme = activeTheme === 'dark' ? 'light' : 'dark';
        
        document.documentElement.setAttribute('data-theme', nextTheme);
        localStorage.setItem('theme', nextTheme);
        updateThemeIcon(nextTheme);
    });
}

function updateThemeIcon(theme) {
    const themeIcon = document.querySelector('#theme-toggle-btn i');
    if (!themeIcon) return;
    
    if (theme === 'dark') {
        themeIcon.className = 'bi bi-sun-fill';
    } else {
        themeIcon.className = 'bi bi-moon-fill';
    }
}

/**
 * Handles copy-to-clipboard actions on code blocks.
 */
function initCopyButtons() {
    document.body.addEventListener('click', async (e) => {
        if (e.target.classList.contains('copy-btn')) {
            const btn = e.target;
            const targetId = btn.getAttribute('data-target');
            let text = '';
            
            if (targetId) {
                const codeBlock = document.getElementById(targetId);
                text = codeBlock ? codeBlock.innerText : '';
            } else {
                // Otherwise copy from sibling pre or code code block
                const pre = btn.closest('pre');
                if (pre) {
                    // Temporarily remove button text if we fetch from innerHTML
                    const clone = pre.cloneNode(true);
                    const btnInClone = clone.querySelector('.copy-btn');
                    if (btnInClone) btnInClone.remove();
                    text = clone.innerText;
                }
            }
            
            if (!text) return;
            
            try {
                await navigator.clipboard.writeText(text);
                const originalText = btn.innerText;
                btn.innerText = 'Copied!';
                btn.classList.add('btn-success');
                setTimeout(() => {
                    btn.innerText = originalText;
                    btn.classList.remove('btn-success');
                }, 2000);
            } catch (err) {
                console.error('Failed to copy text: ', err);
            }
        }
    });
}

/**
 * Drag and drop upload effects.
 */
function initDropzones() {
    const dropzones = document.querySelectorAll('.dropzone-area');
    dropzones.forEach(zone => {
        const fileInput = document.getElementById(zone.getAttribute('data-input'));
        if (!fileInput) return;

        zone.addEventListener('click', () => fileInput.click());

        fileInput.addEventListener('change', () => {
            if (fileInput.files.length > 0) {
                const label = zone.querySelector('.dropzone-label');
                if (label) {
                    label.innerText = fileInput.files[0].name;
                }
            }
        });

        // Dragover effects
        zone.addEventListener('dragover', (e) => {
            e.preventDefault();
            zone.classList.add('dragover');
        });

        zone.addEventListener('dragleave', () => {
            zone.classList.remove('dragover');
        });

        zone.addEventListener('drop', (e) => {
            e.preventDefault();
            zone.classList.remove('dragover');
            
            if (e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                const label = zone.querySelector('.dropzone-label');
                if (label) {
                    label.innerText = e.dataTransfer.files[0].name;
                }
            }
        });
    });
}
