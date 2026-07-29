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

    window.addEventListener('pageshow', () => {
        body.classList.remove('is-navigating');
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
        const form = input.closest('form');

        input.addEventListener('change', () => {
            if (! target) {
                return;
            }

            target.textContent = input.files?.[0]?.name || 'Nenhum arquivo selecionado.';
            updateInlinePdfPreview(form, input.files?.[0] || null);
        });
    });

    document.querySelectorAll('[data-pdf-preview-form]').forEach((form) => {
        const refreshPreview = () => {
            const file = form.querySelector('input[type="file"][name="pdf"]')?.files?.[0] || null;
            updateInlinePdfPreview(form, file);
        };

        form.addEventListener('input', refreshPreview);
        form.addEventListener('change', refreshPreview);
    });

    document.querySelectorAll('[data-copy-text]').forEach((button) => {
        button.addEventListener('click', async () => {
            const text = button.dataset.copyText || '';

            if (! text) {
                return;
            }

            await copyText(text);

            const icon = button.querySelector('i');
            const originalClass = icon?.className;

            if (icon) {
                icon.className = 'bi bi-check2';
                window.setTimeout(() => {
                    icon.className = originalClass || 'bi bi-clipboard';
                }, 1200);
            }
        });
    });

    document.querySelectorAll('[data-digits-only]').forEach((input) => {
        input.addEventListener('input', () => {
            input.value = input.value.replace(/\D/g, '');
        });
    });

    document.querySelectorAll('[data-document-type-select]').forEach((select) => {
        const form = select.closest('form');
        const label = form?.querySelector('[data-reference-label]');
        const field = form?.querySelector('[data-reference-field]');
        const input = form?.querySelector('[name="purchase_order_number"]');

        const syncReferenceLabel = () => {
            if (! label || ! field || ! input) {
                return;
            }

            const withoutPurchaseOrder = select.value === 'nf_no_oc';
            label.textContent = select.value === 'cte' ? 'Nota Fiscal' : 'Ordem de compra';
            field.hidden = withoutPurchaseOrder;
            input.disabled = withoutPurchaseOrder;
            input.required = ! withoutPurchaseOrder;

            if (withoutPurchaseOrder) {
                input.value = '';
            }
        };

        select.addEventListener('change', syncReferenceLabel);
        syncReferenceLabel();
    });

    document.querySelectorAll('[data-payment-method]').forEach((select) => {
        const form = select.closest('form');
        const area = form?.querySelector('[data-payment-installments-area]');
        const countInput = form?.querySelector('[data-installments-count]');
        const grid = form?.querySelector('[data-installments-grid]');

        const syncInstallments = () => {
            if (! area || ! countInput || ! grid) {
                return;
            }

            const requiresInstallments = select.value !== 'anticipated';
            area.hidden = ! requiresInstallments;
            countInput.disabled = ! requiresInstallments;
            countInput.required = requiresInstallments;

            const count = Math.max(1, Math.min(12, Number.parseInt(countInput.value || '1', 10) || 1));
            countInput.value = String(count);

            while (grid.children.length < count) {
                grid.appendChild(createInstallmentRow(grid.children.length));
            }

            while (grid.children.length > count) {
                grid.lastElementChild?.remove();
            }

            grid.querySelectorAll('[data-installment-row]').forEach((row, index) => {
                row.querySelector('.installment-number').textContent = `#${index + 1}`;

                const dueDate = row.querySelector('[data-installment-due-date]');
                const amount = row.querySelector('[data-installment-amount]');

                if (dueDate) {
                    dueDate.name = `payment_installments[${index}][due_date]`;
                    dueDate.id = `payment_installments_${index}_due_date`;
                    dueDate.disabled = ! requiresInstallments;
                    dueDate.required = requiresInstallments;
                    row.querySelector('label[for$="_due_date"]')?.setAttribute('for', dueDate.id);
                }

                if (amount) {
                    amount.name = `payment_installments[${index}][amount]`;
                    amount.id = `payment_installments_${index}_amount`;
                    amount.disabled = ! requiresInstallments;
                    amount.required = requiresInstallments;
                    row.querySelector('label[for$="_amount"]')?.setAttribute('for', amount.id);
                    bindCurrencyInput(amount);
                }
            });
        };

        select.addEventListener('change', syncInstallments);
        countInput?.addEventListener('change', syncInstallments);
        syncInstallments();
    });

    document.querySelectorAll('[data-installment-amount]').forEach(bindCurrencyInput);

    function updateInlinePdfPreview(form, file) {
        const preview = document.querySelector('[data-inline-pdf-preview]');
        const frame = preview?.querySelector('[data-pdf-preview-frame]');

        if (! form || ! preview || ! frame) {
            return;
        }

        if (! file) {
            preview.hidden = true;
            delete preview.dataset.fileSignature;
            frame.removeAttribute('src');

            if (currentPreviewUrl) {
                URL.revokeObjectURL(currentPreviewUrl);
                currentPreviewUrl = null;
            }

            return;
        }

        const fileSignature = `${file.name}:${file.size}:${file.lastModified}`;

        if (preview.dataset.fileSignature !== fileSignature) {
            if (currentPreviewUrl) {
                URL.revokeObjectURL(currentPreviewUrl);
            }

            currentPreviewUrl = URL.createObjectURL(file);
            frame.src = currentPreviewUrl;
            preview.dataset.fileSignature = fileSignature;
        }

        preview.hidden = false;

        setPreviewText(preview, '[data-preview-file-name]', file.name);
        setPreviewText(preview, '[data-preview-file-size]', formatBytes(file.size));
        const documentType = form.querySelector('[name="document_type"]')?.value || 'nf';
        setPreviewText(preview, '[data-preview-reference-label]', documentType === 'cte' ? 'Nota Fiscal' : 'Ordem');
        setPreviewText(preview, '[data-preview-purchase-order]', form.querySelector('[name="purchase_order_number"]')?.value || '-');
        setPreviewText(preview, '[data-preview-arrival-date]', formatDate(form.querySelector('[name="arrival_date"]')?.value));
        setPreviewText(preview, '[data-preview-payment]', formatPayment(form));
        setPreviewText(preview, '[data-preview-notes]', form.querySelector('[name="user_notes"]')?.value || '-');
    }

    function setPreviewText(root, selector, value) {
        const element = root.querySelector(selector);

        if (element) {
            element.textContent = value || '-';
        }
    }

    async function copyText(text) {
        if (navigator.clipboard?.writeText) {
            try {
                await navigator.clipboard.writeText(text);

                return;
            } catch (error) {
                // Fall back to the textarea copy path below.
            }
        }

        const input = document.createElement('textarea');
        input.value = text;
        input.setAttribute('readonly', '');
        input.style.position = 'fixed';
        input.style.opacity = '0';
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        input.remove();
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

    function createInstallmentRow(index) {
        const row = document.createElement('div');
        row.className = 'installment-row';
        row.dataset.installmentRow = '';
        row.innerHTML = `
            <div class="installment-number">#${index + 1}</div>
            <div>
                <label class="form-label" for="payment_installments_${index}_due_date">Vencimento</label>
                <input class="form-control" id="payment_installments_${index}_due_date" type="date" name="payment_installments[${index}][due_date]" data-installment-due-date>
            </div>
            <div>
                <label class="form-label" for="payment_installments_${index}_amount">Valor</label>
                <input class="form-control" id="payment_installments_${index}_amount" name="payment_installments[${index}][amount]" inputmode="decimal" data-installment-amount>
            </div>
        `;

        return row;
    }

    function bindCurrencyInput(input) {
        if (input.dataset.currencyBound === 'true') {
            return;
        }

        input.dataset.currencyBound = 'true';
        input.addEventListener('input', () => {
            input.value = formatCurrencyDigits(input.value);
        });
    }

    function formatCurrencyDigits(value) {
        const digits = value.replace(/\D/g, '');

        if (! digits) {
            return '';
        }

        const amount = Number.parseInt(digits, 10) / 100;

        return amount.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function formatPayment(form) {
        const method = form.querySelector('[name="payment_method"]')?.value || 'anticipated';

        if (method === 'anticipated') {
            return 'Antecipado';
        }

        const label = method === 'boleto' ? 'Boleto' : 'Deposito';
        const count = form.querySelector('[name="payment_installments_count"]')?.value || '1';

        return `${label} - ${count} parcela${count === '1' ? '' : 's'}`;
    }
})();
