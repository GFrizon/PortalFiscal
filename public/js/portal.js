(function () {
    const body = document.body;
    const sidebar = document.getElementById('appSidebar');
    const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
    const mobileMenuButton = document.querySelector('[data-mobile-menu]');
    const mobileBackdrop = document.querySelector('[data-sidebar-close]');
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let currentPreviewUrl = null;

    body.classList.add('js-enhanced');

    requestAnimationFrame(() => {
        body.classList.add('is-ready');
    });

    if (localStorage.getItem('portal.sidebar.collapsed') === 'true') {
        body.classList.add('sidebar-collapsed');
        sidebar?.classList.add('is-collapsed');
    }

    sidebarToggle?.addEventListener('click', () => {
        body.classList.toggle('sidebar-collapsed');
        sidebar?.classList.toggle('is-collapsed', body.classList.contains('sidebar-collapsed'));
        localStorage.setItem('portal.sidebar.collapsed', body.classList.contains('sidebar-collapsed') ? 'true' : 'false');
    });

    mobileMenuButton?.addEventListener('click', () => {
        body.classList.add('mobile-menu-open');
    });

    mobileBackdrop?.addEventListener('click', () => {
        body.classList.remove('mobile-menu-open');
    });

    document.querySelectorAll('.nav-link-item').forEach((link) => {
        link.addEventListener('click', () => {
            body.classList.remove('mobile-menu-open');
        });
    });

    document.querySelectorAll('a[href]').forEach((link) => {
        link.addEventListener('click', (event) => {
            const href = link.getAttribute('href') || '';
            const url = new URL(link.href, window.location.href);
            const opensElsewhere = link.target === '_blank' || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;
            const isDownload = link.hasAttribute('download') || href.includes('/pdf/download');
            const isHashOnly = url.pathname === window.location.pathname && url.hash;

            if (opensElsewhere || isDownload || isHashOnly || url.origin !== window.location.origin) {
                return;
            }

            if (! prefersReducedMotion) {
                body.classList.add('is-navigating');
            }
        });
    });

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.matches('[data-pdf-preview-form]') && form.dataset.previewConfirmed !== 'true') {
                if (! form.checkValidity()) {
                    return;
                }

                const fileInput = form.querySelector('input[type="file"][name="pdf"]');
                const file = fileInput?.files?.[0];

                if (file && window.bootstrap) {
                    event.preventDefault();
                    openPdfPreview(form, file);

                    return;
                }
            }

            const message = event.submitter?.dataset.confirm || form.dataset.confirm;

            if (message && ! confirm(message)) {
                event.preventDefault();

                return;
            }

            const submitter = event.submitter;

            if (submitter instanceof HTMLButtonElement) {
                submitter.setAttribute('aria-busy', 'true');

                const icon = submitter.querySelector('i');

                if (icon) {
                    icon.className = 'bi bi-arrow-repeat';
                }
            }

            if (! prefersReducedMotion) {
                body.classList.add('is-navigating');
            }
        });
    });

    document.querySelectorAll('[data-file-input]').forEach((input) => {
        const target = document.querySelector(input.dataset.fileTarget);

        input.addEventListener('change', () => {
            if (! target) {
                return;
            }

            target.textContent = input.files?.[0]?.name || 'Nenhum arquivo selecionado.';
        });
    });

    document.querySelectorAll('[data-digits-only]').forEach((input) => {
        input.addEventListener('input', () => {
            input.value = input.value.replace(/\D/g, '');
        });
    });

    function openPdfPreview(form, file) {
        const modalElement = document.getElementById('pdfPreviewModal');
        const frame = modalElement?.querySelector('[data-pdf-preview-frame]');

        if (! modalElement || ! frame) {
            form.dataset.previewConfirmed = 'true';
            form.requestSubmit();

            return;
        }

        if (currentPreviewUrl) {
            URL.revokeObjectURL(currentPreviewUrl);
        }

        currentPreviewUrl = URL.createObjectURL(file);
        frame.src = currentPreviewUrl;

        setPreviewText(modalElement, '[data-preview-file-name]', file.name);
        setPreviewText(modalElement, '[data-preview-file-size]', formatBytes(file.size));
        setPreviewText(modalElement, '[data-preview-purchase-order]', form.querySelector('[name="purchase_order_number"]')?.value || '-');
        setPreviewText(modalElement, '[data-preview-arrival-date]', formatDate(form.querySelector('[name="arrival_date"]')?.value));
        setPreviewText(modalElement, '[data-preview-due-date]', formatDate(form.querySelector('[name="due_date"]')?.value));
        setPreviewText(modalElement, '[data-preview-notes]', form.querySelector('[name="user_notes"]')?.value || '-');

        const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
        const confirmButton = modalElement.querySelector('[data-confirm-pdf-upload]');

        confirmButton?.addEventListener('click', () => {
            form.dataset.previewConfirmed = 'true';
            modal.hide();
            form.requestSubmit();
        }, { once: true });

        modalElement.addEventListener('hidden.bs.modal', () => {
            if (form.dataset.previewConfirmed === 'true') {
                return;
            }

            frame.removeAttribute('src');

            if (currentPreviewUrl) {
                URL.revokeObjectURL(currentPreviewUrl);
                currentPreviewUrl = null;
            }
        }, { once: true });

        modal.show();
    }

    function setPreviewText(root, selector, value) {
        const element = root.querySelector(selector);

        if (element) {
            element.textContent = value || '-';
        }
    }

    function formatBytes(bytes) {
        if (! bytes) {
            return '-';
        }

        const units = ['B', 'KB', 'MB', 'GB'];
        let value = bytes;
        let unitIndex = 0;

        while (value >= 1024 && unitIndex < units.length - 1) {
            value /= 1024;
            unitIndex++;
        }

        return `${value.toLocaleString('pt-BR', {
            maximumFractionDigits: unitIndex === 0 ? 0 : 2,
        })} ${units[unitIndex]}`;
    }

    function formatDate(value) {
        if (! value) {
            return '-';
        }

        const [year, month, day] = value.split('-');

        if (! year || ! month || ! day) {
            return value;
        }

        return `${day}/${month}/${year}`;
    }
})();
